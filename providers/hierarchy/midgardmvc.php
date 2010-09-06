<?php
class midgardmvc_core_providers_hierarchy_midgardmvc implements midgardmvc_core_providers_hierarchy
{
    private $root_node = null;

    public function __construct()
    {
        $this->midgardmvc = midgardmvc_core::get_instance();
        try
        {
            $node = new midgardmvc_core_node($this->midgardmvc->configuration->midgardmvc_root_page);
        }
        catch (midgard_error_exception $e)
        {
            $node = new midgardmvc_core_node();
            $node->get_by_path('/midgardmvc_root');
        }

        $this->root_node = new midgardmvc_core_providers_hierarchy_node_midgardmvc($node);
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
        $node->set_path(implode('/', $real_path));
        return $node;
    }
}
