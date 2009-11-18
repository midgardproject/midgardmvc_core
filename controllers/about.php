<?php
/**
 * @package midcom_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM "About Midgard CMS" controller
 *
 * @package midcom_core
 */
class midcom_core_controllers_about
{
    public function __construct(midcom_core_component_interface $instance)
    {
        $this->configuration = $_MIDCOM->configuration;
    }
    
    public function get_about(array $args)
    {
        $_MIDCOM->authorization->require_user();

        $this->data['versions'] = array
        (
            'midcom'  => $_MIDCOM->componentloader->manifests['midcom_core']['version'],
            'midgard' => mgd_version(),
            'php'     => phpversion(),
        );
        
        $this->data['components'] = array();
        foreach ($_MIDCOM->componentloader->manifests as $component => $manifest)
        {
            if ($component == 'midcom_core')
            {
                continue;
            }
            
            $this->data['components'][$component] = array
            (
                'name'    => $manifest['component'],
                'version' => $manifest['version'],
            );
        }
        
        $this->data['authors'] = $_MIDCOM->componentloader->authors;
        ksort($this->data['authors']);
    }

    public function get_database(array $args)
    {
        $_MIDCOM->authorization->require_admin();

        $this->data['installed_types'] = $_MIDCOM->dispatcher->get_mgdschema_classes();
    }
    
    public function post_database(array $args)
    {
        $_MIDCOM->authorization->require_admin();
        if (isset($_POST['update']))
        {
            //Disable limits
            // TODO: Could this be done more safely somehow
            @ini_set('memory_limit', -1);
            @ini_set('max_execution_time', 0);

            $_MIDCOM->dispatcher->get_midgard_connection()->set_loglevel('debug');
            
            if (!class_exists('midgard_storage'))
            {
                // Midgard1 or Midgard2 9.03
                if (!midgard_config::create_midgard_tables())
                {
                    throw new Exception("Could not create Midgard class tables");
                }
                // And update as necessary
                $mgdschema_types = $_MIDCOM->dispatcher->get_mgdschema_classes();
                foreach ($mgdschema_types as $type)
                {
                    if (midgard_config::class_table_exists($type))
                    {
                        $_MIDCOM->log('midcom_core_controllers_about::post_database', "Updating database table for type {$type}", 'debug');
                        if (!midgard_config::update_class_table($type))
                        {
                            throw new Exception('Could not update ' . $type . ' tables in test database');
                        }
                        continue;
                    }
                    $_MIDCOM->log('midcom_core_controllers_about::post_database', "Creating database table for type {$type}", 'debug');
                    if (!midgard_config::create_class_table($type))
                    {
                        throw new Exception('Could not create ' . $type . ' tables in test database');
                    }
                }
            }
            else
            {
                // Midgard2 9.09 or newer
                if (!midgard_storage::create_base_storage())
                {
                    throw new Exception("Could not create Midgard class tables");
                }
                // And update as necessary
                $mgdschema_types = $_MIDCOM->dispatcher->get_mgdschema_classes();
                foreach ($mgdschema_types as $type)
                {
                    if (midgard_storage::class_storage_exists($type))
                    {
                        // FIXME: Skip updates until http://trac.midgard-project.org/ticket/1426 is fixed
                        continue;
                        $_MIDCOM->log('midcom_core_controllers_about::post_database', "Updating storage for type {$type}", 'debug');
                        if (!midgard_storage::update_class_storage($type))
                        {
                            throw new Exception('Could not update ' . $type . ' storage');
                        }
                        continue;
                    }
                    $_MIDCOM->log('midcom_core_controllers_about::post_database', "Creating storage for type {$type}", 'debug');
                    if (!midgard_storage::create_class_storage($type))
                    {
                        throw new Exception('Could not create ' . $type . ' storage');
                    }
                }
            }
            $_MIDCOM->dispatcher->get_midgard_connection()->set_loglevel($_MIDCOM->configuration->get('log_level'));
        }
        
        $this->get_database($args);
    }
}
?>
