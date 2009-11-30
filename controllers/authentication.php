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
    public function __construct(midgardmvc_core_component_interface $instance)
    {
        $this->configuration = midgardmvc_core::get_instance()->configuration;
    }
    
    public function get_logout(array $args)
    {
        midgardmvc_core::get_instance()->authentication->logout();
        header('location: /');
        exit();
    }
    
    public function get_login(array $args)
    {   
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
            if (midgardmvc_core::get_instance()->authentication->login($_POST['username'], $_POST['password']))
            {
                midgardmvc_core::get_instance()->head->relocate('/');
            }
        }
        $this->get_login($args);
    }
}
?>
