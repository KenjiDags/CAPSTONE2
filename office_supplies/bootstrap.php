<?php
// Browser URLs resolve from the project root; PHP includes use explicit filesystem paths.
if (!defined('APP_ROOT_URL')) {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/office_supplies/index.php');
    $rootUrl = rtrim(str_replace('\\', '/', dirname(dirname($script))), '/');
    define('APP_ROOT_URL', $rootUrl . '/');
}