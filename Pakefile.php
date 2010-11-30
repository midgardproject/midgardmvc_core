<?php
if (version_compare(pakeApp::VERSION, '1.4.1', '<'))
{
    throw new pakeException('Pake 1.4.1 or newer is required');
}

define('__PAKEFILE_DIR__', dirname(__FILE__));

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
    if (!isset($args[0]))
    {
        throw new pakeException('usage: pake '.$task->get_name().' path/to/application.yml target/dir/path');
    }

    if (!isset($args[1]))
    {
        throw new pakeException('usage: pake '.$task->get_name().' path/to/application.yml target/dir/path');
    }

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

    $target_dir = $args[1];

    if (file_exists($target_dir))
    {
        throw new pakeException("Directory {$target_dir} already exists");
    }

    create_env_fs($target_dir);
    $dir = realpath($target_dir);

    get_mvc_components($application, $dir);

    $dbname = 'midgard2';
    create_ini_file($dir, $dbname);
    create_config($dir, $dbname);

    pakeYaml::emitFile($application, "{$target_dir}/application.yml");

    // install PHPTAL
    pake_superuser_sh('pear install -s http://phptal.org/latest.tar.gz');

    init_mvc_stage2($dir, $dbname);
}

function get_mvc_components(array $application, $target_dir)
{
    foreach ($application['components'] as $component => $source)
    {
        get_mvc_component($component, $source, $target_dir);
    }
}

function get_mvc_component($component, $source, $target_dir)
{
    if (   !is_array($source)
        && !file_exists("{$target_dir}/{$component}"))
    {
        throw new pakeException("Cannot install {$component}, source repository not provided");
    }

    if (!isset($source['git']))
    {
        throw new pakeException("Cannot install {$component}, unknown source");
    }

    if (!file_exists("{$target_dir}/{$component}"))
    {
        // Check out the component from git
        pakeGit::clone_repository($source['git'], "{$target_dir}/{$component}");
    }

    $manifest_path = "{$target_dir}/{$component}/manifest.yml";
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
    $schema_files = pakeFinder::type('file')->name('*.xml')->in("{$target_dir}/{$component}/models/");
    foreach ($schema_files as $schema_file)
    {
        pake_copy($schema_file, "{$target_dir}/share/schema/{$component}_" . basename($schema_file));
    }

    if (isset($manifest['requires']))
    {
        // Install required components too
        foreach ($manifest['requires'] as $component => $source)
        {
            get_mvc_component($component, $source, $target_dir);
        }
    }
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

    pake_echo_comment("Midgard MVC installed. Run your application with 'php -c {$dir}/php.ini {$dir}/midgardmvc_core/httpd/midgardmvc-root-appserv.php' and go to http://localhost:8001/");
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

    if (!extension_loaded('midgard2'))
    {
        $php_config .= "extension=midgard2.so\n";
    }

    $php_config .= "date.timezone=" . ini_get('date.timezone') . "\n";
    $php_config .= "midgard.engine = On\n";
    $php_config .= "midgard.http = On\n";
    $php_config .= "midgard.configuration_file = {$cfg_path}\n";

    $res = file_put_contents($fname, $php_config);

    if ($res === false)
    {
        throw new pakeException("Couldn't create {$fname} file");
    }

    pake_echo_action('file+', $fname);
}

function create_env_fs($dir)
{
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
    if (is_dir(__PAKEFILE_DIR__.'/../midgard/core/midgard'))
        $xml_dir = __PAKEFILE_DIR__.'/../midgard/core/midgard';
    elseif (is_dir('/usr/share/midgard2'))  // <-- need something smarter here
    {
        $xml_dir = '/usr/share/midgard2';
    }
    else
    {
        throw new pakeException("Can't find core xml-files directory");
    }

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
