<?php
// Global configuration for path-agnostic includes and URLs

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', str_replace('\\', '/', realpath(__DIR__)));
}

$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
$projRoot = ROOT_PATH;
$basePath = '';
if ($docRoot && strpos($projRoot, $docRoot) === 0) {
    $rel = substr($projRoot, strlen($docRoot));
    $rel = '/' . trim(str_replace('\\', '/', $rel), '/');
    if ($rel === '/') { $rel = ''; }
    $basePath = $rel;
} else {
    $scriptDir = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])) : '';
    $scriptDir = rtrim($scriptDir, '/');
    $basePath = $scriptDir === '/' ? '' : $scriptDir;
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $basePath);
}

if (!function_exists('base_url')) {
    function base_url(string $path = '/') : string {
        $p = '/' . ltrim($path, '/');
        return BASE_PATH . $p;
    }
}
