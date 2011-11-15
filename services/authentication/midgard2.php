<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Authentication service helpers for Midgard2
 *
 * @package midgardmvc_core
 */
abstract class midgardmvc_core_services_authentication_midgard2 implements midgardmvc_core_services_authentication
{
    protected $supported_authtypes = array();
    protected $user = null;
    protected $person = null;

    public function __construct()
    {
        if (!extension_loaded('midgard2'))
        {
            return;
        }

        // Connect to the Midgard2 "auth-changed" signal so we can get information from external authentication handlers
        midgardmvc_core::get_instance()->dispatcher->get_midgard_connection()->connect('auth-changed', array($this, 'on_auth_changed'), array());

        $this->supported_authtypes = midgardmvc_core::get_instance()->configuration->services_authentication_authtypes;
    }

    /**
     * Signal callback for authentication state change
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
        }
        
        return $this->person;
    }
    
    public function get_user()
    {
        return $this->user;
    }

    protected function hash_password(array $tokens)
    {
        if (!isset($tokens['password']))
        {
            throw new InvalidArgumentException("{$tokens['authtype']} authentication type requires a password");
        }

        switch ($tokens['authtype'])
        {
            case 'SHA1':
                $tokens['password'] = sha1($tokens['password']);
                break;
            case 'SHA256':
                $tokens['password'] = hash('sha256', $tokens['password']);
                break;
            case 'MD5':
                $tokens['password'] = md5($tokens['password']);
                break;
        }
        return $tokens;
    }

    protected function prepare_tokens(array $tokens)
    {
        if (   !isset($tokens['authtype'])
            || empty($tokens['authtype']))
        {
            $tokens['authtype'] = midgardmvc_core::get_instance()->configuration->services_authentication_authtype;
        }

        if (!in_array($tokens['authtype'], $this->supported_authtypes))
        {
            throw new midgardmvc_exception_httperror("Unsupported authentication type '{$tokens['authtype']}' attempted", 500);
        }

        // Handle hashing of password for the authtypes that need it
        switch ($tokens['authtype'])
        {
            case 'Plaintext':
            case 'SHA1':
            case 'SHA256':
            case 'MD5':
                $tokens = $this->hash_password($tokens);
                break;
        }

        // Ensure that only active accounts can log in
        $tokens['active'] = true;
        
        return $tokens;
    }

    /**
     * Executes the login to Midgard2.
     */
    protected function do_midgard_login(array $tokens)
    {
        try
        {
            $tokens = $this->prepare_tokens($tokens);
            $user = new midgard_user($tokens);
            if ($user->login())
            {
                $this->user = $user;
            }
        }
        catch (midgard_error_exception $e)
        {
            midgardmvc_core::get_instance()->log(__CLASS__, "Failed authentication attempt for {$tokens['login']}: " . $e->getMessage(), 'warning');
            midgardmvc_core::get_instance()->context->get_request()->set_data_item
            (
                'midgardmvc_core_services_authentication_message', 
                midgardmvc_core::get_instance()->i18n->get('authentication failed', 'midgardmvc_core')
            );
            return false;
        }
        catch (Exception $e)
        {
            midgardmvc_core::get_instance()->log(__CLASS__, "Failed authentication attempt for {$tokens['login']}: " . $e->getMessage(), 'warning');
            midgardmvc_core::get_instance()->context->get_request()->set_data_item
            (
                'midgardmvc_core_services_authentication_message', 
                midgardmvc_core::get_instance()->i18n->get('authentication failed: ' . $e->getMessage(), 'midgardmvc_core')
            );
            return false;
        }
        
        return true;
    }
}
