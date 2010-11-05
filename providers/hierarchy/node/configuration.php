<?php
class midgardmvc_core_providers_hierarchy_node_configuration implements midgardmvc_core_providers_hierarchy_node
{
    private $argv = array();
    private $parent = null;

    public $guid = '';
    public $name = '';
    public $title = '';
    public $content = '';
    private $component = null;
    private $children = array();
    private $path = null;
    private $configuration = array();

    static $nodes_by_component = array();

    public function __construct($name, array $configuration, midgardmvc_core_providers_hierarchy_node_configuration $parent = null)
    {
        $this->name =& $name;
        $this->title =& $configuration['title'];
        $this->component =& $configuration['component'];

        if (isset($configuration['content']))
        {
            $this->content =& $configuration['content'];
        }

        if (isset($configuration['configuration']))
        {
            $this->configuration = $configuration['configuration'];
        }

        if (isset($configuration['children']))
        {
            $this->children = $configuration['children'];
        }

        $this->parent =& $parent;

        if (!isset(self::$nodes_by_component[$this->component]))
        {
            self::$nodes_by_component[$this->component] = $this;
        }
    }

    public function get_object()
    {
        return $this;
    }

    public function get_configuration()
    {
        return $this->configuration;
    }

    public function get_component()
    {
        return $this->component;
    }

    public function get_arguments()
    {
        return $this->argv;
    }

    public function set_arguments(array $argv)
    {
        $this->argv = $argv;
    }

    public function get_path()
    {
        if (is_null($this->path))
        {
            $parent = $this->get_parent_node();
            if (!$parent)
            {
                $this->path = '/';
            }
            else
            {
                $this->path = $parent->get_path() . $this->name . '/';
            }
        }
        return $this->path;
    }

    public function set_path($path)
    {
        $this->path = $path;
    }

    public function get_child_nodes()
    {
        $children = array();
        foreach ($this->children as $name => $child)
        {
            $children[] = new midgardmvc_core_providers_hierarchy_node_configuration($name, $child, $this);
        }
        return $children;
    }

    public function get_child_by_name($name)
    {
        if (isset($this->children[$name]))
        {
            return new midgardmvc_core_providers_hierarchy_node_configuration($name, $this->children[$name], $this);
        }
        return null;
    }

    public function has_child_nodes()
    {
        return !empty($this->children);
    }

    public function get_parent_node()
    {
        return $this->parent;
    }
}
