<?php

// Monitor Laravel log in real-time
$logFile = 'C:/laragon/www/plnip-portal/storage/logs/laravel.log';

echo "=== Monitoring Laravel Log (Press Ctrl+C to stop) ===\n";
echo "Waiting for new log entries...\n\n";

$lastSize = filesize($logFile);

while (true) {
    clearstatcache();
    $currentSize = filesize($logFile);

    if ($currentSize > $lastSize) {
        $handle = fopen($logFile, 'r');
        fseek($handle, $lastSize);

        while ($line = fgets($handle)) {
            echo $line;
        }

        fclose($handle);
        $lastSize = $currentSize;
    }

    usleep(500000); // 0.5 second
}
