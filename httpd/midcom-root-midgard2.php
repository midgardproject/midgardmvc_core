<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 *
 * MidgardRootFile for running MidCOM 3 under FastCGI setups like lighttpd
 */
 
// Load MidCOM 3
// Note: your MidCOM base directory has to be in PHP include_path
require('midgardmvc_core/framework.php');
$_MIDCOM = midgardmvc_core_midcom::get_instance('midgard2');
    
// Process the request
$_MIDCOM->process();

// Serve the request
$_MIDCOM->serve();

// End
unset($_MIDCOM);
?>