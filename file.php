<?php

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols

use dokuwiki\plugin\filelist\Path;

if (!defined('DOKU_INC')) define('DOKU_INC', __DIR__ . '/../../../');
if (!defined('NOSESSION')) define('NOSESSION', true); // we do not use a session or authentication here (better caching)
if (!defined('DOKU_DISABLE_GZIP_OUTPUT')) define('DOKU_DISABLE_GZIP_OUTPUT', 1); // we gzip ourself here
require_once(DOKU_INC . 'inc/init.php');

global $INPUT;

$syntax = plugin_load('syntax', 'filelist');
if (!$syntax) die('plugin disabled?');

$pathUtil = new Path($syntax->getConf('paths'));
$path = $INPUT->str('root') . $INPUT->str('file');

try {
    $pathInfo = $pathUtil->getPathInfo($path, false);
    if ($pathUtil::isWikiControlled($pathInfo['path'])) {
        throw new Exception('Access to wiki files is not allowed');
    }

    if (!is_readable($pathInfo['path'])) {
        header('Content-Type: text/plain');
        http_status(404);
        echo 'Path not readable: ' . $pathInfo['path'];
        exit;
    }
    [$ext, $mime, $download] = mimetype($pathInfo['path'], false);
    $basename = basename($pathInfo['path']);
    header('Content-Type: ' . $mime);
    if ($download) {
        header('Content-Disposition: attachment; filename="' . $basename . '"');
    }
    http_sendfile($pathInfo['path']);
    readfile($pathInfo['path']);
} catch (Exception $e) {
    header('Content-Type: text/plain');
    http_status(403);
    echo $e->getMessage();
    exit;
}
