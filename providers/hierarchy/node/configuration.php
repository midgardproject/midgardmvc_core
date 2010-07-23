<?php
class midgardmvc_core_providers_hierarchy_node_configuration implements midgardmvc_core_providers_hierarchy_node
{
    private $argv = array();

    public $name = '';
    public $title = '';
    public $content = '';
    private $component = null;

    public function __construct(array $configuration)
    {
        $this->name =& $configuration['name'];
        $this->title =& $configuration['title'];
        $this->content =& $configuration['content'];
        $this->component =& $configuration['component'];
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
        return array();
    }

    public function get_child_by_name($name)
    {
        return null;
    }

    public function has_child_nodes()
    {
        return false;
    }

    public function get_parent_node()
    {
        return null;
    }
}
