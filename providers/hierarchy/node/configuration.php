<?php
class midgardmvc_core_providers_hierarchy_node_configuration implements midgardmvc_core_providers_hierarchy_node
{
    private $argv = array();
    private $parent = null;

    public $name = '';
    public $title = '';
    public $content = '';
    private $component = null;
    private $children = array();

    public function __construct($name, array $configuration, midgardmvc_core_providers_hierarchy_node_configuration $parent = null)
    {
        $this->name =& $name;
        $this->title =& $configuration['title'];
        $this->content =& $configuration['content'];
        $this->component =& $configuration['component'];
        $this->parent =& $parent;
    }

    public function get_object()
    {
        return $this;
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
