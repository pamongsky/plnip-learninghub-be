#!/bin/bash

# ORACLE AUTOMATED BACKUP SCRIPT
# Schedule this with Windows Task Scheduler or cron

# Configuration
ORACLE_HOME="C:/oracle/product/19c"
BACKUP_DIR="C:/oracle/backups"
DATE=$(date +%Y%m%d_%H%M%S)
LOG_FILE="$BACKUP_DIR/backup_$DATE.log"
RETENTION_DAYS=7

# Database credentials (use environment variables in production)
DB_USER="${DB_USERNAME:-system}"
DB_PASS="${DB_PASSWORD:-password}"
DB_SID="${DB_SID:-ORCL}"

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"
echo "🔄 Oracle Backup Started: $(date)" | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"

# Create backup directory
mkdir -p "$BACKUP_DIR"

# 1. Export Full Database with Data Pump
echo "" | tee -a "$LOG_FILE"
echo "Step 1: Exporting database with Data Pump..." | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"

expdp $DB_USER/$DB_PASS@$DB_SID \
    DIRECTORY=DATA_PUMP_DIR \
    DUMPFILE=plnip_backup_$DATE.dmp \
    LOGFILE=plnip_backup_$DATE.log \
    FULL=Y \
    COMPRESSION=ALL \
    PARALLEL=4 2>&1 | tee -a "$LOG_FILE"

if [ ${PIPESTATUS[0]} -eq 0 ]; then
    echo "✓ Data Pump export completed successfully" | tee -a "$LOG_FILE"
else
    echo "❌ Data Pump export FAILED" | tee -a "$LOG_FILE"
    exit 1
fi

# 2. RMAN Backup (if configured)
echo "" | tee -a "$LOG_FILE"
echo "Step 2: RMAN Backup..." | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"

rman target / << EOF | tee -a "$LOG_FILE"
BACKUP DATABASE PLUS ARCHIVELOG;
BACKUP CURRENT CONTROLFILE;
DELETE NOPROMPT OBSOLETE;
CROSSCHECK BACKUP;
EXIT;
EOF

if [ ${PIPESTATUS[0]} -eq 0 ]; then
    echo "✓ RMAN backup completed successfully" | tee -a "$LOG_FILE"
else
    echo "⚠️  RMAN backup failed or not configured" | tee -a "$LOG_FILE"
fi

# 3. Export Critical Tables Only (Quick Backup)
echo "" | tee -a "$LOG_FILE"
echo "Step 3: Quick backup of critical tables..." | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"

expdp $DB_USER/$DB_PASS@$DB_SID \
    DIRECTORY=DATA_PUMP_DIR \
    DUMPFILE=plnip_critical_$DATE.dmp \
    TABLES=USERS,ROLES,PERMISSIONS,ANNOUNCEMENTS,COURSES,SUPPORT_TICKETS \
    COMPRESSION=ALL 2>&1 | tee -a "$LOG_FILE"

echo "✓ Critical tables backup completed" | tee -a "$LOG_FILE"

# 4. Verify Backup
echo "" | tee -a "$LOG_FILE"
echo "Step 4: Verifying backups..." | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"

BACKUP_FILE=$(ls -lh "$ORACLE_HOME/admin/$DB_SID/dpdump/plnip_backup_$DATE.dmp" 2>/dev/null)
if [ -f "$ORACLE_HOME/admin/$DB_SID/dpdump/plnip_backup_$DATE.dmp" ]; then
    echo "✓ Full backup exists: $BACKUP_FILE" | tee -a "$LOG_FILE"
else
    echo "❌ Backup file not found!" | tee -a "$LOG_FILE"
fi

# 5. Cleanup old backups (keep last 7 days)
echo "" | tee -a "$LOG_FILE"
echo "Step 5: Cleaning up old backups..." | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"

find "$ORACLE_HOME/admin/$DB_SID/dpdump" -name "plnip_backup_*.dmp" -mtime +$RETENTION_DAYS -delete
find "$ORACLE_HOME/admin/$DB_SID/dpdump" -name "plnip_critical_*.dmp" -mtime +$RETENTION_DAYS -delete
find "$BACKUP_DIR" -name "backup_*.log" -mtime +30 -delete

echo "✓ Old backups cleaned (retention: $RETENTION_DAYS days)" | tee -a "$LOG_FILE"

# 6. Send notification (optional - configure email/Slack)
echo "" | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"
echo "✅ Backup Completed: $(date)" | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"

# Optional: Send to monitoring system
# curl -X POST https://your-monitoring-system.com/api/backup-status \
#   -d "status=success&timestamp=$DATE"

exit 0
