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
        $data = self::prepare_exception_data($exception);

        $midgardmvc = null;
        try
        {
            $midgardmvc = midgardmvc_core::get_instance();
            $midgardmvc->log($data['message_type'], $data['message'], 'warning');
        }
        catch (Exception $e)
        {
            // MVC not initialized, display original message and exit
            return self::show_error_plaintext($data);
        }

        if (!$midgardmvc->dispatcher->headers_sent())
        {
            $midgardmvc->dispatcher->header("X-MidgardMVC-Error: {$data['message']}");
            $midgardmvc->dispatcher->header($data['header']);
        }
        
        if ($data['http_code'] == 304)
        {
            return;
        }

        self::show_error_templated($data, $midgardmvc);
    }

    public static function handle_assert($file, $line, $expression) 
    {
        $message = "Assertion '{$expression}' failed, {$file} line {$line}";
        midgardmvc_core::get_instance()->log('midgardmvc_core', "Assertion {$expression} failed, {$file} {$line}", 'warning');
        throw new RunTimeException($message);
    }

    private static function prepare_exception_data(Exception $exception)
    {
        $data = array();
        $data['message_type'] = get_class($exception);
        $data['http_code'] = self::code_by_exception($exception);
        $data['message'] = strip_tags($exception->getMessage());
        $data['header'] = self::header_by_code($data['http_code']);
        $data['exception'] = $exception;
        $data['trace'] = null;
        return $data;
    }

    private static function show_error_plaintext(array $data, $dispatcher = null)
    {
        $message = "<h1>Unexpected Error</h1>\n\n<p>Headers were sent so we don't have correct HTTP code ({$data['http_code']}).</p>\n\n<p>{$data['message_type']}: {$data['message']}</p>\n";
        if (is_null($dispatcher))
        {
            // We got an exception before MVC was fully initialized
            if (!headers_sent())
            {
                header("X-MidgardMVC-Error: {$data['message']}");
                header(self::header_by_code($data['http_code']));
            }
            die($message);
        }
        
        echo $message;
        $dispatcher->end_request();
    }

    private static function show_error_templated(array $data, midgardmvc_core $midgardmvc)
    {
        $midgardmvc->dispatcher->header('Content-Type: text/html; charset=utf-8');

        if ($midgardmvc->configuration->enable_exception_trace)
        {
            $data['trace'] = $exception->getTrace();
        }

        try
        {
            $request = $midgardmvc->context->get_request();
            if (!$request)
            {
                // Exception happened before request was set to context
                $request = self::bootstrap_request();
            }
            $route = $request->get_route();
            $route->template_aliases['root'] = 'midgardmvc-show-error';
                
            $request->set_data_item('midgardmvc_core_exceptionhandler', $data);
            $request->set_data_item('cache_enabled', false);

            $midgardmvc->templating->template($request);
            $midgardmvc->templating->display($request);
        }
        catch (Exception $e)
        {
            // Templating isn't working
            self::show_error_untemplated($data);
        }
            
        // Clean up and finish
        $midgardmvc->context->delete();
    }
    
    private static function show_error_untemplated(array $data) 
    {
        echo "<!DOCTYPE html>\n";
        echo "<html>\n";
        echo "    <head>\n";
        echo "        <title>{$data['header']}</title>\n";
        echo "    </head>\n";
        echo "    <body class=\"{$data['message_type']}\">\n";
        echo "        <h1>{$data['header']}</h1>\n";
        echo "        <p>{$data['message']}</p>\n";
        echo "    </body>\n";
        echo "</html>";
    }

    private static function bootstrap_request()
    {
        $request = new midgardmvc_core_request();
        $core = midgardmvc_core::get_instance()->component->get('midgardmvc_core');
        $request->add_component_to_chain($core);
        $route = new midgardmvc_core_route('midgardmvc_show_error', '', '', '', array());
        $request->set_route($route);
        $request->set_component($core);
        midgardmvc_core::get_instance()->context->create($request);
        return $request;
    }
    
    public static function code_by_exception(Exception $exception)
    {
        // Different HTTP error codes for different Exceptions
        switch (get_class($exception))
        {
            case 'midgardmvc_exception_notfound':
            case 'midgardmvc_exception_unauthorized':
            case 'midgardmvc_exception_httperror':
                return $exception->getHttpCode();
        }
        return 500;
    }

    public static function header_by_code($code)
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
class midgardmvc_exception extends Exception 
{
    public function __construct($message, $code = 500) 
    {
        parent::__construct($message, $code);
    }
    
    public function getHttpCode()
    {
        return 500;
    }
}

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

    public function getHttpCode()
    {
        return 404;
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
    
    public function getHttpCode()
    {
        return 401;
    }
}

/**
 * Midgard MVC generic HTTP error exception
 *
 * @package midgardmvc_core
 */
class midgardmvc_exception_httperror extends midgardmvc_exception
{
    protected $httpcode = 500;
    // Redefine the exception so message isn't optional
    public function __construct($message, $code = 500) 
    {
        $this->httpcode = $code;
        parent::__construct($message, $code);
    }

    public function getHttpCode()
    {
        return $this->httpcode;
    }
}

set_exception_handler(array('midgardmvc_core_exceptionhandler', 'handle'));
?>
