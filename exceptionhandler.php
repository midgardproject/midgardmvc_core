<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard MVC exception handler
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_exceptionhandler
{
    public static function handle(Exception $exception)
    {
        // Different HTTP error codes for different Exceptions
        $message_type = get_class($exception);
        switch ($message_type)
        {
            case 'midgardmvc_exception_notfound':
            case 'midgardmvc_exception_unauthorized':
            case 'midgardmvc_exception_httperror':
                $http_code = $exception->getCode();
                break;
            default:
                $http_code = 500;
                break;
        }

        $message = strip_tags($exception->getMessage());
        $message = str_replace("\n", ' ', $message);

        $midgardmvc = midgardmvc_core::get_instance();

        $midgardmvc->log($message_type, $message, 'warning');

        $header = self::header_by_code($http_code);
        if (!isset($midgardmvc->dispatcher))
        {
            if (headers_sent())
            {
                die("<h1>Unexpected Error</h1>\n\n<p>Headers were sent so we don't have correct HTTP code ({$http_code}).</p>\n\n<p>{$message_type}: {$message}</p>\n");
            }

            header("X-MidgardMVC-Error: {$message}");
            header($header);
        }
        else
        {
            if ($midgardmvc->dispatcher->headers_sent())
            {
                echo "<h1>Unexpected Error</h1>\n\n<p>Headers were sent so we don't have correct HTTP code ({$http_code}).</p>\n\n<p>{$message_type}: {$message}</p>\n";
                $midgardmvc->dispatcher->end_request();
            }

            $midgardmvc->dispatcher->header("X-MidgardMVC-Error: {$message}");
            $midgardmvc->dispatcher->header($header);
        }

        if ($http_code != 304)
        {
            if (isset($midgardmvc->dispatcher))
            {
                $midgardmvc->dispatcher->header('Content-Type: text/html; charset=utf-8');
            }

            $data['header'] = $header;
            $data['message_type'] = $message_type;
            $data['message'] = $message;
            $data['exception'] = $exception;

            $data['trace'] = false;

            if (!$midgardmvc)
            {
                return;
            }

            if (   $midgardmvc->configuration 
                && $midgardmvc->configuration->enable_exception_trace)
            {
                $data['trace'] = $exception->getTrace();
            }

            try
            {
                $request = $midgardmvc->context->get_request();
                if (!$request)
                {
                    throw $exception;
                }

                $route = $request->get_route();
                $route->template_aliases['root'] = 'midcom-show-error';
                
                $request->set_data_item('midgardmvc_core_exceptionhandler', $data);
                $request->set_data_item('cache_enabled', false);

                if (!$midgardmvc->templating)
                {
                    throw new Exception('no templating found');
                }

                $midgardmvc->templating->template($request);
                $midgardmvc->templating->display($request);
            }
            catch (Exception $e)
            {
                // Templating isn't working
                echo "<!DOCTYPE html>\n";
                echo "<html>\n";
                echo "    <head>\n";
                echo "        <title>{$header}</title>\n";
                echo "    </head>\n";
                echo "    <body class=\"{$message_type}\">\n";
                echo "        <h1>{$header}</h1>\n";
                echo "        <p>{$message}</p>\n";
                echo "    </body>\n";
                echo "</html>";
            }
            
            // Clean up and finish
            $midgardmvc->context->delete();
        }
    }

    private static function header_by_code($code)
    {
        $headers = array
        (
            200 => 'HTTP/1.0 200 OK',
            303 => 'HTTP/1.0 303 See Other',
            304 => 'HTTP/1.0 304 Not Modified',
            401 => 'HTTP/1.0 401 Unauthorized',
            404 => 'HTTP/1.0 404 Not Found',
            405 => 'HTTP/1.0 405 Method not allowed',
            500 => 'HTTP/1.0 500 Server Error',
            503 => 'HTTP/1.0 503 Service Unavailable',
        );

        if (!isset($headers[$code]))
        {
            $code = 500;
        }

        return $headers[$code];
    }
}

/**
 * Basic Midgard MVC exception
 *
 * @package midgardmvc_core
 */
class midgardmvc_exception extends Exception {}

/**
 * Midgard MVC "not found" exception
 *
 * @package midgardmvc_core
 */
class midgardmvc_exception_notfound extends midgardmvc_exception
{
    // Redefine the exception so message isn't optional
    public function __construct($message, $code = 404) 
    {
        parent::__construct($message, $code);
    }
}

/**
 * Midgard MVC "unauthorized" exception
 *
 * @package midgardmvc_core
 */
class midgardmvc_exception_unauthorized extends midgardmvc_exception
{
    // Redefine the exception so message isn't optional
    public function __construct($message, $code = 401) 
    {
        parent::__construct($message, $code);
    }
}

/**
 * Midgard MVC generic HTTP error exception
 *
 * @package midgardmvc_core
 */
class midgardmvc_exception_httperror extends midgardmvc_exception
{
    // Redefine the exception so message isn't optional
    public function __construct($message, $code = 500) 
    {
        parent::__construct($message, $code);
    }
}

set_exception_handler(array('midgardmvc_core_exceptionhandler', 'handle'));
?>
