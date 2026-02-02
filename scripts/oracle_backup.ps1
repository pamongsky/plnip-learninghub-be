# ORACLE AUTOMATED BACKUP SCRIPT FOR WINDOWS
# Run this with Windows Task Scheduler daily at 2 AM

# Configuration
$ORACLE_HOME = "C:\oracle\product\19c"
$BACKUP_DIR = "C:\oracle\backups"
$DATE = Get-Date -Format "yyyyMMdd_HHmmss"
$LOG_FILE = "$BACKUP_DIR\backup_$DATE.log"
$RETENTION_DAYS = 7

# Database credentials (use secure credential manager in production)
$DB_USER = $env:DB_USERNAME
$DB_PASS = $env:DB_PASSWORD
$DB_SID = $env:DB_SID

if (!$DB_USER) { $DB_USER = "system" }
if (!$DB_SID) { $DB_SID = "ORCL" }

function Log {
    param($Message)
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] $Message"
    Write-Host $logMessage
    Add-Content -Path $LOG_FILE -Value $logMessage
}

Log "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
Log "🔄 Oracle Backup Started"
Log "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Create backup directory
New-Item -ItemType Directory -Force -Path $BACKUP_DIR | Out-Null

# Step 1: Data Pump Export
Log ""
Log "Step 1: Exporting database with Data Pump..."
Log "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

$expdpCmd = @"
expdp $DB_USER/$DB_PASS@$DB_SID DIRECTORY=DATA_PUMP_DIR DUMPFILE=plnip_backup_$DATE.dmp LOGFILE=plnip_backup_$DATE.log FULL=Y COMPRESSION=ALL PARALLEL=4
"@

try {
    Invoke-Expression $expdpCmd 2>&1 | Tee-Object -Append -FilePath $LOG_FILE
    Log "✓ Data Pump export completed"
} catch {
    Log "❌ Data Pump export FAILED: $_"
    exit 1
}

# Step 2: Quick backup of critical tables
Log ""
Log "Step 2: Quick backup of critical tables..."
Log "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

$criticalCmd = @"
expdp $DB_USER/$DB_PASS@$DB_SID DIRECTORY=DATA_PUMP_DIR DUMPFILE=plnip_critical_$DATE.dmp TABLES=USERS,ROLES,PERMISSIONS,ANNOUNCEMENTS,COURSES,SUPPORT_TICKETS COMPRESSION=ALL
"@

try {
    Invoke-Expression $criticalCmd 2>&1 | Tee-Object -Append -FilePath $LOG_FILE
    Log "✓ Critical tables backup completed"
} catch {
    Log "⚠️  Critical backup warning: $_"
}

# Step 3: Verify backup exists
Log ""
Log "Step 3: Verifying backups..."
Log "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

$dumpDir = "$ORACLE_HOME\admin\$DB_SID\dpdump"
$backupFile = "$dumpDir\plnip_backup_$DATE.dmp"

if (Test-Path $backupFile) {
    $size = (Get-Item $backupFile).Length / 1MB
    Log "✓ Full backup exists: $backupFile ($([math]::Round($size, 2)) MB)"
} else {
    Log "❌ Backup file not found: $backupFile"
}

# Step 4: Cleanup old backups
Log ""
Log "Step 4: Cleaning up old backups..."
Log "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

$cutoffDate = (Get-Date).AddDays(-$RETENTION_DAYS)
Get-ChildItem "$dumpDir\plnip_backup_*.dmp" | Where-Object { $_.LastWriteTime -lt $cutoffDate } | Remove-Item -Force
Get-ChildItem "$dumpDir\plnip_critical_*.dmp" | Where-Object { $_.LastWriteTime -lt $cutoffDate } | Remove-Item -Force
Get-ChildItem "$BACKUP_DIR\backup_*.log" | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-30) } | Remove-Item -Force

Log "✓ Old backups cleaned (retention: $RETENTION_DAYS days)"

# Step 5: Copy to network backup (optional)
# Uncomment and configure for network backup
# Log ""
# Log "Step 5: Copying to network backup..."
# $networkPath = "\\backup-server\oracle\plnip"
# Copy-Item $backupFile -Destination $networkPath -Force

Log ""
Log "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
Log "✅ Backup Completed Successfully"
Log "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Send notification (configure as needed)
# Send-MailMessage -To "dba@plnip.co.id" -Subject "Oracle Backup Success" -Body "Backup completed at $(Get-Date)"

exit 0
