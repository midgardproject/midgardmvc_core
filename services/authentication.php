<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Authentication interface for Midgard MVC
 *
 * @package midgardmvc_core
 */
interface midgardmvc_core_services_authentication
{
    public function on_auth_changed();

    public function get_person();
    
    public function is_user();
    
    public function login($username, $password);
    
    public function logout();
    
    public function handle_exception(Exception $exception);
}
?>
