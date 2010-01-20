<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Page management controller
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_controllers_page extends midgardmvc_core_controllers_baseclasses_crud
{
    public function __construct(midgardmvc_core_component_interface $instance)
    {
        $this->configuration =& midgardmvc_core::get_instance()->configuration;
    }

    public function load_object(array $args)
    {
        if (!isset(midgardmvc_core::get_instance()->context->page->id))
        {
            throw new midgardmvc_exception_notfound('No Midgard page found');
        }
        
        $this->object = midgardmvc_core::get_instance()->context->page;
    }
    
    public function prepare_new_object(array $args)
    {
        $this->object = new midgard_page();
        $this->object->up = midgardmvc_core::get_instance()->context->page->id;
        $this->object->info = 'active';
    }
    
    public function get_url_read()
    {
        return midgardmvc_core::get_instance()->context->prefix;
    }
    
    public function get_url_update()
    {
        return midgardmvc_core::get_instance()->dispatcher->generate_url('page_update', array());
    }

    public function get_read(array $args)
    {
        parent::get_read($args);
        
        // Neutron introspection file
        midgardmvc_core::get_instance()->head->add_link_head
        (
            array
            (
                'rel' => 'neutron-introspection',
                'type' => 'application/neutron+xml',
                'href' => midgardmvc_core::get_instance()->dispatcher->generate_url
                (
                    'page_variants', array
                    (
                        'variant' => array
                        (
                            'identifier' => 'page',
                            'variant' => 'neutron-introspection',
                            'type' => 'xml',
                        )
                    )
                )
            )
        );

        if (midgardmvc_core::get_instance()->context->route_id == 'page_variants')
        {
            // Get variant of the page
            $variant = new midgardmvc_core_helpers_variants();
            $variant->datamanager = $this->data['datamanager'];
            $variant->object = $this->data['object'];
            echo $variant->handle($args['variant'], midgardmvc_core::get_instance()->context->request_method);
            die();
        }
    }

    public function post_read(array $args)
    {
        $this->get_read($args);
    }

    public function put_read(array $args)
    {
        parent::get_read($args);
        
        midgardmvc_core::get_instance()->authorization->require_do('midgard:update', $this->data['object']);

        // Get variant of the page
        $variant = new midgardmvc_core_helpers_variants();
        $variant->datamanager = $this->data['datamanager'];
        $variant->object = $this->data['object'];
        echo $variant->handle($args['variant'], $this->dispatcher->request_method);
        die();
    }

    public function mkcol_read(array $args)
    {
        parent::get_read($args);

        // Create subpage
        midgardmvc_core::get_instance()->authorization->require_do('midgard:create', $this->data['object']);
        $this->prepare_new_object($args);
        $this->object->name = $args['name']['identifier'];    
        $this->object->create();
    }
}
?>
