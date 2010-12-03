<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Automatic registration authentication service for using Midgard MVC with Midgard Runtime
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_authentication_runtime implements midgardmvc_core_services_authentication
{
    private $user = null;
    private $person = null;
    
    public function __construct()
    {
        if (!isset($_ENV['MIDGARD_ENV_USER_NAME']))
        {
            midgardmvc_core::get_instance()->authentication = new midgardmvc_core_services_authentication_sessionauth();
        }

        // Connect to the Midgard "auth-changed" signal so we can get information from external authentication handlers
        midgardmvc_core::get_instance()->dispatcher->get_midgard_connection()->connect('auth-changed', array($this, 'on_auth_changed_callback'), array());
    }

    public function check_session()
    {
        if (isset($_ENV['MIDGARD_ENV_USER_NAME']))
        {
            $this->autologin();
        }
    }

    private function get_person_by_name($name)
    {
        // We know the real name, try to match to a Midgard Person
        $name_parts = explode(' ', $_ENV['MIDGARD_ENV_REAL_NAME']);
        if (count($name_parts) < 2)
        {
            return null;
        }
        
        $qb = new midgard_query_builder('midgard_person');
        $qb->add_constraint('firstname', '=', $name_parts[0]);
        $qb->add_constraint('lastname', '=', $name_parts[1]);
        $persons = $qb->execute();
        if (count($persons) > 0)
        {
            return $persons[0];
        }
        
        $person = new midgard_person();
        $person->firstname = $name_parts[0];
        $person->lastname = $name_parts[1];
        $person->create();
        return $person;
    }

    private function autologin()
    {
        try
        {
            // Try authenticating if the user already exists
            $this->user = new midgard_user
            (
                array
                (
                    'login' => $_ENV['MIDGARD_ENV_USER_NAME'],
                    'authtype' => 'PAM',
                )
            );
        }
        catch (midgard_error_exception $e)
        {
            // User is missing, create it
            if (isset($_ENV['MIDGARD_ENV_REAL_NAME']))
            {
                $this->person = $this->get_person_by_name($_ENV['MIDGARD_ENV_REAL_NAME']);
            }
            
            $this->user = new midgard_user();
            $this->user->login = $_ENV['MIDGARD_ENV_USER_NAME'];
            $this->user->authtype = 'PAM';
            $this->user->active = true;
            $this->user->usertype = 1;
            $this->user->create();
            
            if (!is_null($this->person))
            {
                $this->user->set_person($this->person);
            }
        }
        $this->user->login();
    }

    /**
     * Signal callback for authentication state change
     */
    public function on_auth_changed_callback()
    {
        midgardmvc_core::get_instance()->authentication->on_auth_changed();
    }

    /**
     * Refresh Midgard MVC internal authentication change information based on authentication state of Midgard Connection
     */
    public function on_auth_changed()
    {
        $this->user = midgardmvc_core::get_instance()->dispatcher->get_midgard_connection()->get_user();
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
            midgardmvc_core::get_instance()->cache->register_object($this->person->guid);
        }
        
        return $this->person;
    }
    
    public function get_user()
    {
        return $this->user;
    }

    public function login($username, $password)
    {
        return false;
    }
    
    public function logout()
    {
        return;
    }
    
    public function handle_exception(Exception $exception)
    {
        // No need to handle exceptions here, pass them on
        throw $exception;
    }
}
?>
