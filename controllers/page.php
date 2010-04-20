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
    }
    
    public function get_url_read()
    {
        return midgardmvc_core::get_instance()->context->prefix;
    }
    
    public function get_url_update()
    {
        return midgardmvc_core::get_instance()->dispatcher->generate_url
        (
            'asgard_crud_update', array
            (
                'type' => 'midgard_page', 
                'guid' => $this->object->guid
            )
        );
    }
}
?>
