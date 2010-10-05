<?php
interface midgardmvc_core_providers_hierarchy
{
    public function get_root_node();

    public function get_node_by_path($path);

    public function get_node_by_component($component);

    public function prepare_nodes(array $nodes, $destructive = false);
}
