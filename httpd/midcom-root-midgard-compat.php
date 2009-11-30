<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 *
 * MidgardRootFile for running MidCOM 3 under Apache
 */
// If code-compat is defined we use that, otherwise we load MidCOM 3
if (mgd_is_element_loaded('code-compat'))
{
    ?><(code-compat)><?php
}
else
{
    // Note: your MidCOM base directory has to be in PHP include_path
    require('midgardmvc_core/framework.php');
    $_MIDCOM = midgardmvc_core_midcom::get_instance('midgard');
}

// code- elements used for things run before output
?>
<(code-global)>
<?php
if (mgd_is_element_loaded('code-init'))
{
    ?><(code-init)><?php
}
elseif (isset($_MIDCOM))
{
    // Call the controller if available
    $_MIDCOM->process();
}

if (isset($_MIDCOM))
{
    // Serve the request
    $_MIDCOM->serve();
}
else
{
    ?><(ROOT)><?php
}
// code-finish can be used for custom caching etc
?>
<(code-finish)>