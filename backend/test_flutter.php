<?php
require_once __DIR__ . '/api.php';

// Prepare a clean temp workspace directory for testing
$test_dir = __DIR__ . DIRECTORY_SEPARATOR . 'test_workspace';
if (!is_dir($test_dir)) {
    mkdir($test_dir, 0755, true);
}

$flutter_bin = find_flutter_path();
if (empty($flutter_bin)) {
    die("Flutter path not found!\n");
}

$flutter_win_exe = $flutter_bin . DIRECTORY_SEPARATOR . 'flutter.bat';

// We run the command inside $test_dir
$cmd = '"' . $flutter_win_exe . '" create --platforms=android .';
echo "Executing: $cmd in $test_dir\n";

$descriptorspec = array(
    0 => array("pipe", "r"),
    1 => array("pipe", "w"),
    2 => array("pipe", "w")
);

$env = getenv();
if (!is_array($env)) {
    $env = array();
}
foreach ($_SERVER as $k => $v) {
    if (is_string($v) && !isset($env[$k])) {
        $env[$k] = $v;
    }
}

// Add path
$path_key = 'PATH';
foreach ($env as $k => $v) {
    if (strcasecmp($k, 'path') === 0) {
        $path_key = $k;
        break;
    }
}
$path_val = isset($env[$path_key]) ? $env[$path_key] : '';
$paths_win = explode(';', $path_val);
if (!in_array($flutter_bin, $paths_win)) {
    $paths_win[] = $flutter_bin;
}
$normalized_path = implode(';', $paths_win);
$env['PATH'] = $normalized_path;
$env['Path'] = $normalized_path;
$env['path'] = $normalized_path;

$process = proc_open($cmd, $descriptorspec, $pipes, $test_dir, $env);

if (is_resource($process)) {
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exit_code = proc_close($process);
    
    echo "Exit Code: $exit_code\n";
    echo "Stdout:\n$stdout\n";
    echo "Stderr:\n$stderr\n";
} else {
    echo "Failed to start process!\n";
}

// Cleanup $test_dir
function delTree($dir) {
    if (!is_dir($dir)) return false;
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}
delTree($test_dir);
?>
