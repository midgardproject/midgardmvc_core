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
 * @package midgardmvc_core
 */

class midgardmvc_core_services_authentication_sessionauth extends midgardmvc_core_services_authentication_midgard2
{
    protected $session_cookie = null;
    
    protected $current_session_id = null;
    
    private $trusted_auth = false;
        
    public function __construct()
    {
        $this->session_cookie = new midgardmvc_core_services_authentication_cookie();
        parent::__construct();
    }

    public function check_session()
    {
        $this->user = null;
        $this->person = null;
        if ($this->session_cookie->read_login_session())
        {
            $sessionid = $this->session_cookie->get_session_id();
            $this->authenticate_session($sessionid);
        }
    }

    public function login(array $tokens)
    {
        if (!isset($tokens['login']))
        {
            throw new InvalidArgumentException('Login tokens need to provide a login');
        }

        return $this->create_login_session($tokens);
    }
    
    public function trusted_login(array $tokens)
    {
        if ($this->session_cookie->read_login_session())
        {
            $sessionid = $this->session_cookie->get_session_id();
            return $this->authenticate_session($sessionid);
        }
        $this->trusted_auth = true;

        if (!isset($tokens['login']))
        {
            throw new InvalidArgumentException("Trusted login tokens need to provide a login");
        }

        if (isset($tokens['password']))
        {
            unset($tokens['password']);
        }

        return $this->create_login_session($tokens);
    }

    /**
     * Function creates the login session entry to the database
     * TODO: Function does not produce any nice exceptions 
     */
    protected function create_login_session(array $tokens, $clientip = null)
    {
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
        
        if (!$this->do_midgard_login($tokens))
        {
            return false;
        }

        // Create session to DB
        midgardmvc_core::get_instance()->authorization->enter_sudo('midgardmvc_core');
        $session = new midgardmvc_core_login_session();
        $session->userid = $this->user->guid;
        $session->username = $tokens['login'];
        if (isset($tokens['password']))
        {
            $session->password = $this->_obfuscate_password($tokens['password']);
        }
        if (isset($tokens['authtype']))
        {
            $session->authtype = $tokens['authtype'];
        }
        $session->clientip = $clientip;
        $session->timestamp = time();
        $session->trusted = $this->trusted_auth; // for trusted authentication
        if (!$session->create())
        {
            midgardmvc_core::get_instance()->authorization->leave_sudo();
            midgardmvc_core::get_instance()->context->get_request()->set_data_item(
                'midgardmvc_core_services_authentication_message', 
                midgardmvc_core::get_instance()->i18n->get('authentication session creation failed', 'midgardmvc_core')
            );
            return false;
        }
        midgardmvc_core::get_instance()->authorization->leave_sudo();

        $result = array
        (
            'session_id' => $session->guid, 
            'user' => $this->user->guid,
        );
        
        $this->current_session_id = $session->guid;

        // By default the session expires when browser is closed
        $expire_session = 0;
        if (isset($_POST['remember_login']))
        {
            $expire_session = time() + 24 * 3600 * 365;
        }
        $this->session_cookie->create_login_session_cookie($session->guid, $this->user->guid, $expire_session);

        return $result;
    
    }
    
    /**
     * Function deletes login session row from database and
     * cleans away the cookie
     */
    public function logout()
    {
        if ($this->user)
        {
            $this->user->logout();
        }

        // Remove session cookie from browser
        $this->session_cookie->delete_login_session_cookie();

        // Delete login session from DB
        midgardmvc_core::get_instance()->authorization->enter_sudo('midgardmvc_core');
        $qb = new midgard_query_builder('midgardmvc_core_login_session');
        $qb->add_constraint('guid', '=', $this->session_cookie->get_session_id());
        $res = $qb->execute();
        if (!$res)
        {
            return false;
        }
        $res[0]->delete();
        $res[0]->purge();
        midgardmvc_core::get_instance()->authorization->leave_sudo();

        // Initialize a fresh session cookie handler
        $this->session_cookie = new midgardmvc_core_services_authentication_cookie();
        return true;
    }
    
    /**
     * This function authenticates a session that has been created 
     * previously with load_login_session (mandatory)
     *
     * If authentication fails, given session id will be deleted
     * from database immediately.
     *
     * @param string $sessionid The session identifier to authenticate against
     * @param bool Indicating success
     */
    public function authenticate_session($sessionid)
    {
        try
        {
            $session = new midgardmvc_core_login_session($sessionid);
        }
        catch (midgard_error_exception $e)
        {
            midgardmvc_core::get_instance()->log(__CLASS__, "Failed to read session {$sessionid}", 'warning');
            $this->session_cookie->delete_login_session_cookie();
            return false;
        }

        $tokens = array
        (
            'login' => $session->username,
            'password' => $this->_unobfuscate_password($session->password),
            'authtype' => $session->authtype,
        );
        $this->trusted_auth = $session->trusted;
        if (!$this->do_midgard_login($tokens))
        {
            // Session information was invalid, delete it
            $this->session_cookie->delete_login_session_cookie();

            midgardmvc_core::get_instance()->authorization->enter_sudo('midgardmvc_core');
            $session->delete();
            $session->purge();
            midgardmvc_core::get_instance()->authorization->leave_sudo();
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
    private function _unobfuscate_password($password)
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
            $tokens = array
            (
                'login' => $_POST['username'],
                'password' => $_POST['password'],
            );

            if ($this->login($tokens))
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
        $request->set_data_item
        (
            'midgardmvc_core_services_authentication_message', 
            $data['message']
        );

        $request->set_data_item('cache_enabled', false);

        // Do normal templating
        $app->templating->template($request);
        $app->templating->display($request);
        
        // Clean up and finish
        $app->context->delete();
    }

}

    
