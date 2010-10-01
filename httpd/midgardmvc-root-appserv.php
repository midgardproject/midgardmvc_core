<?php

require 'AppServer/autoload.php';
use MFS\AppServer\Apps\FileServe\FileServe as file_server;
use MFS\AppServer\Middleware\PHP_Compat\PHP_Compat as aip_php_compat;
use MFS\AppServer\Middleware\Logger\Logger as aip_logger;
use MFS\AppServer\Middleware\Session\Session as aip_session;

require __DIR__.'/appserv_app.php';

try
{
    $app = new aip_php_compat(new aip_session(new midgardmvc_appserv_app()));

    $_midcom_root = realpath(__DIR__ . '/../..') . '/';

    $map = new \MFS\AppServer\Middleware\URLMap\URLMap(array(
        '/' => $app,
        '/favicon.ico'                                  => function($ctx) { return array(404, array(), ''); },
        '/midgardmvc-static/midgardmvc_core'                => new file_server($_midcom_root.'midgardmvc_core/static', 4000000),
        //'/midgardmvc-static/midgardmvc_helper_forms'  => new file_server($_midcom_root.'midgardmvc_helper_forms/static'),
        '/midgardmvc-static/midgardmvc_admin'        => new file_server($_midcom_root.'midgardmvc_admin/static'),
    ));

    $handler = new \MFS\AppServer\DaemonicHandler('tcp://127.0.0.1:8001', 'HTTP');
    $handler->serve(new aip_logger($map, STDOUT));
}
catch (Exception $e)
{
    echo $e;
}
