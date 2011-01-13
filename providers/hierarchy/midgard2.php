<?php
class midgardmvc_core_providers_hierarchy_midgard2 implements midgardmvc_core_providers_hierarchy
{
    private $root_node = null;
    private $root_node_id = null;

    public function __construct()
    {
        $this->check_dependencies();
        $this->load_root_node();

        // Subscribe to node editing signals
        midgard_object_class::connect_default('midgardmvc_core_node', 'action-updated', array($this, 'refresh_node'), array());
    }

    private function check_dependencies()
    {
        if (!extension_loaded('midgard2'))
        {
            throw new Exception('The midgardmvc hierarchy provider requires Midgard2 PHP extension to be present. If you\'re not running MVC with Midgard2 then use the configuration node provider');
        }

        if (!class_exists('midgardmvc_core_node', false))
        {
            throw new Exception('The Midgard2 schemas needed for the midgardmvc hierarchy provider are not loaded. Check your Midgard2 schema directory');
        }
    }

    private function load_root_node()
    {
        $midgardmvc = midgardmvc_core::get_instance();
        if ($midgardmvc->configuration->midgardmvc_root_page)
        {
            try
            {
                $node = new midgardmvc_core_node($midgardmvc->configuration->midgardmvc_root_page);
            }
            catch (midgard_error_exception $e)
            {
                $node = new midgardmvc_core_node();
                $node->get_by_path('/midgardmvc_root');
            }
        }
        else
        {
            $node = new midgardmvc_core_node();
            $node->get_by_path('/midgardmvc_root');
        }
        $this->root_node_id = $node->id;

        $this->root_node =  midgardmvc_core_providers_hierarchy_node_midgard2::get_instance($node);
    }

    public function refresh_node(midgardmvc_core_node $node)
    {
        $hierarchy_node = midgardmvc_core_providers_hierarchy_node_midgard2::get_instance($node);
        $hierarchy_node->refresh_node();
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
        if (isset(midgardmvc_core_providers_hierarchy_node_midgard2::$nodes_by_component[$component]))
        {
            return midgardmvc_core_providers_hierarchy_node_midgard2::$nodes_by_component[$component];
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
        return midgardmvc_core_providers_hierarchy_node_midgard2::get_instance($nodes[0]);
    }

    private static function prepare_node(midgardmvc_core_node $node, array $node_data, $destructive)
    {
        $node->title = $node_data['title'];
        $node->component = $node_data['component'];
        if (   $destructive
            || !$node->content)
        {
            $node->content = $node_data['content'];
        }

        if ($node->guid)
        {
            $node->update();
        }
        else
        {
            $node->create();
        }

        if (isset($node_data['configuration']))
        {
            $node->set_parameter('midgardmvc_core', 'configuration', midgardmvc_core::write_yaml($node_data['configuration']));
        }

        self::prepare_node_children($node, $node_data, $destructive);
    }

    private static function prepare_node_children(midgardmvc_core_node $node, array $node_data, $destructive)
    {
        if (   !isset($node_data['children'])
            || empty($node_data['children']))
        {
            return;
        }

        $qb = new midgard_query_builder('midgardmvc_core_node');
        $qb->add_constraint('up', '=', $node->id);
        $children = $qb->execute();
        foreach ($children as $child)
        {
            if (!isset($node_data['children'][$child->name]))
            {
                // Child node in database that is missing from config
                if ($destructive)
                {
                    $child->delete();
                }
                continue;
            }

            self::prepare_node($child, $node_data['children'][$child->name], $destructive);

            unset($node_data['children'][$child->name]);
        }

        // Handle missing children
        foreach ($node_data['children'] as $name => $child_data)
        {
            $child = new midgardmvc_core_node();
            $child->name = $name;
            $child->up = $node->id;
            self::prepare_node($child, $child_data, $destructive);
        }
    }

    public static function prepare_nodes(array $nodes, $destructive = false)
    {
        // Get root node
        $qb = new midgard_query_builder('midgardmvc_core_node');
        if (midgardmvc_core::get_instance()->configuration->midgardmvc_root_page)
        {
            $qb->add_constraint('guid', '=', midgardmvc_core::get_instance()->configuration->midgardmvc_root_page);
        }
        else
        {
            $qb->add_constraint('up', '=', 0);
            $qb->add_constraint('name', '=', 'midgardmvc_root');
        }
        $roots = $qb->execute();

        if (count($roots) == 0)
        {
            // Initialize a new root node
            $root = new midgardmvc_core_node();
            $root->up = 0;
            $root->name = 'midgardmvc_root';
        }

        self::prepare_node($root, $nodes, $destructive);
    }
}
