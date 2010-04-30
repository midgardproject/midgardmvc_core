<?php

require 'midgardmvc_core/framework.php';

class StartNewRequestException extends RuntimeException {}

class midgardmvc_appserv_app
{
    public function __construct()
    {
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
            $mvc = midgardmvc_core::get_instance('appserv');
            $mvc->dispatcher->set_request_data($context);

            call_user_func($context['logger'], "-> starting midgardmvc");
            try {
                ob_start();
                $mvc->process();
                $mvc->serve();
                $body = ob_get_clean();
            } catch (StartNewRequestException $e) {
                $body = ob_get_clean();
                call_user_func($context['logger'], "--> [!] StartNewRequestException exception arrived");
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

            call_user_func($context['logger'], "-> done with midgardmvc");

            return array(
                $mvc->dispatcher->_get_status(),
                $mvc->dispatcher->_get_headers(),
                $body
            );
        } catch (Exception $e) {
            echo $e;
            return array(500, array('Content-type', 'text/plain'), "Internal Server Error \n(check log)");
        }
    }
}
