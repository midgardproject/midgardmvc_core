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
        if (!isset(midgardmvc_core::get_instance()->context->node))
        {
            throw new midgardmvc_exception_notfound('No Midgard MVC node found');
        }
        
        $this->object = midgardmvc_core::get_instance()->context->node;
    }
    
    public function prepare_new_object(array $args)
    {
        $this->object = new midgardmvc_core_node();
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
            'mvcadmin_crud_update', array
            (
                'type' => 'midgardmvc_core_node', 
                'guid' => $this->object->guid
            )
        );
    }
}
?>
