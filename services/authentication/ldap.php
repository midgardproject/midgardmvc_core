<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Provides a session based authentication method that validates logins with an LDAP service
 *
 * @package midgardmvc_core
 */

class midgardmvc_core_services_authentication_ldap extends midgardmvc_core_services_authentication_sessionauth
{
    private $server = '';
    private $dn = '';

    public function __construct()
    {
        if (!extension_loaded('ldap'))
        {
            throw new Exception('The LDAP authentication service requires "ldap" PHP extension to be present.');
        }

        if (!isset(midgardmvc_core::get_instance()->configuration->services_authentication_ldap))
        {
            throw new Exception('The LDAP authentication service requires configuration key "services_authentication_ldap" to be set with subkeys "server" and "dn"');
        }

        if (!in_array('LDAP', midgardmvc_core::get_instance()->configuration->services_authentication_authtypes))
        {
            throw new Exception('LDAP has to be enabled in allowed authentication types to use the LDAP authentication service.');
        }

        $ldap_settings = midgardmvc_core::get_instance()->configuration->services_authentication_ldap;
        $this->server = $ldap_settings['server'];
        $this->dn = $ldap_settings['dn'];

        parent::__construct();
    }

    /**
     * Validate user against LDAP and then generate a session 
     */
    protected function create_login_session(array $tokens, $clientip = null)
    {
        // Validate user against LDAP
        $ldapuser = $this->ldap_authenticate($tokens);
        if (!$ldapuser)
        {
            return false;
        }

        // LDAP authentication handled, we don't need the password any longer
        unset($tokens['password']);

        // If user is already in DB we can just log in
        if (parent::create_login_session($tokens, $clientip))
        {
            return true;
        }

        // Otherwise we need to create the necessary Midgard account
        if (!$this->create_account($ldapuser, $tokens))
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

    private function create_account(array $ldapuser, array $tokens)
    {
        midgardmvc_core::get_instance()->authorization->enter_sudo('midgardmvc_core'); 
        $transaction = new midgard_transaction();
        $transaction->begin();

        $qb = new midgard_query_builder('midgard_person');
        $qb->add_constraint('firstname', '=', $ldapuser['firstname']);
        $qb->add_constraint('lastname', '=', $ldapuser['email']);
        $persons = $qb->execute();
        if (count($persons) == 0)
        {
            $person = new midgard_person();
            $person->firstname = $ldapuser['firstname'];
            $person->lastname = $ldapuser['email'];
            if (!$person->create())
            {
                midgardmvc_core::get_instance()->log
                (
                    __CLASS__,
                    "Creating midgard_person for LDAP user failed: " . midgard_connection::get_instance()->get_error_string(),
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
        $user->authtype = 'LDAP';
        $user->active = true;
        $user->set_person($person);
        if (!$user->create())
        {
            midgardmvc_core::get_instance()->log
            (
                __CLASS__,
                "Creating midgard_user for LDAP user failed: " . midgard_connection::get_instance()->get_error_string(),
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

    /**
     * Performs an LDAP search
     *
     * @param string username to search for in LDAP
     *
     * @return Array with username (uid), firstname (cn) and email (mail) coming from LDAP
     */
    private function ldap_search($ldap_connection, $username)
    {
        $sr = ldap_search($ldap_connection, $this->dn, "uid={$username}");
        $info = ldap_get_entries($ldap_connection, $sr);

        if ($info['count'] == 0)
        {
            midgardmvc_core::get_instance()->context->get_request()->set_data_item(
                'midgardmvc_core_services_authentication_message', 
                midgardmvc_core::get_instance()->i18n->get('ldap authentication failed: no user information found', 'midgardmvc_core')
            );

            return null;
        }

        return array
        (
            'username' => $info[0]['uid'][0],
            'firstname' => $info[0]['cn'][0],
            'email' => $info[0]['mail'][0],
            'employeenumber' => $info[0]['employeenumber'][0],
        );
    }

    /**
     * Performs an LDAP bind; ie. authenticates
     *
     * @return Array with username (uid), firstname (cn) and email (mail) coming from LDAP
     */
    private function ldap_authenticate(array $tokens)
    {
        if (   !isset($tokens['login'])
            || !isset($tokens['password']))
        {
            midgardmvc_core::get_instance()->context->get_request()->set_data_item(
                'midgardmvc_core_services_authentication_message', 
                midgardmvc_core::get_instance()->i18n->get('ldap authentication requires login and password', 'midgardmvc_core')
            );

            return null;
        }

        $ds = ldap_connect($this->server);
        if (!$ds)
        {
            midgardmvc_core::get_instance()->context->get_request()->set_data_item(
                'midgardmvc_core_services_authentication_message', 
                midgardmvc_core::get_instance()->i18n->get('ldap authentication failed: no connection to server', 'midgardmvc_core')
            );

            return null;
        }

        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);

        if (@ldap_bind($ds, "cn={$tokens['login']},{$this->dn}", $tokens['password'])) 
        {
            // Valid account
            $userinfo = $this->ldap_search($ds, $tokens['login']);
            ldap_close($ds);
            return $userinfo;
        }

        ldap_close($ds);
        midgardmvc_core::get_instance()->context->get_request()->set_data_item(
            'midgardmvc_core_services_authentication_message', 
            midgardmvc_core::get_instance()->i18n->get('ldap authentication failed: login and password don\'t match', 'midgardmvc_core')
        );

        return null;
    }
}
