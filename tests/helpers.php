<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

if (! defined('MIDGARDMVC_TESTS_ENABLE_OUTPUT')) 
{
    define('MIDGARDMVC_TESTS_ENABLE_OUTPUT', true);
}

/**
 * @package midgardmvc_core
 */
abstract class midgardmvc_core_tests_helpers
{
    public static function get_tests($directory, $root_class, $add_skip = null)
    {
        $tests = array();
        $skip = array
        (
            'all.php'
        );
        if (is_array($add_skip))
        {
            $skip = array_merge($skip, $add_skip);
        }
        $skip = array_flip($skip);
        
        if (!is_dir($directory))
        {
            $path_parts = pathinfo($directory);
            $directory = $path_parts['dirname'];
        }
        $tests_dir = dir($directory);
        if (!$tests_dir)
        {
            return $tests;
        }
        
        $prefix = str_replace('_all', '', $root_class);

        while(($testfile = $tests_dir->read()) !== false)
        {
            if (   array_key_exists($testfile, $skip)
                || substr($testfile, 0, 1) == '.') 
            {
                continue;
            }
            
            $filepath = realpath($directory) . "/{$testfile}";
            
            if (is_dir($filepath))
            {
                $tests = array_merge($tests, midgardmvc_core_tests_helpers::get_tests($filepath, "{$prefix}_{$testfile}"));
                continue;
            }

            $path_parts = pathinfo($filepath);
            if ($path_parts['extension'] != 'php')
            {
                continue;
            }
            
            require_once($filepath);
            $test_class = "{$prefix}_{$path_parts['filename']}";
            $tests[] = $test_class;
        }
        $tests_dir->close();
        return $tests;
    }
}
?>
