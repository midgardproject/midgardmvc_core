<?php
interface midgardmvc_core_providers_hierarchy
{
    public function get_root_node();

    public function get_node_by_path($path);
}
