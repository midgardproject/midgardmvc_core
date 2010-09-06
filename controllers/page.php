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
    public function load_object(array $args)
    {
        $this->object = $this->request->get_node()->get_object();
    }
    
    public function prepare_new_object(array $args)
    {
        $parent = $this->request->get_node()->get_object();
        $this->object = new midgardmvc_core_node();
        $this->object->up = $parent->id;
    }
    
    public function get_url_read()
    {
        return $this->request->get_prefix();
    }
    
    public function get_url_update()
    {
        return midgardmvc_core::get_instance()->dispatcher->generate_url
        (
            $this->request,
            'mvcadmin_crud_update', array
            (
                'type' => 'midgardmvc_core_node', 
                'guid' => $this->object->guid
            )
        );
    }
}
?>
