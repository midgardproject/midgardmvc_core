<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard MVC cache management controller
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_controllers_cache
{
    public function __construct($request)
    {
        $this->request = $request;
    }
    
    public function get_invalidate(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_user();
        midgardmvc_core::get_instance()->cache->invalidate_all();
        midgardmvc_core::get_instance()->context->cache_enabled = false;
        midgardmvc_core::get_instance()->head->relocate
        (
            midgardmvc_core::get_instance()->dispatcher->generate_url('page_read', array(), $this->request)
        );
    }

    public function post_invalidate(array $args)
    {
        $this->get_invalidate($args);
    }
}
?>
