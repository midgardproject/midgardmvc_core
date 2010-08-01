<?php
class midgardmvc_core_providers_component_component_midgardmvc implements midgardmvc_core_providers_component_component
{
    private $path = '';
    private $parent = null;
    public $name = '';
    static $use_yaml = null;

    public function __construct($name, array $manifest)
    {
        $this->path = MIDGARDMVC_ROOT . "/{$name}";
        $this->name = $name;
        
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
        $configuration = file_get_contents($th$this->path . "/configuration/defaults.yml");

        if (!self::$use_yaml)
        {
            return Spyc::YAMLLoad($configuration);
        }

        return yaml_parse($configuration);

    }

    public function get_configuration_contents()
    {
        $path = $this->get_configuration();
        if (!file_exists($path))
        {
            return null;
        }
        return file_get_contents($path);
    }
}
