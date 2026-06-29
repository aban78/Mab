<?php
/**
 * Standalone background runner for Flutter APK build.
 * Runs in CLI mode.
 */
set_time_limit(0);
ignore_user_abort(true);

$config_file = __DIR__ . '/.apk_build_config.json';
$output_file = __DIR__ . '/.apk_build_output.txt';
$control_file = __DIR__ . '/.apk_build_control.json';
$status_file = __DIR__ . '/.apk_build_status.json';

if (!file_exists($config_file)) {
    exit("No build configuration file found.");
}

$config = json_decode(file_get_contents($config_file), true);
if (!$config) {
    exit("Invalid build configuration.");
}

require_once __DIR__ . '/config.php';

// Clear files and start
@file_put_contents($output_file, "Preparing build...\n");

$mode = isset($config['mode']) ? $config['mode'] : 'release'; // release, debug, profile
$target = isset($config['target']) ? $config['target'] : 'fat'; // fat, split, arm64-v8a, armeabi-v7a
$obfuscate = !empty($config['obfuscate']);
$clean = !empty($config['clean']);
$workspace_dir = isset($config['workspace_dir']) ? $config['workspace_dir'] : dirname(__DIR__);
$frontend_dir = get_flutter_project_dir($workspace_dir);

if (defined('BUILD_METHOD') && BUILD_METHOD === 'github') {
    run_github_build($mode, $target, $frontend_dir, $output_file, $status_file, $control_file);
    exit();
}

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

// Helper to resolve the actual Flutter project folder within the workspace
function get_flutter_project_dir($workspace_dir) {
    if (empty($workspace_dir)) {
        return $workspace_dir;
    }
    // 1. Check if the workspace itself contains pubspec.yaml
    if (file_exists($workspace_dir . DIRECTORY_SEPARATOR . 'pubspec.yaml')) {
        return $workspace_dir;
    }
    
    // 2. Check if a 'frontend' subfolder contains pubspec.yaml
    if (file_exists($workspace_dir . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'pubspec.yaml')) {
        return $workspace_dir . DIRECTORY_SEPARATOR . 'frontend';
    }
    
    // 3. Search for any subfolder (1 level deep) that contains pubspec.yaml
    if (is_dir($workspace_dir)) {
        $files = scandir($workspace_dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $sub_dir = $workspace_dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($sub_dir)) {
                if (file_exists($sub_dir . DIRECTORY_SEPARATOR . 'pubspec.yaml')) {
                    return $sub_dir;
                }
            }
        }
    }
    
    return $workspace_dir;
}

// Verify flutter project exists
$frontend_dir = get_flutter_project_dir($workspace_dir);
if (!file_exists($frontend_dir . DIRECTORY_SEPARATOR . 'pubspec.yaml')) {
    @file_put_contents($output_file, "Error: pubspec.yaml not found in workspace at " . $frontend_dir . "\nPlease ask the AI assistant to 'create a simple app' first to initialize a Flutter project.");
    @file_put_contents($status_file, json_encode(['status' => 'error', 'error' => 'pubspec.yaml not found']));
    exit();
}

// Clear files and start
@file_put_contents($output_file, "Preparing build...\n");
@file_put_contents($control_file, json_encode(['action' => 'run']));
@file_put_contents($status_file, json_encode([
    'status' => 'running',
    'pid' => getmypid(),
    'start_time' => time(),
    'current_step' => 'initializing'
]));

$is_windows = (strncasecmp(PHP_OS, 'WIN', 3) === 0);

// Choose flutter executable. If a custom path was configured, we can append it.
// We also use cmd.exe context on Windows to ensure batch files (.bat) run properly.
$flutter_bin = find_flutter_path();
if ($is_windows) {
    if (!empty($flutter_bin) && is_file($flutter_bin . DIRECTORY_SEPARATOR . 'flutter.bat')) {
        $flutter_exe = 'cmd.exe /c "' . $flutter_bin . DIRECTORY_SEPARATOR . 'flutter.bat"';
    } else {
        $flutter_exe = "cmd.exe /c flutter.bat";
    }
} else {
    if (!empty($flutter_bin) && is_file($flutter_bin . DIRECTORY_SEPARATOR . 'flutter')) {
        $flutter_exe = '"' . $flutter_bin . DIRECTORY_SEPARATOR . 'flutter"';
    } else {
        $flutter_exe = "flutter";
    }
}

// Build list of commands to run
$commands = [];

if ($clean) {
    $commands[] = [
        'name' => 'clean',
        'cmd' => $flutter_exe . " clean"
    ];
}

$build_cmd = $flutter_exe . " build apk --" . $mode;
if ($target === 'split') {
    $build_cmd .= " --split-per-abi";
} elseif ($target === 'arm64-v8a') {
    $build_cmd .= " --target-platform android-arm64";
} elseif ($target === 'armeabi-v7a') {
    $build_cmd .= " --target-platform android-arm";
}

if ($obfuscate) {
    // Obfuscate code requires split-debug-info directory
    $build_cmd .= " --obfuscate --split-debug-info=build/app/outputs/symbols";
}

$commands[] = [
    'name' => 'build',
    'cmd' => $build_cmd
];

$descriptorspec = array(
    0 => array("pipe", "r"), // stdin
    1 => array("pipe", "w"), // stdout
    2 => array("pipe", "w")  // stderr
);

// Execute commands sequentially
foreach ($commands as $index => $step) {
    $step_name = $step['name'];
    $cmd_string = $step['cmd'];
    
    @file_put_contents($status_file, json_encode([
        'status' => 'running',
        'pid' => getmypid(),
        'start_time' => time(),
        'current_step' => $step_name
    ]));
    
    file_put_contents($output_file, "\n[Step: $step_name]\n> " . $cmd_string . "\n", FILE_APPEND);

    // Inject mirror variables for Flutter & Pub to speed up and fix network timeouts
    $env = getenv();
    if (!is_array($env)) {
        $env = array();
    }
    $env['FLUTTER_STORAGE_BASE_URL'] = 'https://storage.flutter-io.cn';
    $env['PUB_HOSTED_URL'] = 'https://pub.flutter-io.cn';
    foreach ($_SERVER as $k => $v) {
        if (is_string($v) && !isset($env[$k])) {
            $env[$k] = $v;
        }
    }
    
    $is_sh = isset($env['SHELL']) || isset($env['BASH']) || isset($env['TERM']) || (strpos($cmd_string, 'sh ') !== false) || (strpos($cmd_string, 'bash ') !== false);
    
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

    $process = proc_open($cmd_string, $descriptorspec, $pipes, $frontend_dir, $env);
    
    if (!is_resource($process)) {
        file_put_contents($output_file, "Error: Failed to start process: " . $cmd_string . "\n", FILE_APPEND);
        @file_put_contents($status_file, json_encode(['status' => 'error', 'error' => "Failed to start $step_name"]));
        exit();
    }
    
    // Set non-blocking
    stream_set_blocking($pipes[1], 0);
    stream_set_blocking($pipes[2], 0);
    fclose($pipes[0]); // We don't need stdin
    
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
        
        // 3. Check for cancellation
        if (file_exists($control_file)) {
            $control = json_decode(file_get_contents($control_file), true);
            if ($control && isset($control['action']) && $control['action'] === 'stop') {
                file_put_contents($output_file, "\n[Build cancelled by user]\n", FILE_APPEND);
                
                $status = proc_get_status($process);
                if ($status['running']) {
                    $pid = $status['pid'];
                    if ($is_windows) {
                        exec("taskkill /F /T /PID " . $pid);
                    } else {
                        exec("kill -9 -" . $pid);
                    }
                }
                
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                
                @file_put_contents($status_file, json_encode(['status' => 'stopped']));
                exit();
            }
        }
        
        // 4. Check if finished
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
            
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            
            if ($exit_code !== 0) {
                file_put_contents($output_file, "\n[Step $step_name failed with exit code $exit_code]\n", FILE_APPEND);
                @file_put_contents($status_file, json_encode([
                    'status' => 'error',
                    'exit_code' => $exit_code,
                    'failed_step' => $step_name
                ]));
                exit();
            }
        }
        
        usleep(100000); // 100ms sleep
    }
}

file_put_contents($output_file, "\n[Build finished successfully!]\n", FILE_APPEND);

// Scan for output APK files
$apk_dir = $frontend_dir . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'outputs' . DIRECTORY_SEPARATOR . 'flutter-apk';
$found_apks = [];
if (is_dir($apk_dir)) {
    $files = scandir($apk_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'apk') {
            $found_apks[] = $file;
        }
    }
}

@file_put_contents($status_file, json_encode([
    'status' => 'success',
    'apks' => $found_apks,
    'finish_time' => time()
]));

// --- GITHUB ACTIONS BUILD IMPLEMENTATION ---

function run_github_build($mode, $target, $frontend_dir, $output_file, $status_file, $control_file) {
    $uploads_dir = __DIR__ . '/uploads';
    if (!is_dir($uploads_dir)) {
        @mkdir($uploads_dir, 0755, true);
    }
    
    $build_id = 'build_' . time() . '_' . bin2hex(random_bytes(4));
    $zip_filename = $build_id . '.zip';
    $zip_path = $uploads_dir . '/' . $zip_filename;
    
    file_put_contents($output_file, "Creating source code archive (zip)...\n", FILE_APPEND);
    
    if (!create_source_zip($frontend_dir, $zip_path)) {
        file_put_contents($output_file, "Error: Failed to create source zip archive. Make sure ZipArchive is enabled.\n", FILE_APPEND);
        @file_put_contents($status_file, json_encode(['status' => 'error', 'error' => 'Failed to create zip']));
        exit();
    }
    
    global $config;
    $base_url = isset($config['shared_hosting_url']) ? $config['shared_hosting_url'] : SHARED_HOSTING_URL;
    $base_url = rtrim($base_url, '/') . '/';
    $zip_url = $base_url . 'uploads/' . $zip_filename;
    
    file_put_contents($output_file, "Triggering GitHub Actions build...\n", FILE_APPEND);
    
    $repo = GITHUB_REPO;
    $token = GITHUB_TOKEN;
    $workflow = GITHUB_WORKFLOW;
    
    if (empty($token) || $token === 'YOUR_GITHUB_PERSONAL_ACCESS_TOKEN' || empty($repo) || $repo === 'YOUR_GITHUB_USERNAME/YOUR_REPO_NAME') {
        file_put_contents($output_file, "Error: GitHub Repo or Personal Access Token is not configured in config.php.\nPlease set GITHUB_REPO and GITHUB_TOKEN.\n", FILE_APPEND);
        @unlink($zip_path);
        @file_put_contents($status_file, json_encode(['status' => 'error', 'error' => 'GitHub credentials not configured']));
        exit();
    }
    
    $workspace_name = basename($workspace_dir);
    $clean_name = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower($workspace_name));
    if (empty($clean_name)) {
        $clean_name = "app_" . time();
    }
    $package_name = "com.example." . $clean_name;
    $app_name = $workspace_name; // E.g. "Animaton"

    $dispatch_url = "https://api.github.com/repos/$repo/actions/workflows/$workflow/dispatches";
    $dispatch_data = array(
        'ref' => 'main',
        'inputs' => array(
            'zip_url' => $zip_url,
            'build_mode' => $mode,
            'target' => $target,
            'build_id' => $build_id,
            'package_name' => $package_name,
            'app_name' => $app_name
        )
    );
    
    @file_put_contents($status_file, json_encode([
        'status' => 'running',
        'pid' => getmypid(),
        'start_time' => time(),
        'current_step' => 'triggering_github'
    ]));
    
    $res = github_api_request('POST', $dispatch_url, $token, $dispatch_data);
    
    if ($res['code'] !== 204) {
        file_put_contents($output_file, "Error triggering GitHub Action. HTTP Code: " . $res['code'] . "\nResponse: " . json_encode($res['body']) . "\n", FILE_APPEND);
        @unlink($zip_path);
        @file_put_contents($status_file, json_encode(['status' => 'error', 'error' => 'Failed to trigger GitHub Action']));
        exit();
    }
    
    file_put_contents($output_file, "GitHub Action triggered successfully. Waiting for runner to start...\n", FILE_APPEND);
    
    // Find the run ID
    $run_id = null;
    $attempts = 0;
    $max_attempts = 12; // Try for 60 seconds
    
    while (!$run_id && $attempts < $max_attempts) {
        sleep(5);
        $attempts++;
        
        $runs_url = "https://api.github.com/repos/$repo/actions/workflows/$workflow/runs?per_page=5";
        $runs_res = github_api_request('GET', $runs_url, $token);
        
        if ($runs_res['code'] === 200 && isset($runs_res['body']['workflow_runs'])) {
            foreach ($runs_res['body']['workflow_runs'] as $run) {
                $created_time = strtotime($run['created_at']);
                if (time() - $created_time < 180) { // Created in last 3 minutes
                    $run_id = $run['id'];
                    break;
                }
            }
        }
    }
    
    if (!$run_id) {
        file_put_contents($output_file, "Error: Could not find the triggered GitHub Actions run. Please check your workflow file and repository.\n", FILE_APPEND);
        @unlink($zip_path);
        @file_put_contents($status_file, json_encode(['status' => 'error', 'error' => 'Workflow run not found']));
        exit();
    }
    
    file_put_contents($output_file, "Found workflow run ID: $run_id. Monitoring build...\n", FILE_APPEND);
    
    @file_put_contents($status_file, json_encode([
        'status' => 'running',
        'pid' => getmypid(),
        'start_time' => time(),
        'current_step' => 'github_building',
        'run_id' => $run_id
    ]));
    
    $status = 'queued';
    $conclusion = null;
    $start_time = time();
    
    while (true) {
        // Check for cancellation
        if (file_exists($control_file)) {
            $control = json_decode(file_get_contents($control_file), true);
            if ($control && isset($control['action']) && $control['action'] === 'stop') {
                file_put_contents($output_file, "\n[Cancelling GitHub Actions build...]\n", FILE_APPEND);
                
                $cancel_url = "https://api.github.com/repos/$repo/actions/runs/$run_id/cancel";
                github_api_request('POST', $cancel_url, $token);
                
                @unlink($zip_path);
                @file_put_contents($status_file, json_encode(['status' => 'stopped']));
                exit();
            }
        }
        
        sleep(8);
        
        $run_url = "https://api.github.com/repos/$repo/actions/runs/$run_id";
        $run_res = github_api_request('GET', $run_url, $token);
        
        if ($run_res['code'] === 200) {
            $run_data = $run_res['body'];
            $status = $run_data['status'];
            $conclusion = $run_data['conclusion'];
            
            $elapsed = time() - $start_time;
            $elapsed_str = sprintf("%02d:%02d", floor($elapsed / 60), $elapsed % 60);
            
            file_put_contents($output_file, "[$elapsed_str] Build Status: " . ucfirst(str_replace('_', ' ', $status)) . "...\n", FILE_APPEND);
            
            if ($status === 'completed') {
                break;
            }
        }
    }
    
    // Clean up the uploaded source zip file immediately after compilation starts/finishes
    @unlink($zip_path);
    
    if ($conclusion !== 'success') {
        file_put_contents($output_file, "\n[GitHub Actions build failed with conclusion: $conclusion]\n", FILE_APPEND);
        @file_put_contents($status_file, json_encode([
            'status' => 'error',
            'error' => "GitHub build failed: $conclusion"
        ]));
        exit();
    }
    
    file_put_contents($output_file, "Build successful! Fetching compilation artifact...\n", FILE_APPEND);
    
    // Find the artifact
    $artifacts_url = "https://api.github.com/repos/$repo/actions/runs/$run_id/artifacts";
    $artifacts_res = github_api_request('GET', $artifacts_url, $token);
    
    $artifact_id = null;
    if ($artifacts_res['code'] === 200 && isset($artifacts_res['body']['artifacts'])) {
        foreach ($artifacts_res['body']['artifacts'] as $art) {
            if ($art['name'] === $build_id || $art['name'] === 'flutter-apk') {
                $artifact_id = $art['id'];
                break;
            }
        }
        // Fallback to first artifact if name doesn't match
        if (!$artifact_id && !empty($artifacts_res['body']['artifacts'])) {
            $artifact_id = $artifacts_res['body']['artifacts'][0]['id'];
        }
    }
    
    if (!$artifact_id) {
        file_put_contents($output_file, "Error: Could not find build artifact on GitHub.\n", FILE_APPEND);
        @file_put_contents($status_file, json_encode(['status' => 'error', 'error' => 'Artifact not found']));
        exit();
    }
    
    file_put_contents($output_file, "Downloading APK artifact from GitHub...\n", FILE_APPEND);
    
    $artifact_zip_path = $uploads_dir . '/artifact_' . $build_id . '.zip';
    $fp = fopen($artifact_zip_path, 'w+');
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.github.com/repos/$repo/actions/artifacts/$artifact_id/zip");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $token,
        'User-Agent: PHP-APK-Builder'
    ));
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    
    file_put_contents($output_file, "Extracting APK...\n", FILE_APPEND);
    
    $zip = new ZipArchive();
    if ($zip->open($artifact_zip_path) === true) {
        $output_apk_dir = $frontend_dir . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'outputs' . DIRECTORY_SEPARATOR . 'flutter-apk';
        if (!is_dir($output_apk_dir)) {
            @mkdir($output_apk_dir, 0755, true);
        }
        
        $found_apks = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (pathinfo($filename, PATHINFO_EXTENSION) === 'apk') {
                $target_name = basename($filename);
                // Extract file
                copy("zip://".$artifact_zip_path."#".$filename, $output_apk_dir . DIRECTORY_SEPARATOR . $target_name);
                $found_apks[] = $target_name;
            }
        }
        $zip->close();
        @unlink($artifact_zip_path);
        
        file_put_contents($output_file, "\n[Build finished successfully! APK is ready.]\n", FILE_APPEND);
        @file_put_contents($status_file, json_encode([
            'status' => 'success',
            'apks' => $found_apks,
            'finish_time' => time()
        ]));
    } else {
        file_put_contents($output_file, "Error: Failed to extract downloaded APK artifact.\n", FILE_APPEND);
        @unlink($artifact_zip_path);
        @file_put_contents($status_file, json_encode(['status' => 'error', 'error' => 'Failed to extract APK']));
    }
}

function create_source_zip($frontend_dir, $zip_path) {
    if (!class_exists('ZipArchive')) {
        return false;
    }
    
    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }
    
    $lib_dir = $frontend_dir . DIRECTORY_SEPARATOR . 'lib';
    $pubspec = $frontend_dir . DIRECTORY_SEPARATOR . 'pubspec.yaml';
    
    if (file_exists($pubspec)) {
        $zip->addFile($pubspec, 'pubspec.yaml');
    }
    
    if (is_dir($lib_dir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($lib_dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = 'lib/' . substr($filePath, strlen($lib_dir) + 1);
                $relativePath = str_replace('\\', '/', $relativePath);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
    
    // Also check for assets
    $assets_dir = $frontend_dir . DIRECTORY_SEPARATOR . 'assets';
    if (is_dir($assets_dir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($assets_dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = 'assets/' . substr($filePath, strlen($assets_dir) + 1);
                $relativePath = str_replace('\\', '/', $relativePath);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
    
    $zip->close();
    return true;
}

function github_api_request($method, $url, $token, $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = array(
        'Accept: application/vnd.github+json',
        'Authorization: Bearer ' . $token,
        'User-Agent: PHP-APK-Builder',
        'X-GitHub-Api-Version: 2022-11-28'
    );
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $headers[] = 'Content-Type: application/json';
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return array('code' => $http_code, 'body' => json_decode($response, true), 'raw' => $response);
}
?>
