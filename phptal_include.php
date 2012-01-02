<?php
$phptal_root = '';
$midgardmvc_path = midgardmvc_core::get_component_path('midgardmvc_core');
if (file_exists("{$midgardmvc_path}/vendor/phptal/")) {
    // Try getting PHPTAL from Composer-installed version first
    $phptal_root = "{$midgardmvc_path}/vendor/phptal/phptal/";
}
require_once($phptal_root . 'PHPTAL.php');
require_once($phptal_root . 'PHPTAL/GetTextTranslator.php');
