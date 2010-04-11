<?php

require 'AppServer/autoload.php';
require 'midgardmvc_core/framework.php';

// hack. making name shorter
class file_server extends MFS_AppServer_Apps_FileServe {}

class StartNewRequestException extends RuntimeException {}

class midgardmvc_appserv_app
{
    private $midgardmvc = null;
    public function __construct()
    {
        $this->midgardmvc = midgardmvc_core::get_instance('appserv');
    }

    public function __invoke($context)
    {
        // setting emulated superglobals
        $_SERVER = $context['env'];
        $_COOKIE = $context['_COOKIE'];

        if (isset($context['_POST']))
        {
            $_POST = $context['_POST'];
            if (isset($context['_FILES']))
            {
                $_FILES = $context['_FILES'];
            }
        }

        // starting processing
        try {
            $this->midgardmvc->dispatcher->set_request_data($context);

            call_user_func($context['logger'], "-> starting midgardmvc");
            try {
                ob_start();
                $this->midgardmvc->process();
                $this->midgardmvc->serve();
                $body = ob_get_clean();
            } catch (StartNewRequestException $e) {
                $body = ob_get_clean();
                call_user_func($context['logger'], "-> [!] StartNewRequestException exception arrived");
            } catch (Exception $e) {
                ob_end_clean();
                call_user_func($context['logger'], "-> [!] ".get_class($e)." exception arrived");
                throw $e;
            }
            call_user_func($context['logger'], "-> done with midgardmvc");

            return array(
                $this->midgardmvc->dispatcher->_get_status(),
                $this->midgardmvc->dispatcher->_get_headers(),
                $body
            );
        } catch (Exception $e) {
            echo $e;
            return array(500, array('Content-type', 'text/plain'), "Internal Server Error \n(check log)");
        }
    }
}

try {
    $app = new midgardmvc_appserv_app();
    $app = new MFS_AppServer_Middleware_PHP_Compat($app);

    $_midcom_root = realpath(dirname(__FILE__).'/../..').'/';

    $file_app = new file_server($_midcom_root.'/midgardmvc_core/static');
    $file_app2 = new file_server(realpath(dirname(__FILE__).'/../../net_nemein_dasboard/static'));

    $app = new MFS_AppServer_Middleware_URLMap(array(
        '/' => $app,
        '/midcom-static/midgardmvc_core'                => new file_server($_midcom_root.'midgardmvc_core/static'),
        '/midcom-static/midgardmvc_helper_datamanager'  => new file_server($_midcom_root.'midgardmvc_helper_datamanager/static'),
        // '/midcom-static/midgardmvc_helper_xsspreventer' => new file_server($_midcom_root.'midgardmvc_helper_xsspreventer/static'),
        '/midcom-static/net_nemein_dasboard'            => new file_server($_midcom_root.'net_nemein_dasboard/static'),
        // '/midcom-static/org_openpsa_qbpager'            => new file_server($_midcom_root.'org_openpsa_qbpager/static'),
    ));

    $handler = new MFS_AppServer_DaemonicHandler('tcp://127.0.0.1:8080', 'HTTP');
    $handler->serve($app);
} catch (Exception $e) {
    echo $e;
}
