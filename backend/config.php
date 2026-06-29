<?php
/**
 * Internal Cursor - Backend Configuration
 * Compatible with PHP 7.4+
 */

// Helper to retrieve all request headers in any PHP environment
function cursor_get_headers() {
    if (function_exists('getallheaders')) {
        return getallheaders();
    }
    $headers = array();
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$key] = $value;
        }
    }
    return $headers;
}

// Dynamically determine the active workspace path from client headers
$headers = cursor_get_headers();
$workspace_path = '';

if (isset($_SERVER['HTTP_X_WORKSPACE_PATH'])) {
    $workspace_path = $_SERVER['HTTP_X_WORKSPACE_PATH'];
} else {
    foreach ($headers as $key => $val) {
        if (strcasecmp($key, 'X-Workspace-Path') === 0) {
            $workspace_path = $val;
            break;
        }
    }
}

$workspace_path = trim($workspace_path);

if (empty($workspace_path)) {
    // Default to the parent folder of the backend directory
    $workspace_path = dirname(__DIR__);
}

// Standardize directory separators
$workspace_path = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $workspace_path);
if (!is_dir($workspace_path)) {
    @mkdir($workspace_path, 0755, true);
}
if (is_dir($workspace_path)) {
    $workspace_path = realpath($workspace_path);
}

define('WORKSPACE_DIR', $workspace_path);

// Default API Endpoint Configurations
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent');
define('CLAUDE_API_URL', 'https://api.anthropic.com/v1/messages');
define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');

// Build Configuration
// 'local' (compiles on the local machine using local SDKs)
// 'github' (compiles in the cloud using GitHub Actions - recommended for shared hosting)
define('BUILD_METHOD', 'github');

// GitHub Actions Configuration
define('GITHUB_REPO', 'YOUR_GITHUB_USERNAME/YOUR_REPO_NAME'); // e.g. 'john-doe/my-apk-builder'
define('GITHUB_TOKEN', 'YOUR_GITHUB_PERSONAL_ACCESS_TOKEN'); // GitHub PAT with 'repo' or 'workflow' scope
define('GITHUB_WORKFLOW', 'build-apk.yml'); // The workflow filename in .github/workflows/

// Public URL of this backend (needed for GitHub Actions to download the source zip)
// We try to auto-detect it, but you can hardcode it (e.g. 'https://yourdomain.com/backend/')
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$uri = $_SERVER['REQUEST_URI'] ?? '/backend/';
$dir = preg_replace('/[^\/]+\.php$/i', '', $uri);
define('SHARED_HOSTING_URL', $protocol . '://' . $host . $dir);

// CORS configuration (allow requests from localhost / Flutter web dev server)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Gemini-Key, X-Claude-Key, X-ChatGPT-Key, X-Workspace-Path');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

