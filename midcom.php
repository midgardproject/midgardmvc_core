<?php
/**
 * @package midcom_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM core class
 *
 * @package midcom_core
 */
class midcom_core_midcom
{
    /**
     * @var midcom_core_services_configuration_yaml
     */
    public $configuration;

    /**
     * @var midcom_core_component_loader
     */
    public $componentloader;

    /**
     * @var midcom_core_services dispatcher
     */
    public $dispatcher;

    /**
     * @var midcom_core_helpers_context
     */
    public $context;
    
    /**
     * Access to installed FirePHP logger
     *
     * @var FirePHP
     */
    public $firephp = null;
    
    private $track_autoloaded_files = false;
    private $autoloaded_files = array();
    
    private static $instance = null;
    
    public function __construct()
    {
        // Register autoloader so we get all MidCOM classes loaded automatically
        spl_autoload_register(array($this, 'autoload'));
    }
    
    /**
     * Load all basic services needed for MidCOM usage. This includes configuration, authorization and the component loader.
     */
    public function load_base_services($dispatcher = 'midgard')
    {
        // Load the request dispatcher
        $dispatcher_implementation = "midcom_core_services_dispatcher_{$dispatcher}";
        $this->dispatcher = new $dispatcher_implementation();

        // Load the context helper
        $this->context = new midcom_core_helpers_context();

        // Load the configuration loader and load core config
        $this->configuration = new midcom_core_services_configuration_yaml('midcom_core');

        // Load the head helper
        $this->head = new midcom_core_helpers_head($this->configuration);
        
        if ($this->configuration->development_mode)
        {
            // Load FirePHP logger
            // TODO: separate setting
            include('FirePHPCore/FirePHP.class.php');
            if (class_exists('FirePHP'))
            {
                $this->firephp = FirePHP::getInstance(true);
            }
        }
    }
    
    /**
     * Helper for service initialization. Usually called via getters
     *
     * @param string $service Name of service to load
     */
    private function load_service($service)
    {
        if (isset($this->$service))
        {
            return;
        }
        
        $interface_file = MIDCOM_ROOT . "/midcom_core/services/{$service}.php";
        if (!file_exists($interface_file))
        {
            throw new InvalidArgumentException("Service {$service} not installed");
        }
        
        $service_implementation = $this->configuration->get("services_{$service}");
        if (!$service_implementation)
        {
            throw new Exception("No implementation defined for service {$service}");
        }

        $this->$service = new $service_implementation();
    }
    
    /**
     * Logging interface
     *
     * @param string $prefix Prefix to file the log under
     * @param string $message Message to be logged
     * @param string $loglevel Logging level, may be one of debug, info, message and warning
     */
    public function log($prefix, $message, $loglevel = 'debug')
    {
        if (!extension_loaded('midgard2'))
        {
            // Temporary non-Midgard logger until midgard_error is backported to Ragnaroek
            static $logger = null;
            if (!$logger)
            {
                try
                {
                    $logger = new midcom_core_helpers_log();
                }
                catch (Exception $e)
                {
                    // Unable to instantiate logger
                    return;
                }
            }
            static $log_levels = array
            (
                'debug' => 4,
                'info' => 3,
                'message' => 2,
                'warn' => 1,
            );
            
            if ($log_levels[$loglevel] > $log_levels[$this->configuration->get('log_level')])
            {
                // Skip logging, too low level
                return;
            }
            $logger->log("{$prefix}: {$message}");
            return;
        }

        $firephp_loglevel = $loglevel;
        // Handle mismatching loglevels
        switch ($loglevel)
        {
            case 'debug':
            case 'message':
                $firephp_loglevel = 'log';
                break;
            case 'warn':
            case 'warning':
                $loglevel = 'warning';  
                $firephp_loglevel = 'warn';
                break;
            case 'error':
            case 'critical':
                $firephp_loglevel = 'error';
                break;
        }

        if ($this->firephp)
        {
            $this->firephp->$firephp_loglevel("{$prefix}: {$message}");
        }

        midgard_error::$loglevel("{$prefix}: {$message}");
    }
    
    /**
     * Magic getter for service loading
     */
    public function __get($key)
    {
        $this->load_service($key);
        return $this->$key;
    }
    
    /**
     * Automatically load missing class files
     *
     * @param string $class_name Name of a missing PHP class
     */
    public function autoload($class_name)
    {
        static $components = array('midcom_core');
        if (   count($components) < 2
            && isset(self::$instance->componentloader))
        {
            $components = array_keys(self::$instance->componentloader->manifests);
        }

        foreach ($components as $component)
        {
            // Look which component the file is under
            $component_length = strlen($component);
            if (substr($class_name, 0, $component_length) != $component)
            {
                continue;
            }
            
            if ($class_name == $component)
            {
                // Load the interface class
                self::$instance->componentloader->load($component);
            }
 
            $path_under_component = str_replace('_', '/', substr($class_name, $component_length));
            $path = MIDCOM_ROOT . "/{$component}{$path_under_component}.php";
            if (!file_exists($path))
            {
                return;
            }
            
            if ($this->track_autoloaded_files)
            {
                $this->autoloaded_files[] = $path;
            }
            require($path);
        }
    }
    
    /**
     * Process the current request, loading the page's component and dispatching the request to it
     */
    public function process()
    {
        $this->context->create();
        
        date_default_timezone_set($this->configuration->get('default_timezone'));
        
        $this->dispatcher->get_midgard_connection()->set_loglevel($this->configuration->get('log_level'));

        $this->dispatcher->populate_environment_data();

        /*
        // Check autoloader cache
        if ($this->cache->autoload->check($this->context->uri))
        {
            $this->cache->autoload->load($this->context->uri);
        }
        $this->track_autoloaded_files = true;
        */

        $this->log('MidCOM', "Serving {$this->dispatcher->request_method} {$this->context->uri} at " . gmdate('r'), 'info');

        // Let injectors do their work
        $this->componentloader = new midcom_core_component_loader();
        $this->componentloader->inject_process();

        // Load the cache service and check for content cache
        $this->load_service('cache');
        if (self::$instance->context->cache_enabled)
        {
            $this->dispatcher->generate_request_identifier();
            $this->cache->register_object($this->context->page);
            $this->cache->content->check($this->context->cache_request_identifier);
        }

        // Show the world this is Midgard
        $this->head->add_meta
        (
            array
            (
                'name' => 'generator',
                'content' => "Midgard/" . mgd_version() . " MidCOM/{$this->componentloader->manifests['midcom_core']['version']} PHP/" . phpversion()
            )
        );

        // Load component
        try
        {
            $component = $this->context->get_item('component');
        }
        catch (Exception $e)
        {
            return;
        }
        if (!$component)
        {
            $component = 'midcom_core';
        }

        if ($this->configuration->enable_attachment_cache)
        {
            $classname = $this->configuration->attachment_handler;
            $handler = new $classname();
            $handler->connect_to_signals();
        }

        // Set up initial templating stack
        if (   $this->configuration->services_templating_components
            && is_array($this->configuration->services_templating_components))
        {
            foreach ($this->configuration->services_templating_components as $templating_component)
            {
                self::$instance->templating->append_directory(MIDCOM_ROOT . "/{$templating_component}/templates");
            }
        }

        // Then initialize the component, so it also goes to template stack
        $this->dispatcher->initialize($component);

        if (   $this->configuration->services_templating_database_enabled
            && isset($this->context->style_id))
        {
            // And finally append style and page to template stack
            self::$instance->templating->append_style($this->context->style_id);
            self::$instance->templating->append_page($this->context->page->id);
        }

        try
        {
            $this->dispatcher->dispatch();
        }
        catch (midcom_exception_unauthorized $exception)
        {
            // Pass the exception to authentication handler
            self::$instance->authentication->handle_exception($exception);
        }

        header('Content-Type: ' . $this->context->mimetype);
    }
    
    /**
     * Serve a request either through templating or the WebDAV server
     */
    public function serve()
    {
        // Handle HTTP request
        if (self::$instance->context->webdav_request)
        {
            // Start the full WebDAV server instance
            // FIXME: Figure out how to prevent this with Variants
            $webdav_server = new midcom_core_helpers_webdav();
            $webdav_server->serve();
            // This will exit
        }

        // Prepate the templates
        $this->templating->template();

        // Read contents from the output buffer and pass to MidCOM rendering
        $this->templating->display();
        
        $this->cache->autoload->store($this->context->uri, $this->autoloaded_files);
    }

    /**
     * Access to the MidCOM instance
     */
    public static function get_instance($dispatcher = null)
    {
        static $dispatcher_used = null;
        if (is_null($dispatcher_used))
        {
            $dispatcher_used = $dispatcher;
        }
        if (   !is_null($dispatcher)
            && $dispatcher != $dispatcher_used)
        {
            throw new BadMethodCallException("Dispatcher may be provided only once (using {$dispatcher_used} while you requested {$dispatcher})");
        }

        if (is_null(self::$instance))
        {
            self::$instance = new midcom_core_midcom();
            if (is_null($dispatcher))
            {
                self::$instance->load_base_services();
            }
            else
            {
                self::$instance->load_base_services($dispatcher);
            }
        }
        return self::$instance;
    }
}
?>
