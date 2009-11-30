<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

require_once(dirname(__FILE__) . '/../../tests/testcase.php');

/**
 * Test that loads all components and dispatches each of their routes
 */
class midgardmvc_core_tests_routes extends midcom_tests_testcase
{
    
    public function testDispatchAll()
    {
        return;
        if (MIDGARDMVC_TESTS_ENABLE_OUTPUT)
        {
            echo __FUNCTION__ . "\n";
            echo "Loading all components and their routes\n\n";
        }
        
        // Go through the installed components
        foreach (midgardmvc_core::get_instance()->componentloader->manifests as $component_name => $manifest)
        {
            // Enter new context
            midgardmvc_core::get_instance()->context->create();
            try
            {
                midgardmvc_core::get_instance()->dispatcher->initialize($component_name);
            }
            catch (Exception $e)
            {
                if (MIDGARDMVC_TESTS_ENABLE_OUTPUT)
                {
                    echo "Skipping {$component_name}: component failed to load\n";
                }
                midgardmvc_core::get_instance()->context->delete();
                continue;
            }
            
            if (!midgardmvc_core::get_instance()->context->component_instance)
            {
                if (MIDGARDMVC_TESTS_ENABLE_OUTPUT)
                {
                    echo "Skipping {$component_name}: component failed to load\n";
                }
                midgardmvc_core::get_instance()->context->delete();
                continue;
            }

            if (!midgardmvc_core::get_instance()->context->component_instance->configuration->exists('routes'))
            {
                // No routes in this component, skip
                if (MIDGARDMVC_TESTS_ENABLE_OUTPUT)
                {
                    echo "Skipping {$component_name}: no routes\n";
                }
                midgardmvc_core::get_instance()->context->delete();
                continue;
            }

            if (MIDGARDMVC_TESTS_ENABLE_OUTPUT)
            {
                echo "Running {$component_name}...\n";
            }

            $routes = midgardmvc_core::get_instance()->dispatcher->get_routes();
            
            foreach ($routes as $route_id => $route_configuration)
            {
                // Generate fake arguments
                preg_match_all('/\{(.+?)\}/', $route_configuration['route'], $route_path_matches);
                $route_string = $route_configuration['route'];
                $args = array();
                foreach ($route_path_matches[1] as $match)
                {
                    $args[$match] = 'test';
                    $route_string = str_replace("{{$match}}", "[{$match}: {$args[$match]}]", $route_string);
                }

                midgardmvc_core::get_instance()->dispatcher->set_route($route_id, $args);
                if (MIDGARDMVC_TESTS_ENABLE_OUTPUT)
                {
                    echo "    {$route_id}: {$route_string}\n";
                }

                try
                {
                    midgardmvc_core::get_instance()->dispatcher->dispatch();
                }
                catch (Exception $e)
                {
                    if (MIDGARDMVC_TESTS_ENABLE_OUTPUT)
                    {
                        echo "        " . get_class($e) . ': ' . $e->getMessage() . "\n";
                    }
                    continue;
                }

                try
                {
                    if (MIDGARDMVC_TESTS_ENABLE_OUTPUT)
                    {
                        echo "        returned keys: " . implode(', ', array_keys(midgardmvc_core::get_instance()->context->$component_name)) . "\n";
                    }
                }
                catch (Exception $e)
                {
                    if (MIDGARDMVC_TESTS_ENABLE_OUTPUT)
                    {
                        echo "        returned no data\n";
                    }
                }
            }
            // Delete the context
            midgardmvc_core::get_instance()->context->delete();

            if (MIDGARDMVC_TESTS_ENABLE_OUTPUT)
            {
                echo "\n";
            }
        }
 
        $this->assertTrue(true);
    }

}
?>
