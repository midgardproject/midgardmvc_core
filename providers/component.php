<?php
interface midgardmvc_core_providers_component
{
    public function get($component);

    public function is_installed($component);

    public function inject(midgardmvc_core_request $request, $injector_type);
}
