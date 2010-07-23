<?php
class midgardmvc_core_providers_hierarchy_configuration implements midgardmvc_core_providers_hierarchy
{
    private $root_node = null;

    public function __construct()
    {
        $config = array
        (
            'name' => null,
            'title' => 'Midgard MVC',
            'content' => '',
            'component' => 'midgardmvc_core',
        );
        $this->root_node = new midgardmvc_core_providers_hierarchy_node_configuration($config);
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
            return $this->root_node;
        }

        $argv = explode('/', $path);
        $this->root_node->set_arguments($argv);
        return $this->root_node;
    }
}
