<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Comet listeners controller
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_controllers_authentication
{
    public function __construct()
    {
        $this->configuration = midgardmvc_core::get_instance()->configuration;
    }
    
    public function get_logout(array $args)
    {
        midgardmvc_core::get_instance()->authentication->logout();
        midgardmvc_core::get_instance()->head->relocate('/');
    }
    
    public function get_login(array $args)
    {
        if (!isset($this->data['redirect_url']))
        {
            $this->data['redirect_url'] = '/';

            if (isset($_GET['redirect']))
            {
                $this->data['redirect_url'] = $_GET['redirect'];
            }
        }

        if (midgardmvc_core::get_instance()->authentication->is_user())
        {
            midgardmvc_core::get_instance()->head->relocate($this->data['redirect_url']);
        }

        $exception_data = array();
        $exception_data['message'] = midgardmvc_core::get_instance()->i18n->get
        (
            'please enter your username and password', 'midgardmvc_core'
        );
        midgardmvc_core::get_instance()->context->midgardmvc_core_exceptionhandler = $exception_data;
    }

    public function post_login(array $args)
    {
        if (isset($_POST['redirect']))
        {
            $this->data['redirect_url'] = $_POST['redirect'];
        }

        if (   isset($_POST['username']) 
            && isset($_POST['password']))
        {
            $tokens = array
            (
                'login' => $_POST['username'],
                'password' => $_POST['password'],
            );
            midgardmvc_core::get_instance()->authentication->login($tokens);
        }
        $this->get_login($args);
    }
}
?>
