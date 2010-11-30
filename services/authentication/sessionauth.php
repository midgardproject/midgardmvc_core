<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Provides a session based authentication method.
 * Session and login data is stored to midgardmvc_core_loginsession_db
 *
 * TODO: Refactoring is needed. Perhaps all more advanced authentication
 * methods should inherit the very basic authentication
 *
 * @package midgardmvc_core
 */

class midgardmvc_core_services_authentication_sessionauth implements midgardmvc_core_services_authentication
{
    private $user = null;
    private $person = null;
    private $sitegroup = null;
    private $session_cookie = null;
    
    private $current_session_id = null;
    
    private $trusted_auth = false;
        
    public function __construct()
    {
        $this->session_cookie = new midgardmvc_core_services_authentication_cookie();
        
        if ($this->session_cookie->read_login_session())
        {
            $sessionid = $this->session_cookie->get_session_id();
            $this->authenticate_session($sessionid);
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

    public function login($username, $password, $read_session = true)
    {
        if (   $read_session
            && $this->session_cookie->read_login_session())
        {
            $sessionid = $this->session_cookie->get_session_id();
            return $this->authenticate_session($sessionid);
        }
        return $this->create_login_session($username, $password);
    }
    
    public function trusted_login($username)
    {
        if ($this->session_cookie->read_login_session())
        {
            $sessionid = $this->session_cookie->get_session_id();
            return $this->authenticate_session($sessionid);
        }
        $this->trusted_auth = true;
        return $this->create_login_session($username, $password = '');
    }    
    
    public function is_user()
    {
        if (! $this->user)
        {
            return false;
        }
        
        return true;
    }
    
    public function get_person()
    {
        if (! $this->is_user())
        {
            return null;
        }
        
        if (is_null($this->person))
        {
            $this->person = $this->user->get_person();
            midgardmvc_core::get_instance()->cache->register_object($this->person->guid);
        }
        return $this->person;
    }
    
    public function get_user()
    {
        return $this->user;
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
  
    /**
     * Executes the login to midgard.
     * @param username
     * @param password
     * @return bool 
     */
    private function do_midgard_login($username, $password)
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
            $this->session_cookie->delete_login_session_cookie(); 
            return false;
        }
        
        return true;
    }
    
    /**
     * Function creates the login session entry to the database
     * TODO: Function does not produce any nice exceptions 
     *
     * @param username
     * @param password
     * @clientip determined automatically if not set
     */
    private function create_login_session($username, $password, $clientip = null)
    {
        midgardmvc_core::get_instance()->authorization->enter_sudo('midgardmvc_core');    
    
        if (is_null($clientip))
        {
            if (isset($_SERVER['REMOTE_ADDR']))
            {
                $clientip = $_SERVER['REMOTE_ADDR'];
            }
            else
            {
                // No place like home
                $clientip = '127.0.0.1';
            }
        }
        
        if (! $this->do_midgard_login($username, $password))
        {
            return false;
        }
        
        $session = new midgardmvc_core_login_session();
        $session->userid = $this->user->guid;
        $session->username = $username;
        $session->password = $this->_obfuscate_password($password);
        $session->clientip = $clientip;
        $session->timestamp = time();
        $session->trusted = $this->trusted_auth; // for trusted authentication
        if (! $session->create())
        {
            // TODO: Add some exception?
            return false;
        }

        $result = array
        (
            'session_id' => $session->guid, 
            'user' => &$user // <-- FIXME: is this supposed to be $this->user instead?
        );
        
        $this->current_session_id = $session->guid;
        if (isset($_POST['remember_login']))
        {
            $this->session_cookie->create_login_session_cookie($session->guid, $this->user->guid, time() + 24 * 3600 * 365);
        }
        else
        {
            $this->session_cookie->create_login_session_cookie($session->guid, $this->user->guid);
        }

        midgardmvc_core::get_instance()->authorization->leave_sudo();         

        return $result;
    
    }
    
    /**
     * Function deletes login session row from database and
     * cleans away the cookie
     * TODO: Write the actual functionality
     */
    public function logout()
    {
        if ($this->user)
        {
            $this->user->logout();
        }

        $qb = new midgard_query_builder('midgardmvc_core_login_session');
        $qb->add_constraint('guid', '=', $this->session_cookie->get_session_id());
        $res = $qb->execute();
        $this->session_cookie->delete_login_session_cookie();
        if (! $res)
        {
            return false;
        }

        midgardmvc_core::get_instance()->authorization->enter_sudo('midgardmvc_core');
        $res[0]->delete();
        $res[0]->purge();
        midgardmvc_core::get_instance()->authorization->leave_sudo();

        $this->session_cookie = new midgardmvc_core_services_authentication_cookie();
        return true;
    }
    
    /**
     * This function authenticates a session that has been created 
     * previously with load_login_session (mandatory)
     * 
     * On success ... TODO: Write more
     *
     * If authentication fails, given session id will be deleted
     * from database immediately.
     *
     * @param string $sessionid The session identifier to authenticate against
     * @param bool Indicating success
     */
    public function authenticate_session($sessionid)
    {
        $qb = new midgard_query_builder('midgardmvc_core_login_session');
        $qb->add_constraint('guid', '=', $sessionid);
        $res = $qb->execute();
        if (!$res)
        {
            $this->session_cookie->delete_login_session_cookie();
            return false;
        }
        $session = $res[0];

        $username = $session->username;
        $password = $this->_unobfuscate_password($session->password);
        $this->trusted_auth = $session->trusted;        
        if (! $this->do_midgard_login($username, $password))
        {
            if (!$session->delete())
            {
                // TODO: Throw exception
                // TODO: Sessions must be purged time to time
            }
            $this->session_cookie->delete_login_session_cookie();
            return false;
        }

        $this->current_session_id = $session->guid;
        return true;
    }
    
    public function update_login_session($new_password)
    {
        $pw = $this->_obfuscate_password($new_password);
        $session = new midgardmvc_core_login_session($this->session_cookie->get_session_id());
        $session->password = $pw;
        $session->update();
    }    
    
    /**
     * This function obfuscates a password in some way so that accidential
     * "views" of a password in the database or a log are not immediately
     * a problem. This is not targeted to prevent intrusion, just to prevent
     * somebody viewing the logs or debugging the system is able to just
     * read somebody elses passwords (especially given that many users
     * share their passwords over multiple systems).
     *
     * _unobfuscate_password() is used to restore the password into its original
     * form.
     *
     * @param string $password The password to obfuscate.
     * @return string The obfuscated password.
     * @see _unobfuscate_password()
     * @access private
     */
    
    private function _obfuscate_password($password)
    {
        return base64_encode($password);
    }
    
    /**
     * Reverses password obfuscation.
     *
     * @param string $password The password to obfuscate.
     * @return string The obfuscated password.
     * @see _unobfuscate_password()
     * @access private
     */
    function _unobfuscate_password($password)
    {
        return base64_decode($password);
    }
    
    public function handle_exception(Exception $exception)
    {
        $app = midgardmvc_core::get_instance();
        $request = $app->context->get_request();

        if (   isset($_POST['username']) 
            && isset($_POST['password']))
        {
            if ($this->login($_POST['username'], $_POST['password']))
            {
                // Dispatch again since now we have a user
                $app->dispatcher->dispatch($request);
                return;
            }
        }

        $log_message = str_replace("\n", ' ', $exception->getMessage());
        $app->log(__CLASS__, $log_message, 'info');
        
        // Pass some data to the handler
        $data = array();
        $data['message'] = $exception->getMessage();
        $data['exception'] = $exception;

        $route = $request->get_route();
        $route->template_aliases['root'] = 'midgardmvc-login-form';
        $request->set_route($route);
        
        $request->set_data_item('midgardmvc_core_exceptionhandler', $data);
        $request->set_data_item('cache_enabled', false);

        // Do normal templating
        $app->templating->template($request);
        $app->templating->display($request);
        
        // Clean up and finish
        $app->context->delete();
    }

}

    
