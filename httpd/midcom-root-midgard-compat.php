<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 *
 * MidgardRootFile for running Midgard MVC under Apache and Midgard 8.09
 */
// If code-compat is defined we use that, otherwise we load Midgard MVC
if (mgd_is_element_loaded('code-compat'))
{
    ?><(code-compat)><?php
}
else
{
    // Note: your Midgard MVC base directory has to be in PHP include_path
    require('midgardmvc_core/framework.php');
    $midgardmvc = midgardmvc_core::get_instance('midgard');
}

// code- elements used for things run before output
?>
<(code-global)>
<?php
if (mgd_is_element_loaded('code-init'))
{
    ?><(code-init)><?php
}
elseif (isset($midgardmvc))
{
    // Call the controller if available
    $midgardmvc->process();
}

if (isset($midgardmvc))
{
    // Serve the request
    $midgardmvc->serve();
}
else
{
    ?><(ROOT)><?php
}
// code-finish can be used for custom caching etc
?>
<(code-finish)>
