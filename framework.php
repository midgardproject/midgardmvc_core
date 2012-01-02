<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
error_reporting(E_ALL);

if (!defined('MIDGARDMVC_ROOT'))
{
    define('MIDGARDMVC_ROOT', realpath(__DIR__ . '/../'));
}

if (!defined('MIDGARDMVC_STATIC_URL'))
{
    define('MIDGARDMVC_STATIC_URL', '/midgardmvc-static');
}

/**
 * Make sure the URLs not having query string have trailing slash or some extension in the "filename".
 * This makes life much, much better when making static copies for whatever reason
 */
if (   isset($_SERVER['REQUEST_URI'])
    && !preg_match('%\?|/$|midgardmvc-.+-|/.+\..+$%', $_SERVER['REQUEST_URI'])
    && $_SERVER['REQUEST_METHOD'] == 'GET'
    && !class_exists('MFS\AppServer\DaemonicHandler')
    && !class_exists('MFS_AppServer_DaemonicHandler'))
{
    // Note: Midgard MVC is running under a conventional web server. Under Application Server we cannot use header()
    header('HTTP/1.0 301 Moved Permanently');
    header("Location: {$_SERVER['REQUEST_URI']}/");
    header('Content-type: text/html; charset=utf-8'); // just to be sure, that the browser interprets fallback right
    $url_clean = htmlentities($_SERVER['REQUEST_URI']) . '/';
    echo "301: new location <a href='{$url_clean}'>{$url_clean}</a>";
    exit(0);
}

if (   isset($_SERVER['REQUEST_URI']) 
    && function_exists('mgd_version')
    && !class_exists('MFS\AppServer\DaemonicHandler')
    && !class_exists('MFS_AppServer_DaemonicHandler'))
{
    // Advertise the fact that this is a Midgard server
    header('X-Powered-By: Midgard/' . mgd_version());
    // Note: Midgard MVC is running under a conventional web server. Under Application Server we cannot use header()
}

// Load the exception handler
require(__DIR__ . '/exceptionhandler.php');

// Start up Midgard MVC
require(__DIR__ . '/interface.php');

// Register autoloader so we get all Midgard MVC classes loaded automatically
spl_autoload_register(array('midgardmvc_core', 'autoload'));
?>
