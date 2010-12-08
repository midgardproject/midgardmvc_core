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
        $app = midgardmvc_core::get_instance();
        $app->authentication->logout();
        $app->dispatcher->header('Location: /');
        $app->dispatcher->end_request();
    }
    
    public function get_login(array $args)
    {
        if (midgardmvc_core::get_instance()->authentication->is_user())
        {
            midgardmvc_core::get_instance()->head->relocate('/');
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
        // TODO: Fix some more intelligent way to determine login method
        if (   isset($_POST['username']) 
            && isset($_POST['password']))
        {
            midgardmvc_core::get_instance()->authentication->login($_POST['username'], $_POST['password']);
        }
        $this->get_login($args);
    }
}
?>
