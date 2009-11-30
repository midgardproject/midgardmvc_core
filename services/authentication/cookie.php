<?php

/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
 
/**
 * Provides a cookie setting and validation functionality
 * for cookie based authentication service
 * Cookie path is set by the midcom_service_sessionauth_cookie_path
 *
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_authentication_cookie
{
    private $_cookie_id = 'midgardmvc_services_auth_backend_simple-';
    protected $session_id = null;
    protected $user_id = null;
    
    /**
      * Reads session data from the cookie. It also makes
      * some simple checks for the validity of the cookie data
      */
    public function read_login_session()
    {
        $reset_cookie = false;
        if (   array_key_exists($this->_cookie_id, midgardmvc_core::get_instance()->dispatcher->get)
            && !array_key_exists($this->_cookie_id, $_COOKIE))
        {
            $reset_cookie = true;
        }

        if (!array_key_exists($this->_cookie_id, $_COOKIE))
        {
            return false;
        }

        $data = explode(':', $_COOKIE[$this->_cookie_id]);
        if (count($data) != 2)
        {
            $this->delete_cookie();
            return false;
        }
    
        $this->session_id = $data[0];
        $this->user_id = $data[1];
        
        if ($reset_cookie)
        {
            $this->set_cookie();
        }
  
        return true;
    }
    
    private function set_cookie()
    {
        setcookie
        (
            $this->_cookie_id,
            "{$this->session_id}:{$this->user_id}",
            0,
            midgardmvc_core::get_instance()->configuration->services_authentication_cookie_cookiepath
        );
    }
    
    public function get_session_id()
    {
        return $this->session_id;
    }
    
    private function delete_cookie()
    {
        setcookie(
                    $this->_cookie_id,
                    false,
                    time()-86400,
                    midgardmvc_core::get_instance()->configuration->services_authentication_cookie_cookiepath
                );
    }
    
    public function create_login_session_cookie($session_id, $user_id)
    {
        $this->session_id = $session_id;
        $this->user_id = $user_id;
        $this->set_cookie();
    }
    
    public function delete_login_session_cookie()
    {
        $this->delete_cookie();
    }
}
?>
