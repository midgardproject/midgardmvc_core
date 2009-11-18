<?php
/**
 * @package midcom_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Comet listeners controller
 *
 * @package midcom_core
 */
class midcom_core_controllers_authentication
{
    public function __construct(midcom_core_component_interface $instance)
    {
        $this->configuration = midcom_core_midcom::get_instance()->configuration;
    }
    
    public function get_logout(array $args)
    {
        midcom_core_midcom::get_instance()->authentication->logout();
        header('location: /');
        exit();
    }
    
    public function get_login(array $args)
    {   
        $exception_data = array();
        $exception_data['message'] = midcom_core_midcom::get_instance()->i18n->get
        (
            'please enter your username and password', 'midcom_core'
        );
        midcom_core_midcom::get_instance()->context->midcom_core_exceptionhandler = $exception_data;
    }

    public function post_login(array $args)
    {
        // TODO: Fix some more intelligent way to determine login method
        if (   isset($_POST['username']) 
            && isset($_POST['password']))
        {
            if (midcom_core_midcom::get_instance()->authentication->login($_POST['username'], $_POST['password']))
            {
                midcom_core_midcom::get_instance()->head->relocate('/');
            }
        }
        $this->get_login($args);
    }
}
?>
