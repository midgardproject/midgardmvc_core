<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
 
/**
 * Test that loads all components and dispatches each of their routes
 */

// Argument checks
if (count($argv) != 2)
{
    die("Usage: php find_orphans.php midgardconffile\n");
}
$conffile = $argv[1];

if (   !extension_loaded('midgard')
    && !extension_loaded('midgard2'))
{
    die("Midgard extension is not available\n");
}

// Start up a Midgard connection
$midgard = midgard_connection::get_instance();
$midgard->open($conffile);

// Load MidCOM with the manual dispatcher
require('midgardmvc_core/framework.php');

echo "Loading all components and their routes\n\n";

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
        echo "Skipping {$component_name}: component failed to load\n\n";
        midgardmvc_core::get_instance()->context->delete();
        continue;
    }
    
    if (!midgardmvc_core::get_instance()->context->component_instance)
    {
        echo "Skipping {$component_name}: component failed to load\n\n";
        midgardmvc_core::get_instance()->context->delete();
        continue;
    }

    if (!midgardmvc_core::get_instance()->context->component_instance->configuration->exists('routes'))
    {
        // No routes in this component, skip
        echo "Skipping {$component_name}: no routes\n\n";
        midgardmvc_core::get_instance()->context->delete();
        continue;
    }
    
    echo "Running {$component_name}...\n";
    
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
        echo "    {$route_id}: {$route_string}\n";
        
        try
        {
            midgardmvc_core::get_instance()->dispatcher->dispatch();
        }
        catch (Exception $e)
        {
            echo "        " . get_class($e) . ': ' . $e->getMessage() . "\n";
            continue;
        }
        
        try
        {
            echo "        returned keys: " . implode(', ', array_keys(midgardmvc_core::get_instance()->context->$component_name)) . "\n";
        }
        catch (Exception $e)
        {
            echo "        returned no data\n";
        }
    }
    // Delete the context
    midgardmvc_core::get_instance()->context->delete();
    echo "\n";
}
?>
