<?php

/* Kill all threads if main script is ended */
$pid = $argv[1];
$matcher = base64_decode($argv[2]);

if (!is_numeric($pid)) {
    die("The first argument must be a numeric PID.\n");
}

// Function to check if a process is running
function isProcessRunning($pid): bool
{
    if (stristr(PHP_OS, 'WIN')) {
        // Windows command to check if a process is running
        $output = [];
        exec("tasklist /FI \"PID eq $pid\" 2>NUL", $output);

        return count($output) > 1;
    } else {
        // Linux command to check if a process is running
        return posix_kill($pid, 0);
    }
}

// Function to kill all processes containing the matcher string in their command
function killProcessesMatching($matcher): void
{

    if (stristr(PHP_OS, 'WIN')) {
        // Windows command to kill processes matching the string
        exec('wmic process where "commandline like \'%'.$matcher.'%\'" call terminate');
    } else {
        // Linux command to kill processes matching the string
        exec("pkill -f \"$matcher\"");
    }
}

while (true) {

    if (!isProcessRunning($pid)) {
        killProcessesMatching($matcher);

        break;
    }
    sleep(1);
}
echo "Monitoring complete.\n";
