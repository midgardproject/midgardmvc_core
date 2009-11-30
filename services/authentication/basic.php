<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * HTTP Basic authentication service for MidCOM
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_authentication_basic implements midgardmvc_core_services_authentication
{
    private $user = null;
    private $person = null;
    private $sitegroup = null;
    
    public function __construct()
    {
        if (isset($_SERVER['PHP_AUTH_USER']))
        {
            $this->login($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
        }

        // Connect to the Midgard "auth-changed" signal so we can get information from external authentication handlers
        midgardmvc_core_midcom::get_instance()->dispatcher->get_midgard_connection()->connect('auth-changed', array($this, 'on_auth_changed_callback'), array());
    }

    /**
     * Signal callback for authentication state change
     */
    public function on_auth_changed_callback()
    {
        midgardmvc_core_midcom::get_instance()->authentication->on_auth_changed();
    }

    /**
     * Refresh MidCOM internal authentication change information based on authentication state of Midgard Connection
     */
    public function on_auth_changed()
    {
        $this->user = midgardmvc_core_midcom::get_instance()->dispatcher->get_midgard_connection()->get_user();
    }

    public function is_user()
    {
        if (!$this->user)
        {
            return false;
        }
        
        return true;
    }
    
    public function get_person()
    {
        if (!$this->is_user())
        {
            return null;
        }
        
        if (is_null($this->person))
        {
            $this->person = new midgard_person($this->user->guid);
            midgardmvc_core_midcom::get_instance()->cache->register_object($this->person->guid);
        }
        
        return $this->person;
    }
    
    public function get_user()
    {
        return $this->user;
    }

    public function login($username, $password)
    {
        if (!$this->sitegroup)
        {
            // In Midgard2 we need current SG name for authentication
            $this->sitegroup = midgardmvc_core_midcom::get_instance()->dispatcher->get_midgard_connection()->get_sitegroup();
        }
        
        $this->user = midgard_user::auth($username, $password, $this->sitegroup);
        
        if (!$this->user)
        {
            midgardmvc_core_midcom::get_instance()->log(__CLASS__, "Failed authentication attempt for {$username}", 'warning');
            return false;
        }
        
        return true;
    }
    
    public function logout()
    {
        // TODO: Can this be implemented for Basic auth?
        return;
    }
    
    public function handle_exception(Exception $exception)
    {
        if (!isset($_SERVER['PHP_AUTH_USER']))
        {
            header("WWW-Authenticate: Basic realm=\"Midgard\"");
            header('HTTP/1.0 401 Unauthorized');
            // TODO: more fancy 401 output ?
            echo "<h1>Authorization required</h1>\n";
            exit();
        }

        if (!$this->login($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']))
        {
            // Wrong password: Recurse until auth ok or user gives up
            unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
            $this->handle_exception($exception);
        }
    }
}
?>
