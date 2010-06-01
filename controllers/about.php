<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard MVC "About Midgard CMS" controller
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_controllers_about
{
    public function __construct(midgardmvc_core_component_interface $instance)
    {
        $this->configuration = midgardmvc_core::get_instance()->configuration;
    }
    
    public function get_about(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_user();

        $this->data['versions'] = array
        (
            'midgardmvc'  => midgardmvc_core::get_instance()->componentloader->manifests['midgardmvc_core']['version'],
            'midgard' => mgd_version(),
            'php'     => phpversion(),
        );
        
        $this->data['components'] = array();
        foreach (midgardmvc_core::get_instance()->componentloader->manifests as $component => $manifest)
        {
            if ($component == 'midgardmvc_core')
            {
                continue;
            }
            
            $this->data['components'][$component] = array
            (
                'name'    => $manifest['component'],
                'version' => $manifest['version'],
            );
        }
        
        $this->data['authors'] = midgardmvc_core::get_instance()->componentloader->authors;
        ksort($this->data['authors']);
    }

    public function get_database(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_admin();

        $this->data['installed_types'] = midgardmvc_core::get_instance()->dispatcher->get_mgdschema_classes();
    }
    
    public function post_database(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_admin();
        if (isset($_POST['update']))
        {
            //Disable limits
            // TODO: Could this be done more safely somehow
            @ini_set('memory_limit', -1);
            @ini_set('max_execution_time', 0);

            midgardmvc_core::get_instance()->dispatcher->get_midgard_connection()->set_loglevel('debug');
            
            if (!class_exists('midgard_storage'))
            {
                // Midgard1 or Midgard2 9.03
                if (!midgard_config::create_midgard_tables())
                {
                    throw new Exception("Could not create Midgard class tables");
                }
                // And update as necessary
                $mgdschema_types = midgardmvc_core::get_instance()->dispatcher->get_mgdschema_classes();
                foreach ($mgdschema_types as $type)
                {
                    if (midgard_config::class_table_exists($type))
                    {
                        midgardmvc_core::get_instance()->log('midgardmvc_core_controllers_about::post_database', "Updating database table for type {$type}", 'debug');
                        if (!midgard_config::update_class_table($type))
                        {
                            throw new Exception('Could not update ' . $type . ' tables in test database');
                        }
                        continue;
                    }
                    midgardmvc_core::get_instance()->log('midgardmvc_core_controllers_about::post_database', "Creating database table for type {$type}", 'debug');
                    if (!midgard_config::create_class_table($type))
                    {
                        throw new Exception('Could not create ' . $type . ' tables in test database');
                    }
                }
            }
            else
            {
                // Midgard2 9.09 or newer
                /*if (!midgard_storage::create_base_storage())
                {
                    throw new Exception("Could not create Midgard class tables");
                }*/
                // And update as necessary
                $mgdschema_types = midgardmvc_core::get_instance()->dispatcher->get_mgdschema_classes();
                foreach ($mgdschema_types as $type)
                {
                    if (midgard_storage::class_storage_exists($type))
                    {
                        midgardmvc_core::get_instance()->log('midgardmvc_core_controllers_about::post_database', "Updating storage for type {$type}", 'debug');
                        if (!midgard_storage::update_class_storage($type))
                        {
                            //throw new Exception('Could not update ' . $type . ' storage');
                        }
                        continue;
                    }
                    midgardmvc_core::get_instance()->log('midgardmvc_core_controllers_about::post_database', "Creating storage for type {$type}", 'debug');
                    if (!midgard_storage::create_class_storage($type))
                    {
                        //throw new Exception('Could not create ' . $type . ' storage');
                    }
                }
            }
            midgardmvc_core::get_instance()->dispatcher->get_midgard_connection()->set_loglevel(midgardmvc_core::get_instance()->configuration->get('log_level'));
        }
        
        $this->get_database($args);
    }
}
?>
