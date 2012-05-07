<?php
// By default we get PHPTAL from PEAR include path
$phptal_root = '';
if (file_exists(__DIR__ . '/vendor')) {
    // Midgard MVC was installed as main Composer package
    $phptal_root = __DIR__ . '/vendor/phptal/phptal/classes/';
} elseif (strpos(__DIR__, 'vendor/midgard') !== false) {
    // Both MVC and PHPTAL are installed as Composer dependencies
    $phptal_root = __DIR__ . '/../../phptal/phptal/classes/';
}
require_once($phptal_root . 'PHPTAL.php');
require_once($phptal_root . 'PHPTAL/GetTextTranslator.php');
