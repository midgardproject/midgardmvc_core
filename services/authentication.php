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
    /**
     * This method is called in the beginning of each request in order to check
     * whether users have a valid login session open.
     *
     * When implementing this in an authentication service, do whatever that service
     * requires for session validation and authenticate as necessary.
     */
    public function check_session();

    public function on_auth_changed();

    public function get_person();
    
    public function is_user();

    /**
     * Authenticate user with the provided tokens.
     * Typical tokens include 'login' and 'password', but may vary depending 
     * on the actual authentication implementation
     */
    public function login(array $tokens);
    
    public function logout();
    
    public function handle_exception(Exception $exception);
}
?>
