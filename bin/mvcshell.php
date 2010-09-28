#!/usr/bin/php
<?php
// Load Midgard MVC
// Note: your Midgard MVC base directory has to be in PHP include_path
require('midgardmvc_core/framework.php');

// Defaults for configuration
$mvc_config_yaml = file_get_contents(MIDGARDMVC_ROOT. '/midgardmvc_core/configuration/defaults.yml');
if (!extension_loaded('yaml'))
{
    // YAML PHP extension is not loaded, include the pure-PHP implementation
    require_once MIDGARDMVC_ROOT. '/midgardmvc_core/helpers/spyc.php';
    $config = Spyc::YAMLLoad($mvc_config_yaml);
}
else
{
    $config = yaml_parse($mvc_config_yaml);
}

$config['services_dispatcher'] = 'manual';
$config['providers_component'] = 'midgardmvc';

$arguments = $_SERVER['argv'];

if (count($arguments) == 1)
{
    // First argument is the script name
    mvcshell_print("Run with '$ mvcschell.php --help' to get documentation");
}

$commands = array
(
    'call' => 'mvcshell_call',
    'display' => 'mvcshell_display',
    'profile' => 'mvcshell_profile',
);
$command = null;
$remaining_arguments = array();
for ($i = 1; $i < count($arguments); $i++)
{
    if ($arguments[$i] == '--help')
    {
        // Help requested, display and abort
        mvcshell_print(mvcshell_help());
    }
    elseif (substr($arguments[$i], 0, 2) == '--')
    {
        // Configuration option in format key=value
        $config_key = null;
        $config_value = null;
        if (!strpos($arguments[$i], '='))
        {
            $config_key = substr($arguments[$i], 2);
        }
        else
        {
            $config_parts = explode('=', $arguments[$i]);
            $config_key = substr($config_parts[0], 2);
            $config_value = $config_parts[1];
        }

        if (   !isset($config[$config_key])
            && $config_key != 'midgard')
        {
            mvcshell_print("Unrecognized configuration parameter '{$config_key}'");
        }

        if (!$config_value)
        {
            mvcshell_print("No value given for configuration setting '{$config_key}'");
        }

        $config[$config_key] = $config_value;
        continue;
    }

    if (is_null($command))
    {
        // Intrepret first non-config argument as the command
        $command = $arguments[$i];
        if (!isset($commands[$command]))
        {
            mvcshell_print("Unrecognized command '{$arguments[$i]}'. Run '$ mvcshell --help' to get list of available commands");
        }

        if (!is_callable($commands[$command]))
        {
            mvcshell_print("Non-functioning command '{$arguments[$i]}'. Run '$ mvcshell --help' to get list of available commands");
        }
        continue;
    }

    // This argument is neither config nor command, add it to the arg list
    $remaining_arguments[] = $arguments[$i];
}
mvcshell_check_config($config);
$midgardmvc = midgardmvc_core::get_instance($config);

call_user_func($commands[$command], $remaining_arguments);

/**
 * Print output and exit
 */
function mvcshell_print($content)
{
    die("Midgard MVC shell\n\n{$content}\n");
}

function mvcshell_dump($content)
{
    if (!extension_loaded('yaml'))
    {
        // YAML PHP extension is not loaded, include the pure-PHP implementation
        die("\n" . Spyc::YAMLDump($content) . "\n");
    }
    else
    {
        die("\n" . yaml_emit($content) . "\n");
    }
}

function mvcshell_help()
{
    return "Foo";
}

function mvcshell_check_config(array $config)
{
    $requires_midgard = false;
    if (   $config['providers_hierarchy'] == 'midgardmvc_core_providers_hierarchy_midgardmvc'
        || $config['providers_hierarchy'] == 'midgardmvc')
    {
        $requires_midgard = true;
    }

    if ($requires_midgard)
    {
        if (!extension_loaded('midgard2'))
        {
            mvcshell_print("Your defined configuration requires Midgard but you don't have it enabled in your PHP configuration. Check that the 'midgard2.so' extension is enabled in your php.ini");
        }

        if (!isset($config['midgard']))
        {
            mvcshell_print("Your defined configuration requires Midgard. Specify configuration file with '--midgard=example'. This will first try files from /etc/midgard2/conf.d, and then from ~/.midgard2");
        }

        $midgard_config = new midgard_config();
        if (!$midgard_config->read_file($config['midgard']))
        {
            if (!$midgard_config->read_file($config['midgard'], true))
            {
                mvcshell_print("Couldn't open Midgard configuration file from either /etc/midgard2/conf.d/{$config['midgard']} or ~/.midgard2/{$config['midgard']}");
            }
        }
        // TODO: Dynamic config for loglevels etc
        $midgard_config->loglevel = 'error';

        $midgard = midgard_connection::get_instance();
        if (!$midgard->open_config($midgard_config))
        {
            mvcshell_print("Failed connecting to the Midgard database using the '{$config['midgard']}' configuration file");
        }
        unset($config['midgard']);
    }
}

function mvcshell_call(array $arguments)
{
    if (count($arguments) < 2)
    {
        mvcshell_print("Missing arguments for a call. Run with arguments '<intent> <route_id> <arg1>, <arg2>'");
    }

    $intent = $arguments[0];
    $route_id = $arguments[1];
    $route_args = array_slice($arguments, 2);

    $data = midgardmvc_core::get_instance()->templating->dynamic_call($intent, $route_id, $route_args);
    mvcshell_dump($data);
}

function mvcshell_display(array $arguments)
{
    if (count($arguments) < 2)
    {
        mvcshell_print("Missing arguments for a display. Run with arguments '<intent> <route_id> <arg1>, <arg2>'");
    }

    $intent = $arguments[0];
    $route_id = $arguments[1];
    $route_args = array_slice($arguments, 2);

    $mvc = midgardmvc_core::get_instance();
    $mvc->head = new midgardmvc_core_helpers_head();
    $request = new midgardmvc_core_request();
    $mvc->context->create($request);

    $content = $mvc->templating->dynamic_load($intent, $route_id, $route_args, true);
    mvcshell_print($content);
}

function mvcshell_profile(array $arguments)
{
    if (!extension_loaded('xhprof'))
    {
        mvcshell_print("Profiling command requires the XHProf PHP extension to be installed. Get it from http://pecl.php.net/package/xhprof");
    }

    if (count($arguments) < 2)
    {
        mvcshell_print("Missing arguments for a profiling run. Run with arguments '<intent> <route_id> <arg1>, <arg2>'");
    }

    $intent = $arguments[0];
    $route_id = $arguments[1];
    $route_args = array_slice($arguments, 2);

    $mvc = midgardmvc_core::get_instance();
    $mvc->head = new midgardmvc_core_helpers_head();
    $request = new midgardmvc_core_request();
    $mvc->context->create($request);

    xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);

    $content = $mvc->templating->dynamic_load($intent, $route_id, $route_args, true);

    $xhprof_data = xhprof_disable();
    mvcshell_dump(array_reverse($xhprof_data));
}
?>
