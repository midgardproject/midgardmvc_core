<?php

require realpath(dirname(__FILE__).'/..').'/framework.php';

class StartNewRequestException extends RuntimeException {}

class midgardmvc_appserv_app
{
    private $mgd;

    public function __construct()
    {
        if (ini_get('midgard.http') == 1) {
            throw new LogicException("midgard.http should be set to 'Off', while running via AiP");
        }

        // opening connection
        $filepath = get_cfg_var("midgard.configuration_file");
        $config = new midgard_config();
        $config->read_file_at_path($filepath);

        $this->mgd = midgard_connection::get_instance();
        $this->mgd->open_config($config);

        // starting mvc
        $application_config = get_cfg_var('midgardmvc.application_config');
        if (!$application_config)
        {
            $application_config = MIDGARDMVC_ROOT . '/application.yml';
        }
        $mvc = midgardmvc_core::get_instance($application_config);
    }

    public function __invoke($context)
    {
        if (method_exists($this->mgd, 'reopen'))
        {
            // making sure, that db-connection is still active
            $this->mgd->reopen();
        }

        // setting emulated superglobals
        $_SERVER = $context['env'];
        $_COOKIE = $context['_COOKIE'];
        $_GET = $context['_GET'];

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
            $mvc = midgardmvc_core::get_instance();
            $mvc->dispatcher->set_request_data($context);

            // call_user_func($context['logger'], "-> starting midgardmvc");
            try {
                ob_start();
                $request = $mvc->process();
                $mvc->serve($request);
                $body = ob_get_clean();
            } catch (StartNewRequestException $e) {
                $body = ob_get_clean();
                // call_user_func($context['logger'], "--> [!] StartNewRequestException exception arrived");
            } catch (midgardmvc_exception $e) {
                ob_end_clean();

                try {
                    ob_start();
                    midgardmvc_core_exceptionhandler::handle($e);
                    $body = ob_get_clean();
                } catch (Exception $e) {
                    ob_end_clean();
                    call_user_func($context['logger'], "--> [!] ".get_class($e)." exception arrived");
                    throw $e;
                }
            } catch (Exception $e) {
                ob_end_clean();
                call_user_func($context['logger'], "--> [!] ".get_class($e)." exception arrived");
                throw $e;
            }

            // call_user_func($context['logger'], "-> done with midgardmvc");

            return array(
                $mvc->dispatcher->_get_status(),
                $mvc->dispatcher->_get_headers(),
                $body
            );
        } catch (Exception $e) {
            echo $e;
            return array(500, array('Content-type', 'text/plain'), "Internal Server Error \n" . $e->getMessage());
        }
    }
}
