<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard MVC filesystem componen
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_providers_component_component_midgardmvc implements midgardmvc_core_providers_component_component
{
    private $manifest = array();
    private $path = '';
    private $parent = null;
    private $configuration = null;
    private $cached_routes = array();
    public $name = '';

    public function __construct($name, array $manifest)
    {
        $this->path = midgardmvc_core::get_component_path($name);
        $this->name = $name;
        $this->manifest = $manifest;
        if ($manifest['extends'])
        {
            $this->parent = midgardmvc_core::get_instance()->component->get($manifest['extends']);
        }
        
        array_walk($manifest['observations'], array($this, 'register_observation'));
    }
    
    public function register_observation(array $observation)
    {
        midgardmvc_core::get_instance()->observation->add_listener($observation['callback'], $observation['event'], $observation['type']);
    }

    public function get_parent()
    {
        return $this->parent;
    }

    public function get_class($class)
    {
        // Our classes are directly handled by Midgard MVC autoloader
        return;
    }

    public function get_class_contents($class)
    {
        $local_class = substr($class, strlen($this->name));
        $path = $this->path . str_replace('_', '/', $local_class) . '.php';
        if (!file_exists($path))
        {
            return null;
        }
        return file_get_contents($path);
    }

    public function get_classes()
    {
        $classes = array();

        // Check for MgdSchemas from the component
        $schemas = midgardmvc_core::get_instance()->dispatcher->get_mgdschema_classes();
        foreach ($schemas as $schema)
        {
            if (substr($schema, 0, strlen($this->name)) != $this->name)
            {
                // Not from this component
                continue;
            }
            $classes[] = $schema;
        }

        // Seek component for all PHP files
        $filesystem_classes = $this->get_classes_filesystem($this->path, "{$this->name}");
        foreach ($filesystem_classes as $class)
        {
            $classes[] = $class;
        }

        return $classes;
    }

    private function get_classes_filesystem($path, $prefix = '')
    {
        $files = array();

        // MidgardMVC Core has some files that don't conform to autoloading needs
        $ignore_dirs = array
        (
            'bin',
            'tests',
            'httpd',
        );
        $ignore_files = array
        (
            'midgardmvc_core_framework',
            'midgardmvc_core_services_templating_TAL_modifiers',
            'midgardmvc_core_helpers_spyc',
        );
        $file_aliases = array
        (
            'midgardmvc_core_exceptionhandler' => 'midgardmvc_exception',
            'midgardmvc_core_interface' => 'midgardmvc_core',
        );

        if (!file_exists($path))
        {
            return $files;
        }

        $directory = dir($path);
        while (false !== ($entry = $directory->read()))
        {
            if (substr($entry, 0, 1) == '.')
            {
                // Ignore dotfiles
                continue;
            }

            if (is_dir("{$path}/{$entry}"))
            {
                if (in_array($entry, $ignore_dirs))
                {
                    continue;
                }
                // List subdirectory
                $files = array_merge($files, $this->get_classes_filesystem("{$path}/{$entry}", "{$prefix}_{$entry}"));
                continue;
            }
            
            $pathinfo = pathinfo("{$path}/{$entry}");
            if (   !isset($pathinfo['extension'])
                || $pathinfo['extension'] != 'php')
            {
                // We're not interested in this type of file
                continue;
            }
            
            $filename = "{$prefix}_{$pathinfo['filename']}";
            if (in_array($filename, $ignore_files))
            {
                continue;
            }

            if (isset($file_aliases[$filename]))
            {
                $filename = $file_aliases[$filename];
            }

            $files[] = $filename;
        }
        $directory->close();
        return $files;
    }

    public function get_template($template)
    {
        $mvc = midgardmvc_core::get_instance();
        if (isset($mvc->context->subtemplate))
        {
            return array($this->path . "/templates/{$mvc->context->subtemplate}/{$template}.xhtml", $this->path . "/templates/{$template}.xhtml");
        }
        return array($this->path . "/templates/{$template}.xhtml");
    }

    public function get_template_contents($template)
    {
        $paths = $this->get_template($template);
        foreach ($paths as $path)
        {
            if (!file_exists($path))
            {
                continue;
            }
            return file_get_contents($path);
        }
        return null;
    }

    public function get_configuration()
    {
        if (is_null($this->configuration))
        {
            // Called for first time, load from YAML file
            $this->configuration = midgardmvc_core::read_yaml($this->get_configuration_contents());
        }
        return $this->configuration;
    }

    public function get_configuration_contents()
    {
        $configuration_file = $this->path . "/configuration/defaults.yml";
        if (!file_exists($configuration_file))
        {
            return '';
        }
        return file_get_contents($configuration_file);
    }

    public function get_path()
    {
        return $this->path;
    }

    public function get_description()
    {
        $readme_file = $this->path . "/README.markdown";
        if (!file_exists($readme_file))
        {
            $readme_file = $this->path . "/README.md";
            if (!file_exists($readme_file))
            {
                return '';
            }
        }

        return file_get_contents($readme_file);
    }

    public function get_routes(midgardmvc_core_request $request)
    {
        $node_is_root = false;
        if ($request->get_node() == midgardmvc_core::get_instance()->hierarchy->get_root_node())
        {
            $node_is_root = true;
        }
        
        if (isset($this->cached_routes[$node_is_root]))
        {
            return $this->cached_routes[$node_is_root];
        }

        $routes = array();
        if (!isset($this->manifest['routes']))
        {
            return $routes;
        }
        foreach ($this->manifest['routes'] as $route_id => $route)
        {
            if (   isset($route['root_only'])
                && $route['root_only']
                && !$node_is_root)
            {
                // Drop root-only routes from subnodes
                continue;
            }

            if (   isset($route['test_only'])
                && $route['test_only']
                && !$request->isset_data_item('test_mode'))
            {
                // Drop test-only routes when not in unit tests
                continue;
            }

            if (   isset($route['subrequest_only'])
                && $route['subrequest_only']
                && !$request->is_subrequest())
            {
                // Drop routes that are usable via subrequest only from the main request
                continue;
            }

            // Handle the required route parameters
            if (!isset($route['controller']))
            {
                throw new Exception("Route {$route_id} of {$this->name} has no controller defined");
            }

            if (!isset($route['action']))
            {
                throw new Exception("Route {$route_id} of {$this->name}  has no action defined");
            }

            if (!isset($route['path']))
            {
                throw new Exception("Route {$route_id} of {$this->name}  has no path defined");
            }

            if (!isset($route['template_aliases']))
            {
                $route['template_aliases'] = array();
            }

            if (!isset($route['mimetype']))
            {
                $route['mimetype'] = 'text/html';
                // $route['mimetype'] = 'application/xhtml+xml';
            }

            $routes[$route_id] = new midgardmvc_core_route($route_id, $route['path'], $route['controller'], $route['action'], $route['template_aliases'], $route['mimetype']);
        }
        $this->cached_routes[$node_is_root] = $routes;
        return $routes;
    }
}
