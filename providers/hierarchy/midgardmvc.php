<?php
class midgardmvc_core_providers_hierarchy_midgardmvc implements midgardmvc_core_providers_hierarchy
{
    private $root_node = null;
    private $root_node_id = null;

    public function __construct()
    {
        if (!extension_loaded('midgard2'))
        {
            throw new Exception('The midgardmvc hierarchy provider requires Midgard2 PHP extension to be present. If you\'re not running MVC with Midgard2 then use the configuration node provider');
        }

        if (!class_exists('midgardmvc_core_node', false))
        {
            throw new Exception('The Midgard2 schemas needed for the midgardmvc hierarchy provider are not loaded. Check your Midgard2 schema directory');
        }

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
        $this->root_node_id = $node->id;

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
        // Set remaining parts of the path as node arguments
        $node->set_arguments($argv);
        // Set the actual path of the node (with arguments removed)
        $node->set_path('/' . implode('/', $real_path));
        return $node;
    }

    public function get_node_by_component($component)
    {
        if (isset(midgardmvc_core_providers_hierarchy_node_midgardmvc::$nodes_by_component[$component]))
        {
            return midgardmvc_core_providers_hierarchy_node_midgardmvc::$nodes_by_component[$component];
        }

        $qb = new midgard_query_builder('midgardmvc_core_node');
        $qb->add_constraint('component', '=', $component);
        $qb->begin_group('OR');
            $qb->add_constraint('up', 'INTREE', $this->root_node_id);
            $qb->add_constraint('id', '=', $this->root_node_id);
        $qb->end_group();
        $qb->set_limit(1);
        $nodes = $qb->execute();
        if (count($nodes) == 0)
        {
            return null;
        }
        $node = new midgardmvc_core_providers_hierarchy_node_midgardmvc($nodes[0]);
        return $node;
    }

    public function prepare_nodes(array $nodes, $destructive = false)
    {
    }
}
