<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 *
 * MidgardRootFile for running Midgard MVC under Apache and Midgard 8.09
 */
// Load Midgard MVC
// Note: your Midgard MVC base directory has to be in PHP include_path
require('midgardmvc_core/framework.php');
$midgardmvc = midgardmvc_core::get_instance('midgard');

// Process the request in order to populate needed data
$midgardmvc->process();

// Serve the request
$midgardmvc->serve();

// End
unset($midgardmvc);
?>
