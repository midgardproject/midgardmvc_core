<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Variant handling helper for Midgard MVC
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_helpers_variants
{
    public function __construct()
    {
    }

    public function handle(array $variant, $request_method)
    {
        switch ($request_method)
        {
            case 'GET':
                if ($variant['variant'] == 'neutron-introspection')
                {
                    // Special handling, Neutron introspection file requested
                    return $this->get_neutron_introspection($variant);
                }
                return $this->handle_get($variant);
                break;
            case 'PUT':
                return $this->handle_put($variant);
                break;
            default:
                throw new midgardmvc_exception_httperror("{$request_method} not allowed", 405);
        }
    }

    private function get_neutron_introspection(array $variant)
    {
        if (!isset($this->datamanager))
        {
            // TODO: non-DM variants
            throw new midgardmvc_exception_notfound("Datamanager not available");
        }

        if ($variant['type'] != 'xml')
        {
            throw new midgardmvc_exception_notfound("Neutron Protocol introspection available only as XML");
        }

        midgardmvc_core::get_instance()->context->mimetype = 'application/neutron+xml';
        midgardmvc_core::get_instance()->context->template_entry_point = 'midcom-show-neutron_introspection';
        midgardmvc_core::get_instance()->dispatcher->header('Content-Type: ' . midgardmvc_core::get_instance()->context->mimetype);
        
        $xml = simplexml_load_string('<introspection xmlns="http://www.wyona.org/neutron/1.0"></introspection>');
        
        if (midgardmvc_core::get_instance()->authorization->can_do('midgard:update', $this->object))
        {
            $content_variant = array
            (
                'identifier' => $variant['identifier'],
                'variant' => 'content',
                'type' => 'html',
            );
            $variant_url = midgardmvc_core::get_instance()->dispatcher->generate_url($this->request, 'page_variants', array('variant' => $content_variant));
            $edit = $xml->addChild('edit');
            $edit->addAttribute('mime-type', 'text/html');
            $open = $edit->addChild('open');
            $open->addAttribute('url', $variant_url);
            $open->addAttribute('method', 'GET');
            $open = $edit->addChild('save');
            $open->addAttribute('url', $variant_url);
            $open->addAttribute('method', 'PUT');
        }
        die($xml->asXML());
    }

    private function prepare_variant(array $variant)
    {
        if (!isset($this->datamanager))
        {
            // TODO: non-DM variants
            throw new midgardmvc_exception_notfound("Datamanager not available");
        }
        
        $variant_field = $variant['variant'];
        if (!isset($this->datamanager->types->$variant_field))
        {
            throw new midgardmvc_exception_notfound("{$variant_field} not available");
        }
    }

    private function handle_put(array $variant)
    {
        $this->prepare_variant($variant);
        
        // TODO: Format conversions
        
        $variant_field = $variant['variant'];
        
        // TODO: Pass via widget
        $value = file_get_contents('php://input');
        if (   $variant['type'] == 'html'
            && strpos($value, '</body>') !== false)
        {
            // Clean up
            $xml = simplexml_load_string("<html>{$value}");
            $contents = $xml->xpath("//*/body/*");
            
            $value = '';
            foreach ($contents as $content)
            {
                $value .= $content->asXML();
            }
        }
        
        $this->datamanager->types->$variant_field->value = $value;

        if (!$this->datamanager->save())
        {
            throw new midgardmvc_exception_httperror("Saving {$variant_field} failed");
        }
        
        // Return original content
        return $this->handle_get($variant);
    }

    private function handle_get(array $variant)
    {
        $this->prepare_variant($variant);

        $variant_field = $variant['variant'];
        $type_field = "as_{$variant['type']}";
        if (!isset($this->datamanager->types->$variant_field->$type_field))
        {
            throw new midgardmvc_exception_notfound("Type {$type_field} of {$variant_field} not available");
        }

        // TODO: Other headers
        switch ($variant['type'])
        {
            case 'html':
                midgardmvc_core::get_instance()->context->mimetype = 'text/html';
                break;
            case 'raw':
            case 'csv':
                midgardmvc_core::get_instance()->context->mimetype = 'text/plain';
                break;
            case 'xml':
                midgardmvc_core::get_instance()->context->mimetype = 'text/xml';
                break;
        }
        midgardmvc_core::get_instance()->dispatcher->header('Content-Type: ' . midgardmvc_core::get_instance()->context->mimetype);

        return $this->datamanager->types->$variant_field->$type_field;
    }
    
    public function __set($attribute, $value)
    {
        switch ($attribute)
        {
            case 'datamanager':
                $this->datamanager = $value;
                break;
            case 'object':
                $this->object = $value;
                break;
            default:
                throw new OutOfBoundsException("Midgard MVC variant handler is unable to utilize {$attribute}.");
        }
    }
}
?>
