<?php
/**
 * @package midcom_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM cache management controller
 *
 * @package midcom_core
 */
class midcom_core_controllers_cache
{
    public function __construct(midcom_core_component_interface $instance)
    {
        $this->configuration = midcom_core_midcom::get_instance()->configuration;
    }
    
    public function get_invalidate(array $args)
    {
        midcom_core_midcom::get_instance()->authorization->require_user();
        midcom_core_midcom::get_instance()->cache->invalidate_all();
        midcom_core_midcom::get_instance()->context->cache_enabled = false;
        midcom_core_midcom::get_instance()->head->relocate
        (
            midcom_core_midcom::get_instance()->dispatcher->generate_url('page_read', array())
        );
    }

    public function post_invalidate(array $args)
    {
        $this->get_invalidate($args);
    }
}
?>
