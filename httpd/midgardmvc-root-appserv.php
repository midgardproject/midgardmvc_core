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

    $_midgardmvc_root = realpath(__DIR__ . '/../..') . '/';

    $urlmap = array
    (
        '/' => $app,
        '/favicon.ico' => function($ctx) { return array(404, array(), ''); },
    );

    $components = midgardmvc_core::get_instance()->component->get_components();
    foreach ($components as $component)
    {
        if (!file_exists("{$_midgardmvc_root}{$component->name}/static"))
        {
            continue;
        }
        $urlmap["/midgardmvc-static/{$component->name}"] = new file_server("{$_midgardmvc_root}{$component->name}/static", 4000000);
    }

    $map = new \MFS\AppServer\Middleware\URLMap\URLMap($urlmap);

    $handler = new \MFS\AppServer\DaemonicHandler('tcp://127.0.0.1:8001', 'HTTP');
    $handler->serve(new aip_logger($map, STDOUT));
}
catch (Exception $e)
{
    echo $e;
}
