<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Event observation interface for Midgard MVC
 *
 * @package midgardmvc_core
 */
interface midgardmvc_core_services_observation
{
    public function add_listener($callback, array $events, array $types = null, array $data = null);
    
    public function get_listeners();
}
