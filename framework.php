<?php
/**
 * @package midcom_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
error_reporting(E_ALL);

if (!defined('MIDGARDMVC_ROOT'))
{
    define('MIDGARDMVC_ROOT', realpath(dirname(__FILE__) . '/../'));
}

if (!defined('MIDCOM_STATIC_URL'))
{
    define('MIDCOM_STATIC_URL', '/midcom-static');
}

if (!defined('MIDCOM_TEST_RUN'))
{
    define('MIDCOM_TEST_RUN', false);
}
/**
 * Make sure the URLs not having query string (or midcom-xxx- -method signature)
 * have trailing slash or some extension in the "filename".
 *
 * This makes life much, much better when making static copies for whatever reason
 */
if (   isset($_SERVER['REQUEST_URI'])
    && !preg_match('%\?|/$|midcom-.+-|/.+\..+$%', $_SERVER['REQUEST_URI']) 
    && $_SERVER['REQUEST_METHOD'] == 'GET')
{
    header('HTTP/1.0 301 Moved Permanently');
    header("Location: {$_SERVER['REQUEST_URI']}/");

    header('Content-type: text/html; charset=utf-8'); // just to be sure, that the browser interprets fallback right
    echo "301: new location <a href='{$_SERVER['REQUEST_URI']}/'>{$_SERVER['REQUEST_URI']}/</a>";
    exit(0);
}

if (   isset($_SERVER['REQUEST_URI']) 
    && function_exists('mgd_version'))
{
    // Advertise the fact that this is a Midgard server
    header('X-Powered-By: Midgard/' . mgd_version());
}

// Load the exception handler
require(MIDGARDMVC_ROOT . '/midcom_core/exceptionhandler.php');

// Start up MidCOM
require(MIDGARDMVC_ROOT . '/midcom_core/midcom.php');
?>
