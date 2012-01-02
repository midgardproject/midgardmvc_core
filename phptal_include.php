<?php
$phptal_root = '';
if (file_exists(MIDGARDMVC_ROOT . '/midgardmvc_core/vendor/phptal/')) {
    // Try getting PHPTAL from Composer-installed version first
    $phptal_root = MIDGARDMVC_ROOT . '/midgardmvc_core/vendor/phptal/phptal/';
}
require_once($phptal_root . 'PHPTAL.php');
require_once($phptal_root . 'PHPTAL/GetTextTranslator.php');
