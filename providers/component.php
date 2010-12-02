<?php
interface midgardmvc_core_providers_component
{
    public function get($component);

    public function load_library($library);

    public function is_installed($component);

    public function get_components();

    public function get_routes(midgardmvc_core_request $request);

    public function inject(midgardmvc_core_request $request, $injector_type);
}
