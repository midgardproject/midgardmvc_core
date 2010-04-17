<?php

require 'AppServer/autoload.php';

// hack. making name shorter
class file_server extends MFS_AppServer_Apps_FileServe {}
function _as_w($obj) { return array($obj, '__invoke'); }

require dirname(__FILE__).'/appserv_app.php';

try {
    $app = new MFS_AppServer_Middleware_PHP_Compat(_as_w(new midgardmvc_appserv_app()));

    $_midcom_root = realpath(dirname(__FILE__).'/../..').'/';

    $app = new MFS_AppServer_Middleware_URLMap(array(
        '/' => _as_w($app),
        '/midcom-static/midgardmvc_core'                => _as_w(new file_server($_midcom_root.'midgardmvc_core/static')),
        '/midcom-static/midgardmvc_helper_datamanager'  => _as_w(new file_server($_midcom_root.'midgardmvc_helper_datamanager/static')),
        '/midcom-static/net_nemein_dasboard'            => _as_w(new file_server($_midcom_root.'net_nemein_dasboard/static')),
    ));

    $handler = new MFS_AppServer_DaemonicHandler('tcp://127.0.0.1:8080', 'HTTP');
    $handler->serve(_as_w($app));
} catch (Exception $e) {
    echo $e;
}
