<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Comet helper class
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_helpers_comet
{
    public function __construct() {}
    
    static function set_html_headers()
    {
        midgardmvc_core::get_instance()->head->enable_jsmidgardmvc();
        midgardmvc_core::get_instance()->head->add_jsfile(MIDGARDMVC_STATIC_URL . "/midgardmvc_core/helpers/comet.js");
    }
    
    static function pushdata($data, $type=1, $name='')
    {
		switch ($type)
		{
			case 1:
				echo "<end />".$data;
				echo str_pad('', 4096)."\n";
			break;					
			case 2:
				header("Content-type: application/x-dom-event-stream");

				print "Event: $name\n";
				print "data: $data\n\n";				
			break;				
			case 3:
				print "<script>parent._cometObject.event.push(\"{$data}\")</script>";
			break;
		}
    }
}
?>