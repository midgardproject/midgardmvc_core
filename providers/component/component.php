<?php
interface midgardmvc_core_providers_component_component
{
    public function get_parent();

    public function get_class($class);

    public function get_class_contents($class);

    public function get_template($template);

    public function get_template_contents($template);

    public function get_configuration();
}
