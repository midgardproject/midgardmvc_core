<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Authorization interface for MidCOM 3
 *
 * @package midgardmvc_core
 */
interface midgardmvc_core_services_authorization
{
    /**
     * Checks whether a user has a certain privilege on the given content object.
     * Works on the currently authenticated user by default, but can take another
     * user as an optional argument.
     *
     * @param string $privilege The privilege to check for
     * @param MidgardObject &$content_object A Midgard Content Object
     * @param midgardmvc_core_user $user The user against which to check the privilege, defaults to the currently authenticated user.
     *     You may specify "EVERYONE" instead of an object to check what an anonymous user can do.
     * @return boolean true if the privilege has been granted, false otherwise.
     */
    public function can_do($privilege, $object, $user = null);

    /**
     * Ensure user is permitted to do given operation. Causes an "unauthorized" exception
     * to be thrown if the action is not valid.
     */
    public function require_do($privilege, $object, $user = null);

    /**
     * An user must be logged in to this request
     */
    public function require_user();

    /**
     * Administrator must be logged in to this request
     */
    public function require_admin();

    /**
     * Enter into SUDO mode. Component is required here for access control purposes as SUDO might be disabled for some parts
     */
    public function enter_sudo($component);
    
    /**
     * Leave SUDO mode
     */
    public function leave_sudo();
}
?>