<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * HTTP Basic authentication service for Midgard MVC
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
        midgardmvc_core::get_instance()->dispatcher->get_midgard_connection()->connect('auth-changed', array($this, 'on_auth_changed_callback'), array());
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
            $this->person = new midgard_person($this->user->person);
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
        try
        {
            $user = new midgard_user($this->prepare_tokens($username, $password));
            if ($user->login())
            {
                $this->user = $user;
            }
        }
        catch (Exception $e)
        {
            midgardmvc_core::get_instance()->log(__CLASS__, "Failed authentication attempt for {$username}", 'warning');
            return false;
        }
        
        return true;
    }


    private function prepare_tokens($username, $password)
    {
        $auth_type = midgardmvc_core::get_instance()->configuration->get('services_authentication_authtype');
        $login_tokens = array
        (
            'login' => $username,
            'authtype' => $auth_type,
        );
        switch ($auth_type)
        {
            case 'Plaintext':
                // Compare plaintext to plaintext
                $login_tokens['password'] = $password;
                break;
            case 'SHA1':
                $login_tokens['password'] = sha1($password);
                break;
            case 'SHA256':
                $login_tokens['password'] = hash('sha256', $password);
                break;
            case 'MD5':
                $login_tokens['password'] = md5($password);
                break;
            default:
                throw new midgardmvc_exception_httperror('Unsupported authentication type attempted', 500);
        }
        // TODO: Support other types
        
        return $login_tokens;
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
            $app = midgardmvc_core::get_instance();
            $app->dispatcher->header("WWW-Authenticate: Basic realm=\"Midgard\"");
            $app->dispatcher->header('HTTP/1.0 401 Unauthorized');
            // TODO: more fancy 401 output ?
            echo "<h1>Authorization required</h1>\n";
            // Clean up the context
            $app->context->delete();
            $app->dispatcher->end_request();
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
