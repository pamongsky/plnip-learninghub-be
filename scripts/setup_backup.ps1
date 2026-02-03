# SETUP AUTOMATED ORACLE BACKUP FOR WINDOWS
# Run this script as Administrator

Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
Write-Host "🔧 SETTING UP AUTOMATED ORACLE BACKUP" -ForegroundColor Cyan
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
Write-Host ""

# Check if running as Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Host "❌ ERROR: This script must be run as Administrator" -ForegroundColor Red
    Write-Host "   Right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    exit 1
}

Write-Host "✓ Running as Administrator" -ForegroundColor Green
Write-Host ""

# Configuration
$SCRIPT_PATH = "C:\laragon\www\plnip-portal\scripts\oracle_backup.ps1"
$BACKUP_DIR = "C:\oracle\backups"
$TASK_NAME = "Oracle_Daily_Backup_PLNIP"

# Step 1: Create backup directory
Write-Host "Step 1: Creating backup directory..." -ForegroundColor Cyan
if (-not (Test-Path $BACKUP_DIR)) {
    New-Item -ItemType Directory -Path $BACKUP_DIR -Force | Out-Null
    Write-Host "✓ Created: $BACKUP_DIR" -ForegroundColor Green
} else {
    Write-Host "✓ Directory already exists: $BACKUP_DIR" -ForegroundColor Green
}
Write-Host ""

# Step 2: Set environment variables (for security)
Write-Host "Step 2: Configure database credentials..." -ForegroundColor Cyan
Write-Host "   (These will be stored as system environment variables)" -ForegroundColor Gray
Write-Host ""

$DB_USER = Read-Host "Enter Oracle username (e.g., system)"
$DB_PASS = Read-Host "Enter Oracle password" -AsSecureString
$DB_PASS_Plain = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($DB_PASS))
$DB_SID = Read-Host "Enter Oracle SID (e.g., ORCL)"

[System.Environment]::SetEnvironmentVariable('DB_USERNAME', $DB_USER, 'Machine')
[System.Environment]::SetEnvironmentVariable('DB_PASSWORD', $DB_PASS_Plain, 'Machine')
[System.Environment]::SetEnvironmentVariable('DB_SID', $DB_SID, 'Machine')

Write-Host "✓ Environment variables set" -ForegroundColor Green
Write-Host ""

# Step 3: Create scheduled task
Write-Host "Step 3: Creating scheduled task..." -ForegroundColor Cyan

# Check if task already exists
$existingTask = Get-ScheduledTask -TaskName $TASK_NAME -ErrorAction SilentlyContinue

if ($existingTask) {
    Write-Host "⚠️  Task already exists. Removing old task..." -ForegroundColor Yellow
    Unregister-ScheduledTask -TaskName $TASK_NAME -Confirm:$false
}

# Create task action
$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-ExecutionPolicy Bypass -File `"$SCRIPT_PATH`""

# Create task trigger (daily at 2:00 AM)
$trigger = New-ScheduledTaskTrigger -Daily -At "2:00AM"

# Create task settings
$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -RunOnlyIfNetworkAvailable:$false `
    -ExecutionTimeLimit (New-TimeSpan -Hours 4)

# Register the task (run as SYSTEM)
Register-ScheduledTask `
    -TaskName $TASK_NAME `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -User "SYSTEM" `
    -RunLevel Highest `
    -Description "Automated Oracle database backup for PLNIP Portal (daily at 2 AM)" | Out-Null

Write-Host "✓ Scheduled task created: $TASK_NAME" -ForegroundColor Green
Write-Host "   Schedule: Daily at 2:00 AM" -ForegroundColor Gray
Write-Host "   Run as: SYSTEM" -ForegroundColor Gray
Write-Host ""

# Step 4: Test backup script
Write-Host "Step 4: Testing backup script..." -ForegroundColor Cyan
Write-Host "   Do you want to run a test backup now? (y/n): " -NoNewline
$runTest = Read-Host

if ($runTest -eq 'y' -or $runTest -eq 'Y') {
    Write-Host ""
    Write-Host "Running test backup..." -ForegroundColor Yellow
    Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Gray

    try {
        & powershell.exe -ExecutionPolicy Bypass -File $SCRIPT_PATH
        Write-Host ""
        Write-Host "✓ Test backup completed" -ForegroundColor Green
    } catch {
        Write-Host ""
        Write-Host "❌ Test backup failed: $_" -ForegroundColor Red
    }
} else {
    Write-Host "   Skipped test backup" -ForegroundColor Gray
}

Write-Host ""
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
Write-Host "✅ BACKUP AUTOMATION SETUP COMPLETE!" -ForegroundColor Green
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
Write-Host ""
Write-Host "📋 Summary:" -ForegroundColor White
Write-Host "   • Task Name: $TASK_NAME" -ForegroundColor Gray
Write-Host "   • Schedule: Daily at 2:00 AM" -ForegroundColor Gray
Write-Host "   • Backup Location: $BACKUP_DIR" -ForegroundColor Gray
Write-Host "   • Retention: 7 days" -ForegroundColor Gray
Write-Host ""
Write-Host "🔍 To verify:" -ForegroundColor White
Write-Host "   1. Open Task Scheduler (taskschd.msc)" -ForegroundColor Gray
Write-Host "   2. Look for: $TASK_NAME" -ForegroundColor Gray
Write-Host "   3. Or run: Get-ScheduledTask -TaskName '$TASK_NAME'" -ForegroundColor Gray
Write-Host ""
Write-Host "🚀 To run manually:" -ForegroundColor White
Write-Host "   Start-ScheduledTask -TaskName '$TASK_NAME'" -ForegroundColor Gray
Write-Host ""
Write-Host "📂 Backup files will be stored at:" -ForegroundColor White
Write-Host "   $BACKUP_DIR" -ForegroundColor Gray
Write-Host ""

Write-Host "Press any key to exit..." -ForegroundColor DarkGray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
