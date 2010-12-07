<?php
if (version_compare(pakeApp::VERSION, '1.4.1', '<'))
{
    throw new pakeException('Pake 1.4.1 or newer is required');
}

define('__PAKEFILE_DIR__', dirname(__FILE__));

pake_import('pear', false);

pake_task('default');

pake_desc('Set up Midgard MVC with Midgard2. Usage: pake init_mvc path/to/application.yml target/dir/path');
pake_task('init_mvc');

pake_task('_init_mvc_stage2'); // helper

// show list of available tasks, by default
function run_default($task, $args)
{
    pakeApp::get_instance()->display_tasks_and_comments();
}

function run_init_mvc($task, $args)
{
    if (count($args) != 2)
    {
        throw new pakeException('usage: pake '.$task->get_name().' path/to/application.yml target/dir/path');
    }

    pake_echo_comment('reading application definition');
    $application_yml = $args[0];

    $application = @file_get_contents($application_yml);
    if (empty($application))
    {
        throw new pakeException("Failed to read MVC application.yml from {$application_yml}");
    }
    $application = pakeYaml::loadString($application);
    if (!is_array($application))
    {
        throw new pakeException("Failed to parse MVC application.yml from {$application_yml}");
    }

    create_env_fs($args[1]);
    $dir = realpath($args[1]);

    get_mvc_components($application, $dir);

    pake_echo_comment('getting dependencies');

    // install recent AppServer
    pakePearTask::install_pear_package('AppServer', 'pear.indeyets.pp.ru');

    pake_echo_comment('installing configuration files');
    $dbname = 'midgard2';
    create_ini_file($dir, $dbname);
    create_config($dir, $dbname);

    create_runner_script($dir);

    pakeYaml::emitFile($application, "{$dir}/application.yml");

    init_mvc_stage2($dir, $dbname);

    pake_echo_comment("Midgard MVC installed. Run your application with ".
                        // "'php -c {$dir}/php.ini {$dir}/midgardmvc_core/httpd/midgardmvc-root-appserv.php' ".
                        "'{$dir}/run' and go to http://localhost:8001/");
}

function get_mvc_components(array $application, $target_dir)
{
    pake_echo_comment('fetching MidgardMVC components');
    foreach ($application['components'] as $component => $sources)
    {
        get_mvc_component($component, $sources, $target_dir);
    }
}

function get_mvc_component($component, $sources, $target_dir)
{
    $component_dir = $target_dir.'/'.$component;

    if (!file_exists($component_dir))
    {
        if (!is_array($sources))
        {
            throw new pakeException("Cannot install {$component}, source repository not provided");
        }

        // support for single-source components
        if (!isset($sources[0]))
        {
            $sources = array($sources);
        }

        foreach ($sources as $source)
        {
            if (!isset($source['type']))
            {
                var_dump($source);
                pake_echo_error('source does not have "type" defined. skipping');
                continue;
            }

            try
            {
                switch ($source['type'])
                {
                    case 'git':
                        get_mvc_component_from_git($source['url'], $source['branch'], $component_dir);
                    break;

                    case 'github':
                        $is_private = isset($source['private']) ? $source['private'] : false;
                        get_mvc_component_from_github($source['user'], $source['repository'], $source['branch'], $is_private, $component_dir);
                    break;

                    case 'subversion':
                        get_mvc_component_from_subversion($source['url'], $component_dir);
                    break;

                    default:
                        pake_echo_error('source is of unknown type. skipping');
                    break;
                }

                // there wasn't exception, so, probably, we're ok
                break;
            }
            catch (pakeException $e)
            {
                pake_echo_error('there was an error fetching from source: '.$e->getMessage().'. skipping');
                if (file_exists($component_dir))
                {
                    pake_echo_comment("Cleanup…");
                    pake_remove_dir($component_dir);
                    pake_echo_comment("<- Cleanup is done");
                }
            }
        }
    }

    $manifest_path = "{$component_dir}/manifest.yml";
    if (!file_exists($manifest_path))
    {
        throw new pakeException("Component {$component} did not supply a manifest file");
    }

    $manifest = pakeYaml::loadFile($manifest_path);
    if (!is_array($manifest))
    {
        throw new pakeException("Component {$component} manifest is invalid");
    }

    // Link schemas
    $schema_files = pakeFinder::type('file')->name('*.xml')->maxdepth(0)->in("{$component_dir}/models/");
    foreach ($schema_files as $schema_file)
    {
        pake_copy($schema_file, "{$target_dir}/share/schema/{$component}_" . basename($schema_file));
    }

    $view_files = pakeFinder::type('file')->name('*.xml')->in("{$component_dir}/models/views/");
    foreach ($view_files as $view_file)
    {
        pake_copy($view_file, "{$target_dir}/share/views/{$component}_" . basename($view_file));
    }

    // Install pear-dependencies
    if (isset($manifest['requires_pear'])) {
        $pear = escapeshellarg(pake_which('pear'));

        foreach($manifest['requires_pear'] as $name => $fields) {
            if (isset($fields['channel'])) {
                pakePearTask::install_pear_package($name, $fields['channel']);
            } elseif (isset($fields['url'])) {
                try {
                    // if the package is already installed, this will be ok
                    pake_sh($pear.' info '.escapeshellarg($name));
                } catch (pakeException $e) {
                    // otherwise, let's install it!
                    pake_superuser_sh($pear.' install '.escapeshellarg($fields['url']));
                }
            } else {
                throw new pakeException('Do not know how to install pear-package without channel or url: "'.$name.'"');
            }
        }
    }

    // Install component dependencies too
    if (isset($manifest['requires']))
    {
        foreach ($manifest['requires'] as $component => $source)
        {
            get_mvc_component($component, $source, $target_dir);
        }
    }
}

function get_mvc_component_from_git($url, $branch, $component_dir)
{
    // Check out the component from git
    pakeGit::clone_repository($url, $component_dir)->checkout($branch);
}

function get_mvc_component_from_github($user, $repository, $branch, $is_private, $component_dir)
{
    if ($is_private) {
        get_mvc_component_from_git('git@github.com:'.$user.'/'.$repository.'.git', $branch, $component_dir);
    } else {
        try {
            // At first, we try "git" protocol
            get_mvc_component_from_git('git://github.com/'.$user.'/'.$repository.'.git', $branch, $component_dir);
        } catch (pakeException $e) {
            // Then fallback to http
            get_mvc_component_from_git('https://github.com/'.$user.'/'.$repository.'.git', $branch, $component_dir);
        }
    }
}

function get_mvc_component_from_subversion($url, $component_dir)
{
    pakeSubversion::checkout($url, $component_dir);
}

function init_mvc_stage2($dir, $dbname)
{
    // we have to run it as a separate process because of gda/sqlite bug
    $force_tty = '';
    if (defined('PAKE_FORCE_TTY') or (DIRECTORY_SEPARATOR != '\\' and function_exists('posix_isatty') and @posix_isatty(STDOUT)))
    {
        $force_tty = ' --force-tty';
    }

    putenv('MIDGARD_ENV_GLOBAL_SHAREDIR='.$dir.'/share');
    $php = pake_which('php');
    $pake = pake_which('pake');
    putenv('PHP_COMMAND='.$php.' -c '.$dir.' -d midgard.http=Off');
    pake_sh(
        escapeshellarg($pake).$force_tty.' -f '.escapeshellarg(__FILE__).
        ' _init_mvc_stage2 '.escapeshellarg($dir).' '.escapeshellarg($dbname),
        true
    );
}

function run__init_mvc_stage2($task, $args)
{
    $dir = $args[0];
    $dbname = $args[1];

    init_database($dir, $dbname);
}

function init_database($dir, $dbname)
{
    _connect($dir, $dbname);

    midgard_storage::create_base_storage();
    pake_echo_action('midgard', 'Created base storage');

    $re = new ReflectionExtension('midgard2');
    $classes = $re->getClasses();
    foreach ($classes as $refclass)
    {
        $parent_class = $refclass->getParentClass();

        if (!$parent_class)
        {
            continue;
        }

        if ($parent_class->getName() != 'midgard_object')
        {
            continue;
        }

        $type = $refclass->getName();
        midgard_storage::create_class_storage($type);
        pake_echo_action('midgard', 'Created storage for '.$type.' class');
    }
}


function create_ini_file($dir, $dbname)
{
    $cfg_path = $dir.'/'.$dbname.'.conf';

    $fname = $dir.'/php.ini';

    $php_config = '';

    if (!file_exists('/etc/debian_version') or !extension_loaded('midgard2'))
    {
        $php_config .= "extension=midgard2.so\n";
        $php_config .= "extension=gettext.so\n";

        if (extension_loaded('yaml'))
        {
            $php_config .= "extension=yaml.so\n";
        }

        if (extension_loaded('httpparser'))
        {
            $php_config .= "extension=httpparser.so\n";
        }
    }

    $php_config .= "include_path=" . ini_get('include_path') . "\n";
    $php_config .= "date.timezone=" . ini_get('date.timezone') . "\n";
    $php_config .= "midgard.engine = On\n";
    $php_config .= "midgard.http = On\n";
    $php_config .= "midgard.configuration_file = {$cfg_path}\n";
    $php_config .= "midgardmvc.application_config = {$dir}/application.yml\n";

    $res = file_put_contents($fname, $php_config);

    if ($res === false)
    {
        throw new pakeException("Couldn't create {$fname} file");
    }

    pake_echo_action('file+', $fname);
}

function create_env_fs($dir)
{
    pake_echo_comment('creating directory-structure');

    if (file_exists($dir))
    {
        throw new pakeException("Directory {$target_dir} already exists");
    }

    pake_mkdirs($dir);
    $dir = realpath($dir);

    if (count(pakeFinder::type('any')->in($dir)) > 0)
    {
        throw new pakeException('"'.$dir.'" folder is not empty');
    }

    pake_mkdirs($dir.'/share/schema');
    pake_mkdirs($dir.'/share/views');
    pake_mkdirs($dir.'/blobs');
    pake_mkdirs($dir.'/var');
    pake_mkdirs($dir.'/cache');

    // looking for core xml-files
    $pkgconfig = pake_which('pkg-config');

    if ($pkgconfig) {
        try {
            $xml_dir = trim(pake_sh(escapeshellarg($pkgconfig).' --variable=prefix midgard2')).'/share/midgard2';
        } catch (pakeException $e)
        {
        }
    }

    if (!isset($xml_dir)) {
        if (is_dir('/usr/share/midgard2')) {
            $xml_dir = '/usr/share/midgard2';
        } elseif (is_dir(__PAKEFILE_DIR__.'/../midgard/core/midgard')) {
            $xml_dir = realpath(__PAKEFILE_DIR__.'/..').'/midgard/core/midgard';
        } else {
            $path = pake_input("Please enter your midgard-prefix");

            if (!is_dir($path))
                throw new pakeException('Wrong path: "'.$path.'"');

            $xml_dir = $path.'/share/midgard2';
        }
    }

    if (!is_dir($xml_dir))
        throw new pakeException("Can't find core xml-files directory");

    $xmls = pakeFinder::type('file')->name('*.xml')->maxdepth(0);

    pake_mirror($xmls, $xml_dir, $dir.'/share');
}

function _connect($dir, $dbname)
{
    $config = new midgard_config();
    $res = $config->read_file_at_path($dir.'/'.$dbname.'.conf');

    if (false === $res) {
        throw new pakeException('Failed to read config');
    }

    $config->create_blobdir();

    $midgard = midgard_connection::get_instance();
    $res = $midgard->open_config($config);

    if (false === $res) {
        throw new pakeException('Failed to init connection from config "'.$dbname.'"');
    }

    pake_echo_comment('Connected to database');
}

function create_config($prefix, $dbname)
{
    $fname = $prefix.'/'.$dbname.'.conf';

    $res = file_put_contents(
        $fname,
        "[MidgardDatabase]\n".
        "Type=SQLite\n".
        "Name={$dbname}\n".
        "DatabaseDir={$prefix}\n".
        "Logfile={$prefix}/midgard2.log\n".
        "Loglevel=warning\n".
        "TableCreate=true\n".
        "TableUpdate=true\n".
        "TestUnit=false\n".
        "\n".
        "[MidgardDir]\n".
        "BlobDir={$prefix}/blobs\n".
        "ShareDir={$prefix}/share\n".
        "VarDir={$prefix}/var\n".
        "CacheDir={$prefix}/cache\n"
    );

    if ($res === false)
    {
        throw new pakeException("Couldn't create {$fname} file");
    }

    pake_echo_action('file+', $fname);
}

function create_runner_script($prefix)
{
    $fname = $prefix.'/run';

    $contents =  '#!/bin/sh'."\n\n";
    $contents .= escapeshellarg(pake_which('php')).' -c '.escapeshellarg($prefix.'/php.ini').' '
                .escapeshellarg(pake_which('aip')).' app '.escapeshellarg($prefix.'/midgardmvc_core/httpd');

    file_put_contents($fname, $contents);
    pake_echo_action('file+', $fname);

    pake_chmod('run', $prefix, 0755);
}
