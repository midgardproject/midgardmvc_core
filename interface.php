<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard MVC interface class
 *
 * @package midgardmvc_core
 */
class midgardmvc_core extends midgardmvc_core_component_baseclass
{
    /**
     * @var midgardmvc_core_services_configuration_yaml
     */
    public $configuration;

    /**
     * @var midgardmvc_core_component_loader
     */
    public $componentloader;

    /**
     * @var midgardmvc_core_services dispatcher
     */
    public $dispatcher;

    /**
     * @var midgardmvc_core_helpers_context
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
        // Register autoloader so we get all Midgard MVC classes loaded automatically
        spl_autoload_register(array($this, 'autoload'));
    }
    
    /**
     * Load all basic services needed for Midgard MVC usage. This includes configuration, authorization and the component loader.
     */
    public function load_base_services($dispatcher = 'midgard')
    {
        // Load the context helper and initialize first context
        $this->context = new midgardmvc_core_helpers_context();

        $this->configuration = new midgardmvc_core_services_configuration_yaml();
        $this->configuration->load_component('midgardmvc_core');

        // Load the request dispatcher
        $dispatcher_implementation = "midgardmvc_core_services_dispatcher_{$dispatcher}";
        $this->dispatcher = new $dispatcher_implementation();

        // Load the head helper
        $this->head = new midgardmvc_core_helpers_head();
        
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
        
        $interface_file = MIDGARDMVC_ROOT . "/midgardmvc_core/services/{$service}.php";
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
                    $logger = new midgardmvc_core_helpers_log();
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

        if (   $this->firephp
            && !headers_sent())
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
        static $components = array('midgardmvc_core');
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
            $path = MIDGARDMVC_ROOT . "/{$component}{$path_under_component}.php";
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

        // Let dispatcher populate request with the page and other information used
        $request = $this->dispatcher->get_request();
        $request->populate_context();
        
        if (isset($this->context->page->guid))
        {
            // Load per-folder configuration
            $this->configuration->load_instance($this->context->component, $this->context->page);
        }

        $this->log('Midgard MVC', "Serving " . $request->get_method() . " {$this->context->uri} at " . gmdate('r'), 'info');

        // Let injectors do their work
        $this->componentloader = new midgardmvc_core_component_loader();
        $this->componentloader->inject_process();

        // Load the cache service and check for content cache
        $this->load_service('cache');
        if (self::$instance->context->cache_enabled)
        {
            $request->generate_identifier();
            $this->cache->register_object($this->context->page);
            $this->cache->content->check($this->context->cache_request_identifier);
        }

        // Show the world this is Midgard
        $this->head->add_meta
        (
            array
            (
                'name' => 'generator',
                'content' => "Midgard/" . mgd_version() . " MidgardMVC/{$this->componentloader->manifests['midgardmvc_core']['version']} PHP/" . phpversion()
            )
        );

        if ($this->configuration->enable_attachment_cache)
        {
            $classname = $this->configuration->attachment_handler;
            $handler = new $classname();
            $handler->connect_to_signals();
        }

        // Then initialize the component, so it also goes to template stack
        $this->dispatcher->initialize($request);
        try
        {
            $this->dispatcher->dispatch();
        }
        catch (midgardmvc_exception_unauthorized $exception)
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
            $webdav_server = new midgardmvc_core_helpers_webdav();
            $webdav_server->serve();
            // This will exit
        }

        // Prepate the templates
        $this->templating->template();

        // Read contents from the output buffer and pass to Midgard MVC rendering
        $this->templating->display();
        
        $this->cache->autoload->store($this->context->uri, $this->autoloaded_files);
        
        // Clean up the context
        $this->context->delete();
    }

    /**
     * Access to the Midgard MVC instance
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
            // Load instance
            self::$instance = new midgardmvc_core();
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

    public function get_object_actions(midgard_page &$object, $variant = null)
    {
        $actions = array();
        if (!midgardmvc_core::get_instance()->authorization->can_do('midgard:update', $object))
        {
            // User is not allowed to edit so we have no actions available
            return $actions;
        }
        
        // This is the general action available for a page: forms-based editing
        $actions['update'] = array
        (
            'url' => midgardmvc_core::get_instance()->dispatcher->generate_url('page_update', array(), $object),
            'method' => 'GET',
            'label' => midgardmvc_core::get_instance()->i18n->get('update', 'midgardmvc_core'),
            'icon' => 'midgardmvc_core/stock-icons/16x16/update.png',
        );
        $actions['delete'] = array
        (
            'url' => midgardmvc_core::get_instance()->dispatcher->generate_url('page_delete', array(), $object),
            'method' => 'GET',
            'label' => midgardmvc_core::get_instance()->i18n->get('delete', 'midgardmvc_core'),
            'icon' => 'midgardmvc_core/stock-icons/16x16/delete.png',
        );
        
        return $actions;
    }

    public function get_administer_actions(midgard_page $folder)
    {
        $actions = array();
        
        $actions['logout'] = array
        (
            'url' => midgardmvc_core::get_instance()->dispatcher->generate_url('logout', array()),
            'method' => 'GET',
            'label' => midgardmvc_core::get_instance()->i18n->get('logout', 'midgardmvc_core'),
            'icon' => 'midgardmvc_core/stock-icons/16x16/exit.png',
        );
        
        return $actions;
    }

    public function get_create_actions(midgard_page $folder)
    {
        $actions = array();

        if (!midgardmvc_core::get_instance()->authorization->can_do('midgard:create', $folder))
        {
            // User is not allowed to create subfolders so we have no actions available
            return $actions;
        }
        
        $actions['page_create'] = array
        (
            'url' => midgardmvc_core::get_instance()->dispatcher->generate_url('page_create', array()),
            'method' => 'GET',
            'label' => midgardmvc_core::get_instance()->i18n->get('create folder', 'midgardmvc_core'),
            'icon' => 'midgardmvc_core/stock-icons/16x16/folder.png',
        );
        
        return $actions;
    }
}
?>
