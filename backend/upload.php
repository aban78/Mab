<?php
/**
 * Internal Cursor - Backend Folder Uploader
 * Handles webkitdirectory folder uploads
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Check if content length exceeds post_max_size (which results in empty $_POST and $_FILES)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $post_max_size = ini_get('post_max_size');
    echo json_encode(array(
        'status' => 'error',
        'message' => "The uploaded folder size exceeds the server's post_max_size limit ($post_max_size). Please upload a smaller folder or increase the limit in php.ini."
    ));
    exit;
}

// Check if files and paths are posted
if (!isset($_FILES['files']) || !isset($_POST['paths'])) {
    echo json_encode(array('status' => 'error', 'message' => 'No files or paths uploaded. Please try a smaller folder or check server file upload configurations.'));
    exit;
}

$files = $_FILES['files'];
$paths = $_POST['paths'];

if (!is_array($files['name']) || !is_array($paths)) {
    echo json_encode(array('status' => 'error', 'message' => 'Invalid upload format'));
    exit;
}

// Check if file count exceeds max_file_uploads
$max_file_uploads = (int)ini_get('max_file_uploads');
if (count($paths) > count($files['name']) && count($files['name']) < $max_file_uploads) {
    echo json_encode(array(
        'status' => 'error',
        'message' => "Some files were dropped during upload. The server has a limit of $max_file_uploads files per request (max_file_uploads)."
    ));
    exit;
}

// Define the workspaces storage folder
$workspaces_root = __DIR__ . DIRECTORY_SEPARATOR . 'workspaces';
if (!is_dir($workspaces_root)) {
    if (!@mkdir($workspaces_root, 0777, true)) {
        echo json_encode(array(
            'status' => 'error',
            'message' => 'Failed to create workspaces directory. Please check parent folder permissions.'
        ));
        exit;
    }
}

// Verify workspaces directory is writable
if (!is_writable($workspaces_root)) {
    echo json_encode(array(
        'status' => 'error',
        'message' => 'The workspaces folder on the server is not writable. Please change its permissions (e.g. chmod 777 workspaces).'
    ));
    exit;
}

$uploaded_count = 0;
$upload_errors = array();

// Extract top-level folder name from the first path
// e.g. "myproject/subfolder/file.txt" -> "myproject"
$first_path = ltrim(reset($paths), '\\/');
$parts = explode('/', str_replace('\\', '/', $first_path));
$top_folder = !empty($parts[0]) ? $parts[0] : 'uploaded_project';

// Basic sanitization of top folder name
$top_folder = preg_replace('/[^a-zA-Z0-9_\-]/', '', $top_folder);
if (empty($top_folder)) {
    $top_folder = 'uploaded_project';
}

$target_workspace_dir = $workspaces_root . DIRECTORY_SEPARATOR . $top_folder;

// Loop through each uploaded file
for ($i = 0; $i < count($files['name']); $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
        $upload_errors[] = $files['name'][$i] . ' (Error code: ' . $files['error'][$i] . ')';
        continue;
    }
    
    $tmp_name = $files['tmp_name'][$i];
    $rel_path = ltrim($paths[$i], '\\/');
    
    // Normalize path separation
    $rel_path = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $rel_path);
    
    // Safety check: ensure relative path doesn't traverse upwards
    if (strpos($rel_path, '..' . DIRECTORY_SEPARATOR) !== false || $rel_path[0] === DIRECTORY_SEPARATOR) {
        $upload_errors[] = $files['name'][$i] . ' (Security traversal check failed)';
        continue;
    }
    
    // Align folder name with the sanitized top folder
    $rel_path_parts = explode(DIRECTORY_SEPARATOR, $rel_path);
    if (count($rel_path_parts) > 0) {
        $rel_path_parts[0] = $top_folder;
        $rel_path = implode(DIRECTORY_SEPARATOR, $rel_path_parts);
    }
    
    // Full target file path
    $target_file = $workspaces_root . DIRECTORY_SEPARATOR . $rel_path;
    
    // Ensure parent directory exists
    $parent_dir = dirname($target_file);
    if (!is_dir($parent_dir)) {
        if (!@mkdir($parent_dir, 0777, true)) {
            $upload_errors[] = $files['name'][$i] . ' (Failed to create parent directory: ' . $parent_dir . ')';
            continue;
        }
    }
    @chmod($parent_dir, 0777);
    
    if (!is_writable($parent_dir)) {
        $upload_errors[] = $files['name'][$i] . ' (Parent directory is not writable: ' . $parent_dir . ')';
        continue;
    }
    
    // Move the uploaded file
    if (file_exists($target_file)) {
        @unlink($target_file);
    }
    
    if (move_uploaded_file($tmp_name, $target_file)) {
        $uploaded_count++;
        @chmod($target_file, 0666);
    } else {
        // Fallback: try copy + unlink if move_uploaded_file failed
        if (@copy($tmp_name, $target_file)) {
            @unlink($tmp_name);
            $uploaded_count++;
            @chmod($target_file, 0666);
        } else {
            $error_info = error_get_last();
            $upload_errors[] = $files['name'][$i] . ' (Failed to move file. ' . ($error_info ? $error_info['message'] : '') . ')';
        }
    }
}

if ($uploaded_count > 0 && is_dir($target_workspace_dir)) {
    $absolute_workspace_path = realpath($target_workspace_dir);
    echo json_encode(array(
        'status' => 'success',
        'message' => "Successfully uploaded $uploaded_count files.",
        'path' => str_replace('\\', '/', $absolute_workspace_path)
    ));
} else {
    $err_details = '';
    if (!empty($upload_errors)) {
        $err_details = ' Details: ' . implode(', ', array_slice($upload_errors, 0, 5));
        if (count($upload_errors) > 5) {
            $err_details .= ' ...and more.';
        }
    }
    echo json_encode(array(
        'status' => 'error',
        'message' => 'Failed to upload any files or write to directory.' . $err_details
    ));
}

