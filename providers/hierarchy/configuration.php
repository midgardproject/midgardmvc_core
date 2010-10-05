<?php
class midgardmvc_core_providers_hierarchy_configuration implements midgardmvc_core_providers_hierarchy
{
    private $root_node = null;

    public function __construct()
    {
        $this->root_node = new midgardmvc_core_providers_hierarchy_node_configuration(null, midgardmvc_core::get_instance()->configuration->nodes);
    }

    public function get_root_node()
    {
        return $this->root_node;
    }

    public function get_node_by_path($path)
    {
        // Clean up path
        $path = substr(trim($path), 1);
        if (substr($path, strlen($path) - 1) == '/')
        {
            $path = substr($path, 0, -1);
        }
        if ($path == '')
        {
            $this->root_node->set_arguments(array());
            return $this->root_node;
        }
        
        $path = explode('/', $path);
        $real_path = array();
        $argv = $path; 
        $node = $this->root_node;
        foreach ($path as $i => $p)
        {
            $child = $node->get_child_by_name($p);
            if (is_null($child))
            {
                break;
            }
            $node = $child;
            $real_path[] = $p;
            array_shift($argv);
        }
        $node->set_arguments($argv);
        $node->set_path('/' . implode('/', $real_path));
        return $node;
    }

    public function get_node_by_component($component)
    {
    }

    public function prepare_nodes(array $nodes, $destructive = false)
    {
        // Configuration nodes don't need to be created
        return;
    }
}
