<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Simple authorization interface for Midgard MVC. Unauthenticated users are given read access,
 * and authenticated users that own the object write access.
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_authorization_owner implements midgardmvc_core_services_authorization
{
    private $sudo = false;
    private $sudo_stack = array(
        
    );
    
    /**
     * Starts up the authorization service and connects to various signals
     */
    public function __construct()
    {
        $this->connect_to_signals();
    }
    
    private function connect_to_signals($class)
    {
        midgardmvc_core::get_instance()->observation->add_listener
        (
            array($this, 'on_loading'),
            array('action-loaded-hook')
        );
        midgardmvc_core::get_instance()->observation->add_listener
        (
            array($this, 'on_creating'),
            array('action-create-hook')
        );
        midgardmvc_core::get_instance()->observation->add_listener
        (
            array($this, 'on_updating'),
            array('action-update-hook')
        );
        midgardmvc_core::get_instance()->observation->add_listener
        (
            array($this, 'on_deleting'),
            array('action-delete-hook')
        );
    }
    
    public function on_loading($object)
    {
        if (! midgardmvc_core::get_instance()->authorization->can_do('midgard:read', $object))
        {
            // Note: this is a *hook* so the object is still empty
            throw new midgardmvc_exception_unauthorized("Not authorized to read " . get_class($object));
        }
    }
    
    public function on_creating($object)
    {
        if (! midgardmvc_core::get_instance()->authorization->can_do('midgard:create', $object))
        {
            throw new midgardmvc_exception_unauthorized("Not authorized to create " . get_class($object) . " {$object->guid}");
        }
    }
    
    public function on_updating($object)
    {
        if (! midgardmvc_core::get_instance()->authorization->can_do('midgard:update', $object))
        {
            throw new midgardmvc_exception_unauthorized("Not authorized to update " . get_class($object) . " {$object->guid}");
        }
    }
    
    public function on_deleting($object)
    {
        if (! midgardmvc_core::get_instance()->authorization->can_do('midgard:delete', $object))
        {
            throw new midgardmvc_exception_unauthorized("Not authorized to delete " . get_class($object) . " {$object->guid}");
        }
    }
    
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
    public function can_do($privilege, $object, $user = null)
    {
        if ($this->sudo)
        {
            return true;
        }
        
        switch ($privilege)
        {
            case 'midgard:read' :
                return true;
            break;
            case 'midgard:create' :
                if (midgardmvc_core::get_instance()->authentication->is_user())
                {
                    return true;
                }
                break;
            case 'midgard:update':
            case 'midgard:delete':
                if (midgardmvc_core::get_instance()->authentication->is_user())
                {
                    $person = midgardmvc_core::get_instance()->authentication->get_person();
                    if($person->guid == $object->metadata->creator)
                    {
                        return true;
                    }
                }
                return false;
                break;
            default:
                return false;
                break;       
        }
        
        return false;
    }
    
    public function require_do($privilege, $object, $user = null)
    {
        if (! $this->can_do($privilege, $object, $user))
        {
            throw new midgardmvc_exception_unauthorized("Not authorized to {$privilege} " . get_class($object) . " {$object->guid}");
        }
    }
    
    public function require_user()
    {
        if (! midgardmvc_core::get_instance()->authentication->is_user())
        {
            throw new midgardmvc_exception_unauthorized("Authentication required");
        }
    }

    public function require_admin()
    {
        $this->require_user();
        
        if (!$_MIDGARD['admin'])
        {
            throw new midgardmvc_exception_unauthorized("Administrative privileges required");   
        }
    }

    /**
     * Enter into SUDO mode. Component is required here for access control purposes as SUDO might be disabled for some parts
     */
    public function enter_sudo($component)
    {
        // TODO: Check per-component access control
        $this->sudo = true;
        
        $this->sudo_stack[] = $component;
        
        return $this->sudo;
    }
    
    /**
     * Leave SUDO mode
     */
    public function leave_sudo()
    {
        array_pop($this->sudo_stack);
        
        if (empty($this->sudo_stack))
        {
            $this->sudo = false;
        }
    }
}
?>
