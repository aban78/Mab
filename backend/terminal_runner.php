<?php
/**
 * Standalone background runner for terminal commands.
 * Runs in CLI mode.
 */
set_time_limit(0);
ignore_user_abort(true);

$cmd_file = __DIR__ . '/.terminal_cmd.json';
$output_file = __DIR__ . '/.terminal_output.txt';
$input_file = __DIR__ . '/.terminal_input.txt';
$control_file = __DIR__ . '/.terminal_control.json';
$status_file = __DIR__ . '/.terminal_status.json';

if (!file_exists($cmd_file)) {
    exit("No command configuration file found.");
}

$config = json_decode(file_get_contents($cmd_file), true);
if (!$config || empty($config['command'])) {
    exit("Invalid command configuration.");
}

$command = $config['command'];
$workspace_dir = isset($config['workspace_dir']) ? $config['workspace_dir'] : dirname(__DIR__);

// Helper to find the Flutter bin directory path dynamically
function find_flutter_path() {
    $default_path = 'D:\\flutter\\bin';
    if (is_dir($default_path)) {
        return $default_path;
    }
    
    $is_windows = (strncasecmp(PHP_OS, 'WIN', 3) === 0);
    if ($is_windows) {
        $out = @shell_exec('where.exe flutter 2>NUL');
        if (!empty($out)) {
            $lines = explode("\n", $out);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && is_file($line)) {
                    return dirname($line);
                }
            }
        }
    } else {
        $out = @shell_exec('which flutter 2>/dev/null');
        if (!empty($out)) {
            $line = trim($out);
            if (!empty($line) && is_file($line)) {
                return dirname($line);
            }
        }
    }
    return '';
}

// Clear files
@file_put_contents($output_file, '');
@file_put_contents($input_file, '');
@file_put_contents($control_file, json_encode(['action' => 'run']));

// Write initial status
@file_put_contents($status_file, json_encode([
    'status' => 'running',
    'pid' => getmypid(),
    'start_time' => time()
]));

$descriptorspec = array(
    0 => array("pipe", "r"), // stdin
    1 => array("pipe", "w"), // stdout
    2 => array("pipe", "w")  // stderr
);

// We need to run the command in the workspace directory context with a normalized environment
$env = getenv();
if (!is_array($env)) {
    $env = array();
}
foreach ($_SERVER as $k => $v) {
    if (is_string($v) && !isset($env[$k])) {
        $env[$k] = $v;
    }
}

$is_sh = isset($env['SHELL']) || isset($env['BASH']) || isset($env['TERM']) || (strpos($command, 'sh ') !== false) || (strpos($command, 'bash ') !== false);

// Find Flutter path dynamically and replace command / update environment
$flutter_bin = find_flutter_path();
if (!empty($flutter_bin)) {
    $flutter_win_exe = $flutter_bin . DIRECTORY_SEPARATOR . 'flutter.bat';
    $flutter_sh_exe = $flutter_bin . DIRECTORY_SEPARATOR . 'flutter';
    if (is_file($flutter_win_exe)) {
        $absolute_flutter = $is_sh ? $flutter_sh_exe : $flutter_win_exe;
        if ($is_sh) {
            $absolute_flutter = str_replace('\\', '/', $absolute_flutter);
        }
        $command = preg_replace('/\bflutter\b/', '"' . $absolute_flutter . '"', $command);
    }
}

// Normalize PATH for Windows/Unix compatibility when running under Git Bash / sh
$path_key = 'PATH';
foreach ($env as $k => $v) {
    if (strcasecmp($k, 'path') === 0) {
        $path_key = $k;
        break;
    }
}
$path_val = isset($env[$path_key]) ? $env[$path_key] : '';
if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
    $paths_win = explode(';', $path_val);
    $paths_posix = array();
    foreach ($paths_win as $p) {
        $p = trim($p);
        if (empty($p)) continue;
        if (preg_match('/^([a-zA-Z]):\\\(.*)$/', $p, $matches)) {
            $drive = strtolower($matches[1]);
            $rest = str_replace('\\', '/', $matches[2]);
            $paths_posix[] = '/' . $drive . '/' . $rest;
        } elseif (preg_match('/^([a-zA-Z]):\/(.*)$/', $p, $matches)) {
            $drive = strtolower($matches[1]);
            $rest = $matches[2];
            $paths_posix[] = '/' . $drive . '/' . $rest;
        } else {
            $paths_posix[] = str_replace('\\', '/', $p);
        }
    }
    
    if (!empty($flutter_bin)) {
        $flutter_win_dir = $flutter_bin;
        $flutter_posix_dir = $flutter_bin;
        if (preg_match('/^([a-zA-Z]):\\\(.*)$/', $flutter_bin, $matches)) {
            $drive = strtolower($matches[1]);
            $rest = str_replace('\\', '/', $matches[2]);
            $flutter_posix_dir = '/' . $drive . '/' . $rest;
        }
        if (!in_array($flutter_win_dir, $paths_win)) {
            $paths_win[] = $flutter_win_dir;
        }
        if (!in_array($flutter_posix_dir, $paths_posix)) {
            $paths_posix[] = $flutter_posix_dir;
        }
    }
    
    $normalized_path = $is_sh ? implode(':', $paths_posix) : implode(';', $paths_win);
    $env['PATH'] = $normalized_path;
    $env['Path'] = $normalized_path;
    $env['path'] = $normalized_path;
}
$process = proc_open($command, $descriptorspec, $pipes, $workspace_dir, $env);

if (!is_resource($process)) {
    @file_put_contents($output_file, "Error: Failed to start process: " . $command);
    @file_put_contents($status_file, json_encode(['status' => 'stopped', 'exit_code' => -1]));
    exit();
}

// Make streams non-blocking
stream_set_blocking($pipes[1], 0);
stream_set_blocking($pipes[2], 0);

$running = true;
while ($running) {
    // 1. Read stdout
    $stdout = fread($pipes[1], 8192);
    if ($stdout !== false && $stdout !== '') {
        file_put_contents($output_file, $stdout, FILE_APPEND);
    }

    // 2. Read stderr
    $stderr = fread($pipes[2], 8192);
    if ($stderr !== false && $stderr !== '') {
        file_put_contents($output_file, $stderr, FILE_APPEND);
    }

    // 3. Check for stdin input
    if (file_exists($input_file) && filesize($input_file) > 0) {
        $input_content = file_get_contents($input_file);
        if ($input_content !== false && $input_content !== '') {
            fwrite($pipes[0], $input_content);
            fflush($pipes[0]);
            // Clear input file
            file_put_contents($input_file, '');
        }
    }

    // 4. Check for control actions (stop)
    if (file_exists($control_file)) {
        $control = json_decode(file_get_contents($control_file), true);
        if ($control && isset($control['action']) && $control['action'] === 'stop') {
            $status = proc_get_status($process);
            if ($status['running']) {
                $pid = $status['pid'];
                if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                    // Windows: kill process and sub-processes (tree-kill)
                    exec("taskkill /F /T /PID " . $pid);
                } else {
                    // Unix: kill process group or process
                    exec("kill -9 -" . $pid);
                }
            }
            break;
        }
    }

    // 5. Check if process is still running
    $proc_status = proc_get_status($process);
    if (!$proc_status['running']) {
        $running = false;
        $exit_code = $proc_status['exitcode'];
        // Read remaining output
        while (($stdout = fread($pipes[1], 8192)) !== '' && $stdout !== false) {
            file_put_contents($output_file, $stdout, FILE_APPEND);
        }
        while (($stderr = fread($pipes[2], 8192)) !== '' && $stderr !== false) {
            file_put_contents($output_file, $stderr, FILE_APPEND);
        }
        file_put_contents($output_file, "\n[Process exited with code $exit_code]\n", FILE_APPEND);
        @file_put_contents($status_file, json_encode([
            'status' => 'stopped',
            'exit_code' => $exit_code
        ]));
    }

    usleep(50000); // 50ms sleep
}

// Clean up
fclose($pipes[0]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($process);
?>
