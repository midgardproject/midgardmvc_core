<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Action introspection controllers
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_controllers_actions
{
    public function __construct(midgardmvc_core_component_interface $instance)
    {
        $this->_core = midgardmvc_core::get_instance();
        $this->configuration = $this->_core->configuration;
    }
    
    public function get_object(array $args)
    {
        $this->_core->authorization->require_user();

        $object = midgard_object_class::get_object_by_guid($args['guid']);
        if (!$object->guid)
        {
            throw new midgardmvc_exception_notfound("Object {$args['guid']} not found");
        }
        
        $this->data['actions'] = $this->_core->componentloader->get_object_actions($object);
    }

    public function get_categories(array $args)
    {
        $this->_core->authorization->require_user();
        
        $this->data['categories'] = $this->_core->componentloader->get_action_categories();
    }

    public function get_category(array $args)
    {
        $this->_core->authorization->require_user();
        
        $categories = $this->_core->componentloader->get_action_categories();
        if (!in_array($args['category'], $categories))
        {
            throw new midgardmvc_exception_notfound("Category {$args['category']} not found");
        }

        $page = new midgardmvc_core_node($args['guid']);
        if (!$page->guid)
        {
            throw new midgardmvc_exception_notfound("Folder {$args['guid']} not found");
        }
        
        $this->data['actions'] = $this->_core->componentloader->get_category_actions($args['category'], $page);
    }
}
?>
