<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Provides a session based authentication method that validates logins with
 * BrowserID
 *
 * @link https://persona.org/
 * @package midgardmvc_core
 */
class midgardmvc_core_services_authentication_browserid extends midgardmvc_core_services_authentication_sessionauth
{
    private $provider = 'https://verifier.login.persona.org/verify';
    private $include = 'https://persona.org/include.js';

    public function __construct()
    {
        if (!in_array('BrowserID', midgardmvc_core::get_instance()->configuration->services_authentication_authtypes))
        {
            throw new Exception('BrowserID has to be enabled in allowed authentication types to use the BrowserID authentication service.');
        }

        if (isset(midgardmvc_core::get_instance()->configuration->services_authentication_browserid))
        {
            $browserid_settings = midgardmvc_core::get_instance()->configuration->services_authentication_browserid;
            if (isset($browserid_settings['provider']))
            {
                $this->provider = $browserid_settings['provider'];
            }

            if (isset($browserid_settings['include']))
            {
                $this->include = $browserid_settings['include'];
            }
        }

        parent::__construct();
    }

    public function check_session()
    {
        parent::check_session();

        if (!$this->user)
        {
            midgardmvc_core::get_instance()->head->add_jsfile($this->include);
        }
    }

    /**
     * Validate user against BrowserID provider and then generate a session 
     */
    protected function create_login_session(array $tokens, $clientip = null)
    {
        // Validate user against LDAP
        $browseriduser = $this->browserid_authenticate($tokens);
        if (!$browseriduser)
        {
            return false;
        }

        // BrowserId authentication handled, we don't need the password any longer
        unset($tokens['password']);
        $tokens['login'] = $browseriduser->email;
        $tokens['authtype'] = 'BrowserID';

        // If user is already in DB we can just log in
        if (parent::create_login_session($tokens, $clientip))
        {
            return true;
        }

        // Otherwise we need to create the necessary Midgard account
        if (!$this->create_account($tokens))
        {
            midgardmvc_core::get_instance()->context->get_request()->set_data_item
            (
                'midgardmvc_core_services_authentication_message', 
                midgardmvc_core::get_instance()->i18n->get('midgard account creation failed', 'midgardmvc_core')
            );
            return false;
        }

        // ..and log in
        return parent::create_login_session($tokens, $clientip);
    }

    private function create_account(array $tokens)
    {
        midgardmvc_core::get_instance()->authorization->enter_sudo('midgardmvc_core'); 
        $transaction = new midgard_transaction();
        $transaction->begin();

        $qb = new midgard_query_builder('midgard_person');
        $qb->add_constraint('lastname', '=', $tokens['login']);
        $persons = $qb->execute();
        if (count($persons) == 0)
        {
            $person = new midgard_person();
            $person->lastname = $tokens['login'];
            if (!$person->create())
            {
                midgardmvc_core::get_instance()->log
                (
                    __CLASS__,
                    "Creating midgard_person for BrowserID user failed: " . midgard_connection::get_instance()->get_error_string(),
                    'warning'
                );

                $transaction->rollback();
                midgardmvc_core::get_instance()->authorization->leave_sudo();
                return false;
            }
        }
        else
        {
            $person = $persons[0];
        }

        $user = new midgard_user();
        $user->login = $tokens['login'];
        $user->password = '';
        $user->usertype = 1;
        $user->authtype = 'BrowserID';
        $user->active = true;
        $user->set_person($person);
        if (!$user->create())
        {
            midgardmvc_core::get_instance()->log
            (
                __CLASS__,
                "Creating midgard_user for BrowserID user failed: " . midgard_connection::get_instance()->get_error_string(),
                'warning'
            );

            $transaction->rollback();   
            midgardmvc_core::get_instance()->authorization->leave_sudo();
            return false;
        }

        if (!$transaction->commit())
        {
            midgardmvc_core::get_instance()->authorization->leave_sudo();
            return false;
        }
        midgardmvc_core::get_instance()->authorization->leave_sudo();
        return true;
    }

    private function browserid_authenticate(array $tokens)
    {
        if (!isset($tokens['password']))
        {
            midgardmvc_core::get_instance()->context->get_request()->set_data_item(
                'midgardmvc_core_services_authentication_message', 
                midgardmvc_core::get_instance()->i18n->get('browserid authentication requires an assertion', 'midgardmvc_core')
            );

            return null;
        }

        $ctx = stream_context_create
        (
            array
            (
                'http' => array
                (
                    'method' => 'POST',
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'content' => http_build_query
                    (
                        array
                        (
                            'assertion' => $tokens['password'],
                            'audience' => "{$_SERVER['SERVER_NAME']}:{$_SERVER['SERVER_PORT']}"
                        )
                    )
                )
            )
        );

        $content = file_get_contents($this->provider, false, $ctx);
        if (!$content)
        {
            midgardmvc_core::get_instance()->context->get_request()->set_data_item(
                'midgardmvc_core_services_authentication_message', 
                midgardmvc_core::get_instance()->i18n->get('unable to connect to browserid provider', 'midgardmvc_core')
            );

            return null;
        }

        $verification = @json_decode($content);
        if (   !$verification
            || !is_object($verification)
            || !isset($verification->status)
            || $verification->status != 'okay'
            || !isset($verification->email))
        {
            midgardmvc_core::get_instance()->context->get_request()->set_data_item(
                'midgardmvc_core_services_authentication_message', 
                midgardmvc_core::get_instance()->i18n->get('browserid verification failed', 'midgardmvc_core')
            );

            return null;
        }

        return $verification;
    }
}
