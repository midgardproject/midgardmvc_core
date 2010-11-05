<?php
if (count($argv) != 2)
{
    die("Usage: php quick_init.php midgardconffile\n");
}

if (!extension_loaded('midgard2'))
{
    die("Midgard2 is not installed in your PHP environment.\n");
}

if (!class_exists('midgardmvc_core_node'))
{
    die("Midgard MVC or its schemas are not installed in your Midgard environment.\n");
}

// Create a config file
$config = new midgard_config();
$config->dbtype = 'SQLite';
$config->database = $argv[1];
$config->tablecreate = true;
$config->tableupdate = true;
$config->loglevel = 'warning';
$config->save_file($argv[1]);
echo "Configuration file /etc/midgard2/conf.d/{$argv[1]} created.\n";

// Open a DB connection with the config
$midgard = midgard_connection::get_instance();
if (!$midgard->open_config($config))
{
    die("Failed to open Midgard database connection to {$argv[1]}: " . $midgard->get_error_string() ."\n");
}

// Create storage
midgard_storage::create_base_storage();
echo "Database initialized, preparing storage for MgdSchema classes:\n";
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
    echo "  Created storage for {$type}\n";
}

$page = new midgardmvc_core_node();
$page->name = 'midgardmvc_root';
$page->title = 'Midgard MVC root page';
$page->content = 'Welcome to Midgard MVC!';
$page->component = 'midgardmvc_core';
if (!$page->create())
{
    die("Failed to create Midgard MVC root node: " . $midgard->get_error_string() ."\n");
}
echo "Created Midgard MVC root page {$page->guid}\n";

$person = new midgard_person();
$person->set_guid('f6b665f1984503790ed91f39b11b5392');
$person->firstname = 'Midgard';
$person->lastname = 'Administrator';
$person->email = 'dev@lists.midgard-project.org';
$person->homepage = 'http://www.midgard-project.org/';
$person->birthdate = new midgard_datetime('1999-05-08');
$person->create();
echo "Create Midgard person {$person->firstname} {$person->lastname}\n";

echo "All set up\n";
?>
