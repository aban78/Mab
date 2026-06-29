<?php
/**
 * Internal Cursor - Backend API Router and Controller
 * Compatible with PHP 7.4
 */

require_once __DIR__ . '/config.php';

// Parse incoming JSON input if present
$json_input = json_decode(file_get_contents('php://input'), true);
$input = is_array($json_input) ? $json_input : $_POST;

$action = isset($input['action']) ? $input['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// Helper function to resolve and validate paths to prevent directory traversal
function get_safe_path($relative_path) {
    if (empty($relative_path)) {
        return WORKSPACE_DIR;
    }
    
    // Normalize separators
    $relative_path = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $relative_path);
    $relative_path = ltrim($relative_path, DIRECTORY_SEPARATOR);
    
    $full_path = WORKSPACE_DIR . DIRECTORY_SEPARATOR . $relative_path;
    $real_path = realpath($full_path);
    
    $is_windows = (strncasecmp(PHP_OS, 'WIN', 3) === 0);
    
    if ($real_path === false) {
        // Path does not exist yet (creating a new file/directory)
        $parent = dirname($full_path);
        $real_parent = realpath($parent);
        if ($real_parent === false) {
            return false;
        }
        if ($is_windows) {
            if (stripos($real_parent, WORKSPACE_DIR) !== 0) {
                return false;
            }
        } else {
            if (strpos($real_parent, WORKSPACE_DIR) !== 0) {
                return false;
            }
        }
        $filename = basename($full_path);
        return $real_parent . DIRECTORY_SEPARATOR . $filename;
    }
    
    // Check if the real path is inside the workspace directory
    if ($is_windows) {
        if (stripos($real_path, WORKSPACE_DIR) !== 0) {
            return false;
        }
    } else {
        if (strpos($real_path, WORKSPACE_DIR) !== 0) {
            return false;
        }
    }
    
    return $real_path;
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

// Helper for recursive file listing
function list_dir_recursive($dir, $base_dir) {
    $result = array();
    if (!is_dir($dir)) {
        return $result;
    }
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        // Skip common ignore directories
        if (in_array($file, array('.git', 'node_modules', 'build', '.dart_tool', '.idea', '.vscode', 'vendor'))) {
            continue;
        }
        
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        $rel_path = ltrim(substr($path, strlen($base_dir)), DIRECTORY_SEPARATOR);
        $is_dir = is_dir($path);
        
        $result[] = array(
            'name' => $file,
            'path' => str_replace('\\', '/', $rel_path),
            'is_dir' => $is_dir,
            'children' => $is_dir ? list_dir_recursive($path, $base_dir) : null
        );
    }
    
    // Sort: directories first, then files, both alphabetically
    usort($result, function($a, $b) {
        if ($a['is_dir'] && !$b['is_dir']) return -1;
        if (!$a['is_dir'] && $b['is_dir']) return 1;
        return strcasecmp($a['name'], $b['name']);
    });
    
    return $result;
}

/**
 * Sends a POST request to Google Gemini API (supporting contents, tools, and systemInstruction)
 */
function call_gemini_api($contents, $tools, $api_key, $system_prompt = '') {
    // Ensure that empty 'args' in functionCall and 'response' in functionResponse are encoded as objects, not lists
    if (is_array($contents)) {
        foreach ($contents as &$content) {
            if (isset($content['parts']) && is_array($content['parts'])) {
                foreach ($content['parts'] as &$part) {
                    if (isset($part['functionCall'])) {
                        if (isset($part['functionCall']['args'])) {
                            $part['functionCall']['args'] = (object)$part['functionCall']['args'];
                        } else {
                            $part['functionCall']['args'] = new stdClass();
                        }
                    }
                    if (isset($part['functionResponse'])) {
                        if (isset($part['functionResponse']['response'])) {
                            $part['functionResponse']['response'] = (object)$part['functionResponse']['response'];
                        } else {
                            $part['functionResponse']['response'] = new stdClass();
                        }
                    }
                }
                unset($part);
            }
        }
        unset($content);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $url = GEMINI_API_URL . '?key=' . urlencode($api_key);
    $post_data = array('contents' => $contents);
    if (!empty($tools)) {
        $post_data['tools'] = $tools;
    }
    if (!empty($system_prompt)) {
        $post_data['systemInstruction'] = array(
            'parts' => array(
                array('text' => $system_prompt)
            )
        );
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return array(
        'code' => $http_code,
        'body' => json_decode($response, true),
        'raw' => $response
    );
}

// Handle APIs
header('Content-Type: application/json');

switch ($action) {
    case 'list_workspaces':
        $workspaces_root = __DIR__ . DIRECTORY_SEPARATOR . 'workspaces';
        $result = array();
        if (is_dir($workspaces_root)) {
            $folders = scandir($workspaces_root);
            foreach ($folders as $folder) {
                if ($folder === '.' || $folder === '..') {
                    continue;
                }
                $path = $workspaces_root . DIRECTORY_SEPARATOR . $folder;
                if (is_dir($path)) {
                    $real_path = realpath($path);
                    $result[] = array(
                        'name' => $folder,
                        'path' => str_replace('\\', '/', $real_path)
                    );
                }
            }
        }
        echo json_encode(array('status' => 'success', 'workspaces' => $result));
        break;

    case 'list_files':
        $safe_dir = get_safe_path('');
        if ($safe_dir === false) {
            echo json_encode(array('status' => 'error', 'message' => 'Invalid workspace root'));
            exit;
        }
        $files = list_dir_recursive($safe_dir, WORKSPACE_DIR);
        echo json_encode(array('status' => 'success', 'files' => $files));
        break;

    case 'read_file':
        $rel_path = isset($input['path']) ? $input['path'] : '';
        $safe_path = get_safe_path($rel_path);
        if ($safe_path === false || !is_file($safe_path)) {
            echo json_encode(array('status' => 'error', 'message' => 'File not found or access denied'));
            exit;
        }
        $content = file_get_contents($safe_path);
        echo json_encode(array(
            'status' => 'success',
            'path' => str_replace('\\', '/', $rel_path),
            'content' => $content
        ));
        break;

    case 'write_file':
        $rel_path = isset($input['path']) ? $input['path'] : '';
        $content = isset($input['content']) ? $input['content'] : '';
        $safe_path = get_safe_path($rel_path);
        if ($safe_path === false) {
            echo json_encode(array('status' => 'error', 'message' => 'Invalid file path or access denied'));
            exit;
        }
        
        // Ensure folder directory exists
        $parent_dir = dirname($safe_path);
        if (!is_dir($parent_dir)) {
            mkdir($parent_dir, 0755, true);
        }
        
        if (file_put_contents($safe_path, $content) !== false) {
            echo json_encode(array('status' => 'success', 'message' => 'File saved successfully'));
        } else {
            echo json_encode(array('status' => 'error', 'message' => 'Failed to write file'));
        }
        break;

    case 'create_folder':
        $rel_path = isset($input['path']) ? $input['path'] : '';
        $safe_path = get_safe_path($rel_path);
        if ($safe_path === false) {
            echo json_encode(array('status' => 'error', 'message' => 'Invalid folder path or access denied'));
            exit;
        }
        if (is_dir($safe_path)) {
            echo json_encode(array('status' => 'error', 'message' => 'Folder already exists'));
            exit;
        }
        if (mkdir($safe_path, 0755, true)) {
            echo json_encode(array('status' => 'success', 'message' => 'Folder created successfully'));
        } else {
            echo json_encode(array('status' => 'error', 'message' => 'Failed to create folder'));
        }
        break;

    case 'chat':
        $model = isset($input['model']) ? $input['model'] : 'gemini'; // 'gemini', 'claude', 'chatgpt'
        $messages = isset($input['messages']) ? $input['messages'] : array();
        
        // Dynamic Key checking (falls back to config if request header or post keys are not set)
        $gemini_key = cursor_get_header('X-Gemini-Key');
        if (empty($gemini_key)) {
            $gemini_key = isset($input['gemini_key']) ? $input['gemini_key'] : (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
        }
        
        $claude_key = cursor_get_header('X-Claude-Key');
        if (empty($claude_key)) {
            $claude_key = isset($input['claude_key']) ? $input['claude_key'] : (defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : '');
        }
        
        $chatgpt_key = cursor_get_header('X-ChatGPT-Key');
        if (empty($chatgpt_key)) {
            $chatgpt_key = isset($input['chatgpt_key']) ? $input['chatgpt_key'] : (defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '');
        }

        // Normalize messages to system and user format
        if (empty($messages)) {
            echo json_encode(array('status' => 'error', 'message' => 'No messages/prompt provided'));
            exit;
        }

        if ($model === 'gemini') {
            if (empty($gemini_key)) {
                echo json_encode(array('status' => 'error', 'message' => 'Gemini API Key missing. Update in Settings.'));
                exit;
            }
            
            // Format for Gemini API (contents array)
            $contents = array();
            foreach ($messages as $msg) {
                if ($msg['role'] === 'system') {
                    continue; // Skip system messages as they are set via systemInstruction
                }
                $role = ($msg['role'] === 'assistant' || $msg['role'] === 'model') ? 'model' : 'user';
                $contents[] = array(
                    'role' => $role,
                    'parts' => array(
                        array('text' => $msg['content'])
                    )
                );
            }
            
            // Define tools schema for Gemini
            $gemini_tools = array(
                array(
                    'function_declarations' => array(
                        array(
                            'name' => 'list_files',
                            'description' => 'Lists all files and folders recursively in the active workspace.'
                        ),
                        array(
                            'name' => 'read_file',
                            'description' => 'Reads the entire content of a file in the workspace.',
                            'parameters' => array(
                                'type' => 'OBJECT',
                                'properties' => array(
                                    'path' => array(
                                        'type' => 'STRING',
                                        'description' => 'Relative path to the file from the workspace root.'
                                    )
                                ),
                                'required' => array('path')
                            )
                        ),
                        array(
                            'name' => 'write_file',
                            'description' => 'Creates a new file or overwrites an existing file with content.',
                            'parameters' => array(
                                'type' => 'OBJECT',
                                'properties' => array(
                                    'path' => array(
                                        'type' => 'STRING',
                                        'description' => 'Relative path to the file from the workspace root.'
                                    ),
                                    'content' => array(
                                        'type' => 'STRING',
                                        'description' => 'The full content to write to the file.'
                                    )
                                ),
                                'required' => array('path', 'content')
                            )
                        ),
                        array(
                            'name' => 'create_folder',
                            'description' => 'Creates a new folder in the workspace.',
                            'parameters' => array(
                                'type' => 'OBJECT',
                                'properties' => array(
                                    'path' => array(
                                        'type' => 'STRING',
                                        'description' => 'Relative path to the folder from the workspace root.'
                                    )
                                ),
                                'required' => array('path')
                            )
                        ),
                        array(
                            'name' => 'run_command',
                            'description' => 'Runs a command-line terminal command in the workspace root directory and returns the stdout and stderr output (e.g. running php unit tests, checking syntax with php -l, running build steps, etc.).',
                            'parameters' => array(
                                'type' => 'OBJECT',
                                'properties' => array(
                                    'command' => array(
                                        'type' => 'STRING',
                                        'description' => 'The command line string to execute.'
                                    )
                                ),
                                'required' => array('command')
                            )
                        )
                    )
                )
            );
            
            $system_prompt = "You are an autonomous AI coding agent. You can read/write files and run terminal commands to modify the workspace. The server you are running on might NOT have the `flutter` SDK installed locally. Do NOT attempt to run any `flutter` commands (such as `flutter create`, `flutter build`, or `flutter pub get`) as they will fail. Instead, check the workspace: there is already a pre-initialized Flutter project template in the workspace (usually in a folder named `frontend` or directly in the root, containing a `pubspec.yaml`). Your job is simply to write or modify the Dart code in `lib/main.dart` (e.g., `frontend/lib/main.dart`) and update dependencies in `pubspec.yaml` using the `write_file` tool. Do NOT try to compile the app. The backend will handle compiling the APK in the cloud via GitHub Actions. Focus entirely on writing clean, functional Flutter/Dart code in the files.";
            
            $max_turns = 10;
            $final_reply = '';
            $error_message = '';
            
            for ($turn = 0; $turn < $max_turns; $turn++) {
                $res = call_gemini_api($contents, $gemini_tools, $gemini_key, $system_prompt);
                
                if ($res['code'] !== 200) {
                    $error_message = 'Gemini API Error (HTTP ' . $res['code'] . '): ' . (isset($res['body']['error']['message']) ? $res['body']['error']['message'] : $res['raw']);
                    break;
                }
                
                $candidates = isset($res['body']['candidates']) ? $res['body']['candidates'] : array();
                if (empty($candidates)) {
                    $error_message = 'Invalid response from Gemini API (no candidates)';
                    break;
                }
                
                $candidate = $candidates[0];
                $content = isset($candidate['content']) ? $candidate['content'] : array();
                $parts = isset($content['parts']) ? $content['parts'] : array();
                
                if (empty($parts)) {
                    $error_message = 'Invalid response from Gemini API (no parts)';
                    break;
                }
                
                // Add model's turn to conversation history
                $contents[] = $content;
                
                $function_calls = array();
                foreach ($parts as $part) {
                    if (isset($part['functionCall'])) {
                        $function_calls[] = $part['functionCall'];
                    }
                }
                
                if (!empty($function_calls)) {
                    $function_responses = array();
                    
                    foreach ($function_calls as $fc) {
                        $name = $fc['name'];
                        $args = isset($fc['args']) ? $fc['args'] : array();
                        
                        $tool_output = array();
                        
                        if ($name === 'list_files') {
                            $safe_dir = get_safe_path('');
                            if ($safe_dir === false) {
                                $tool_output = array('error' => 'Invalid workspace root');
                            } else {
                                $tool_output = array('files' => list_dir_recursive($safe_dir, WORKSPACE_DIR));
                            }
                        } elseif ($name === 'read_file') {
                            $path = isset($args['path']) ? $args['path'] : '';
                            $safe_path = get_safe_path($path);
                            if ($safe_path === false || !is_file($safe_path)) {
                                $tool_output = array('error' => 'File not found or access denied');
                            } else {
                                $tool_output = array('content' => file_get_contents($safe_path));
                            }
                        } elseif ($name === 'write_file') {
                            $path = isset($args['path']) ? $args['path'] : '';
                            $content_str = isset($args['content']) ? $args['content'] : '';
                            $safe_path = get_safe_path($path);
                            if ($safe_path === false) {
                                $tool_output = array('error' => 'Invalid path or access denied');
                            } else {
                                $parent_dir = dirname($safe_path);
                                if (!is_dir($parent_dir)) {
                                    mkdir($parent_dir, 0755, true);
                                }
                                if (file_put_contents($safe_path, $content_str) !== false) {
                                    $tool_output = array('success' => true, 'message' => 'File written successfully');
                                } else {
                                    $tool_output = array('error' => 'Failed to write file');
                                }
                            }
                        } elseif ($name === 'create_folder') {
                            $path = isset($args['path']) ? $args['path'] : '';
                            $safe_path = get_safe_path($path);
                            if ($safe_path === false) {
                                $tool_output = array('error' => 'Invalid path or access denied');
                            } elseif (is_dir($safe_path)) {
                                $tool_output = array('error' => 'Folder already exists');
                            } else {
                                if (mkdir($safe_path, 0755, true)) {
                                    $tool_output = array('success' => true, 'message' => 'Folder created successfully');
                                } else {
                                    $tool_output = array('error' => 'Failed to create folder');
                                }
                            }
                        } elseif ($name === 'run_command') {
                            $cmd = isset($args['command']) ? $args['command'] : '';
                            
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
                            
                            $is_sh = isset($env['SHELL']) || isset($env['BASH']) || isset($env['TERM']) || (strpos($cmd, 'sh ') !== false) || (strpos($cmd, 'bash ') !== false);
                            
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
                                    $cmd = preg_replace('/\bflutter\b/', '"' . $absolute_flutter . '"', $cmd);
                                }
                            }
                            
                            $log_file = __DIR__ . '/.api_debug.log';
                            $log_data = "--- " . date('Y-m-d H:i:s') . " ---\n";
                            $log_data .= "Original Command: " . $cmd . "\n";
                            
                            $is_sh = isset($env['SHELL']) || isset($env['BASH']) || isset($env['TERM']) || (strpos($cmd, 'sh ') !== false) || (strpos($cmd, 'bash ') !== false);
                            
                            // Find Flutter path dynamically and replace command / update environment
                            $flutter_bin = find_flutter_path();
                            $log_data .= "Flutter Bin: " . $flutter_bin . "\n";
                            if (!empty($flutter_bin)) {
                                $flutter_win_exe = $flutter_bin . DIRECTORY_SEPARATOR . 'flutter.bat';
                                $flutter_sh_exe = $flutter_bin . DIRECTORY_SEPARATOR . 'flutter';
                                if (is_file($flutter_win_exe)) {
                                    $absolute_flutter = $is_sh ? $flutter_sh_exe : $flutter_win_exe;
                                    if ($is_sh) {
                                        $absolute_flutter = str_replace('\\', '/', $absolute_flutter);
                                    }
                                    $cmd = preg_replace('/\bflutter\b/', '"' . $absolute_flutter . '"', $cmd);
                                }
                            }
                            $log_data .= "Modified Command: " . $cmd . "\n";
                            
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
                            
                            $process = proc_open($cmd, $descriptorspec, $pipes, WORKSPACE_DIR, $env);
                            if (is_resource($process)) {
                                fclose($pipes[0]);
                                $stdout = stream_get_contents($pipes[1]);
                                fclose($pipes[1]);
                                $stderr = stream_get_contents($pipes[2]);
                                fclose($pipes[2]);
                                $exit_code = proc_close($process);
                                
                                $log_data .= "Exit Code: " . $exit_code . "\n";
                                $log_data .= "Stdout: " . $stdout . "\n";
                                $log_data .= "Stderr: " . $stderr . "\n\n";
                                @file_put_contents($log_file, $log_data, FILE_APPEND);
                                $tool_output = array(
                                    'exit_code' => $exit_code,
                                    'stdout' => $stdout,
                                    'stderr' => $stderr
                                );
                            } else {
                                $log_data .= "Process failed to start.\n\n";
                                @file_put_contents($log_file, $log_data, FILE_APPEND);
                                $tool_output = array('error' => 'Failed to execute command');
                            }
                        } else {
                            $tool_output = array('error' => 'Unknown function: ' . $name);
                        }
                        
                        $function_responses[] = array(
                            'functionResponse' => array(
                                'name' => $name,
                                'response' => $tool_output
                            )
                        );
                    }
                    
                    $contents[] = array(
                        'role' => 'function',
                        'parts' => $function_responses
                    );
                    
                } else {
                    foreach ($parts as $part) {
                        if (isset($part['text'])) {
                            $final_reply .= $part['text'];
                        }
                    }
                    break;
                }
            }
            
            if (!empty($error_message)) {
                echo json_encode(array('status' => 'error', 'message' => $error_message));
            } elseif ($final_reply !== '') {
                echo json_encode(array('status' => 'success', 'reply' => $final_reply));
            } else {
                echo json_encode(array('status' => 'error', 'message' => 'No response was generated by the agent. Max turns reached.'));
            }
            
        } elseif ($model === 'chatgpt') {
            if (empty($chatgpt_key)) {
                echo json_encode(array('status' => 'error', 'message' => 'ChatGPT API Key missing. Update in Settings.'));
                exit;
            }
            
            $openai_messages = array();
            foreach ($messages as $msg) {
                $role = ($msg['role'] === 'assistant' || $msg['role'] === 'model') ? 'assistant' : ($msg['role'] === 'system' ? 'system' : 'user');
                $openai_messages[] = array(
                    'role' => $role,
                    'content' => $msg['content']
                );
            }
            
            $post_data = array(
                'model' => 'gpt-4o-mini', // Fast, cheap default, can be customized
                'messages' => $openai_messages
            );
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_URL, OPENAI_API_URL);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $chatgpt_key
            ));
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                $res_data = json_decode($response, true);
                if (isset($res_data['choices'][0]['message']['content'])) {
                    $ai_text = $res_data['choices'][0]['message']['content'];
                    echo json_encode(array('status' => 'success', 'reply' => $ai_text));
                } else {
                    echo json_encode(array('status' => 'error', 'message' => 'Invalid response structure from ChatGPT API', 'raw' => $res_data));
                }
            } else {
                echo json_encode(array('status' => 'error', 'message' => 'ChatGPT API Error (HTTP ' . $http_code . ')', 'details' => json_decode($response, true)));
            }
            
        } elseif ($model === 'claude') {
            if (empty($claude_key)) {
                echo json_encode(array('status' => 'error', 'message' => 'Claude API Key missing. Update in Settings.'));
                exit;
            }
            
            $claude_messages = array();
            $system_prompt = '';
            
            foreach ($messages as $msg) {
                if ($msg['role'] === 'system') {
                    $system_prompt = $msg['content'];
                    continue;
                }
                $role = ($msg['role'] === 'assistant' || $msg['role'] === 'model') ? 'assistant' : 'user';
                $claude_messages[] = array(
                    'role' => $role,
                    'content' => $msg['content']
                );
            }
            
            $post_data = array(
                'model' => 'claude-3-5-haiku-latest', // High speed default
                'max_tokens' => 4096,
                'messages' => $claude_messages
            );
            
            if (!empty($system_prompt)) {
                $post_data['system'] = $system_prompt;
            }
            
            curl_setopt($ch, CURLOPT_URL, CLAUDE_API_URL);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'content-type: application/json',
                'x-api-key: ' . $claude_key,
                'anthropic-version: 2023-06-01'
            ));
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                $res_data = json_decode($response, true);
                if (isset($res_data['content'][0]['text'])) {
                    $ai_text = $res_data['content'][0]['text'];
                    echo json_encode(array('status' => 'success', 'reply' => $ai_text));
                } else {
                    echo json_encode(array('status' => 'error', 'message' => 'Invalid response structure from Claude API', 'raw' => $res_data));
                }
            } else {
                echo json_encode(array('status' => 'error', 'message' => 'Claude API Error (HTTP ' . $http_code . ')', 'details' => json_decode($response, true)));
            }
        } else {
            echo json_encode(array('status' => 'error', 'message' => 'Model ' . $model . ' not supported'));
        }
        break;

    case 'terminal_start':
        $command = isset($input['command']) ? $input['command'] : '';
        if (empty($command)) {
            echo json_encode(array('status' => 'error', 'message' => 'No command provided'));
            exit;
        }

        // Check if already running
        $status_file = __DIR__ . '/.terminal_status.json';
        if (file_exists($status_file)) {
            $status_data = json_decode(file_get_contents($status_file), true);
            if ($status_data && isset($status_data['status']) && $status_data['status'] === 'running') {
                echo json_encode(array('status' => 'error', 'message' => 'A command is already running'));
                exit;
            }
        }

        // Write configuration
        $cmd_config = array(
            'command' => $command,
            'workspace_dir' => WORKSPACE_DIR
        );
        file_put_contents(__DIR__ . '/.terminal_cmd.json', json_encode($cmd_config));

        // Start background process
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            $cmd = "start /B php " . escapeshellarg(__DIR__ . '/terminal_runner.php') . " > NUL 2>&1";
            pclose(popen($cmd, "r"));
        } else {
            $cmd = "php " . escapeshellarg(__DIR__ . '/terminal_runner.php') . " > /dev/null 2>&1 &";
            exec($cmd);
        }

        echo json_encode(array('status' => 'success', 'message' => 'Command started'));
        break;

    case 'terminal_poll':
        $offset = isset($input['offset']) ? intval($input['offset']) : 0;
        $output = '';
        $output_file = __DIR__ . '/.terminal_output.txt';
        $current_size = 0;

        if (file_exists($output_file)) {
            $current_size = filesize($output_file);
            if ($current_size > $offset) {
                $handle = fopen($output_file, 'r');
                if ($handle) {
                    fseek($handle, $offset);
                    $output = fread($handle, $current_size - $offset);
                    fclose($handle);
                }
            }
        }

        $status_file = __DIR__ . '/.terminal_status.json';
        $status = array('status' => 'stopped');
        if (file_exists($status_file)) {
            $status = json_decode(file_get_contents($status_file), true);
        }

        echo json_encode(array(
            'status' => 'success',
            'output' => $output,
            'new_offset' => $current_size,
            'process_status' => $status
        ));
        break;

    case 'terminal_input':
        $text = isset($input['text']) ? $input['text'] : '';
        if ($text !== '') {
            file_put_contents(__DIR__ . '/.terminal_input.txt', $text, FILE_APPEND);
        }
        echo json_encode(array('status' => 'success'));
        break;

    case 'terminal_stop':
        file_put_contents(__DIR__ . '/.terminal_control.json', json_encode(array('action' => 'stop')));
        echo json_encode(array('status' => 'success'));
        break;

    case 'apk_build_start':
        // Check if already running
        $status_file = __DIR__ . '/.apk_build_status.json';
        if (file_exists($status_file)) {
            $status_data = json_decode(file_get_contents($status_file), true);
            if ($status_data && isset($status_data['status']) && $status_data['status'] === 'running') {
                echo json_encode(array('status' => 'error', 'message' => 'A build is already in progress.'));
                exit;
            }
        }

        // Configure build
        $config = array(
            'mode' => isset($input['mode']) ? $input['mode'] : 'release',
            'target' => isset($input['target']) ? $input['target'] : 'fat',
            'obfuscate' => !empty($input['obfuscate']),
            'clean' => !empty($input['clean']),
            'workspace_dir' => WORKSPACE_DIR,
            'shared_hosting_url' => SHARED_HOSTING_URL
        );
        file_put_contents(__DIR__ . '/.apk_build_config.json', json_encode($config));

        // Start background build process
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            $cmd = "start /B \"\" " . escapeshellarg(PHP_BINARY) . " " . escapeshellarg(__DIR__ . '/apk_builder_runner.php') . " > NUL 2>&1";
            pclose(popen($cmd, "r"));
        } else {
            $cmd = escapeshellarg(PHP_BINARY) . " " . escapeshellarg(__DIR__ . '/apk_builder_runner.php') . " > /dev/null 2>&1 &";
            exec($cmd);
        }

        echo json_encode(array('status' => 'success', 'message' => 'APK generation process started.'));
        break;

    case 'apk_build_poll':
        $offset = isset($input['offset']) ? intval($input['offset']) : 0;
        $output = '';
        $output_file = __DIR__ . '/.apk_build_output.txt';
        $current_size = 0;

        if (file_exists($output_file)) {
            $current_size = filesize($output_file);
            if ($current_size > $offset) {
                $handle = fopen($output_file, 'r');
                if ($handle) {
                    fseek($handle, $offset);
                    $output = fread($handle, $current_size - $offset);
                    fclose($handle);
                }
            }
        }

        $status_file = __DIR__ . '/.apk_build_status.json';
        $status = array('status' => 'idle');
        if (file_exists($status_file)) {
            $status = json_decode(file_get_contents($status_file), true);
        }

        echo json_encode(array(
            'status' => 'success',
            'output' => $output,
            'new_offset' => $current_size,
            'build_status' => $status
        ));
        break;

    case 'apk_build_stop':
        file_put_contents(__DIR__ . '/.apk_build_control.json', json_encode(array('action' => 'stop')));
        echo json_encode(array('status' => 'success'));
        break;

    case 'apk_build_status':
        $status_file = __DIR__ . '/.apk_build_status.json';
        $status = array('status' => 'idle');
        if (file_exists($status_file)) {
            $status = json_decode(file_get_contents($status_file), true);
        }

        // Scan for existing APKs
        $flutter_dir = get_flutter_project_dir(WORKSPACE_DIR);
        $apk_dir = $flutter_dir . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'outputs' . DIRECTORY_SEPARATOR . 'flutter-apk';
        $found_apks = [];
        if (is_dir($apk_dir)) {
            $files = scandir($apk_dir);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'apk') {
                    $found_apks[] = $file;
                }
            }
        }

        echo json_encode(array(
            'status' => 'success',
            'build_status' => $status,
            'apks' => $found_apks
        ));
        break;

    case 'apk_build_download':
        $file_name = isset($_GET['file']) ? $_GET['file'] : (isset($input['file']) ? $input['file'] : '');
        $file_name = basename($file_name);
        
        if (empty($file_name) || pathinfo($file_name, PATHINFO_EXTENSION) !== 'apk') {
            header("HTTP/1.0 400 Bad Request");
            echo "Invalid file name.";
            exit;
        }

        $flutter_dir = get_flutter_project_dir(WORKSPACE_DIR);
        $apk_path = $flutter_dir . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'outputs' . DIRECTORY_SEPARATOR . 'flutter-apk' . DIRECTORY_SEPARATOR . $file_name;

        if (!file_exists($apk_path)) {
            header("HTTP/1.0 404 Not Found");
            echo "APK File not found.";
            exit;
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($apk_path));

        while (ob_get_level()) {
            ob_end_clean();
        }

        readfile($apk_path);
        exit;

    default:
        echo json_encode(array('status' => 'error', 'message' => 'Invalid action ' . $action));
        break;
}
