<?php

require 'AppServer/autoload.php';

// hack. making name shorter
class file_server extends MFS_AppServer_Apps_FileServe {}
function _as_w($obj) { return array($obj, '__invoke'); }

require dirname(__FILE__).'/appserv_app.php';

try
{
    $app = new MFS_AppServer_Middleware_PHP_Compat(_as_w(new midgardmvc_appserv_app()));

    $_midgardmvc_root = realpath(dirname(__FILE__).'/../..').'/';

    $urlmap = array
    (
        '/' => $app,
    );

    $components = midgardmvc_core::get_instance()->component->get_components();
    foreach ($components as $component)
    {
        if (!file_exists("{$_midgardmvc_root}{$component->name}/static"))
        {
            continue;
        }
        $urlmap["/midgardmvc-static/{$component->name}"] = _as_w(new file_server("{$_midgardmvc_root}{$component->name}/static", 4000000));
    }

    $app = new MFS_AppServer_Middleware_URLMap($urlmap);

    $handler = new MFS_AppServer_DaemonicHandler('tcp://127.0.0.1:8080', 'HTTP');
    $handler->serve(_as_w($app));
}
catch (Exception $e)
{
    echo $e;
}
