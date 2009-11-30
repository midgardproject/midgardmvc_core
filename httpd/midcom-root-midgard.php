<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 *
 * MidgardRootFile for running MidCOM 3 under Apache
 */
// Load MidCOM 3
// Note: your MidCOM base directory has to be in PHP include_path
require('midgardmvc_core/framework.php');
$_MIDCOM = midgardmvc_core_midcom::get_instance('midgard');

// Process the request in order to populate needed data
$_MIDCOM->process();

// Serve the request
$_MIDCOM->serve();

// End
unset($_MIDCOM);
?>