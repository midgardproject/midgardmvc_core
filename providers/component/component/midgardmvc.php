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
    public $name = '';
    static $use_yaml = null;

    public function __construct($name, array $manifest)
    {
        $this->path = MIDGARDMVC_ROOT . "/{$name}";
        $this->name = $name;
        $this->manifest = $manifest;
        if ($manifest['extends'])
        {
            $this->parent = midgardmvc_core::get_instance()->component->get($manifest['extends']);
        }
        if (is_null(self::$use_yaml))
        {
            // Check for YAML extension
            self::$use_yaml = extension_loaded('yaml');
            if (!self::$use_yaml)
            {
                // YAML PHP extension is not loaded, include the pure-PHP implementation
                require_once MIDGARDMVC_ROOT. '/midgardmvc_core/helpers/spyc.php';
            }
        }
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

    public function get_template($template)
    {
        return $this->path . "/templates/{$template}.xhtml";
    }

    public function get_template_contents($template)
    {
        $path = $this->get_template($template);
        if (!file_exists($path))
        {
            return null;
        }
        return file_get_contents($path);
    }

    public function get_configuration()
    {
        if (is_null($this->configuration))
        {
            // Called for first time, load from YAML file
            $configuration = file_get_contents($this->path . "/configuration/defaults.yml");

            if (!self::$use_yaml)
            {
                $this->configuration = Spyc::YAMLLoad($configuration);
            }
            else
            {
                $this->configuration = yaml_parse($configuration);
            }
        }
        return $this->configuration;
    }

    public function get_configuration_contents()
    {
        return file_get_contents($this->path . "/configuration/defaults.yml");
    }

    public function get_routes(midgardmvc_core_request $request)
    {
        static $routes = null;
        if (!is_null($routes))
        {
           // return $routes;
        }

        $node_is_root = false;
        if ($request->get_node() == $request->get_root_node())
        {
            $node_is_root = true;
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

            $routes[$route_id] = new midgardmvc_core_route($route_id, $route['path'], $route['controller'], $route['action'], $route['template_aliases']);
            //var_dump($route_id);
        }
        return $routes;
    }
}
