<?php
interface midgardmvc_core_providers_component_component
{
    public function get_parent();

    public function get_class($class);

    public function get_class_contents($class);

    public function get_classes();

    public function get_template($template);

    public function get_template_contents($template);

    public function get_configuration();

    public function get_configuration_contents();

    public function get_path();

    public function get_description();

    public function get_routes(midgardmvc_core_request $request);
}
