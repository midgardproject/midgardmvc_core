<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * This class provides the setUp and tearDown methods needed for unit testing classes
 * that expect to have a working Midgard environment available.
 *
 * @package midgardmvc_core
 */
abstract class midgardmvc_core_tests_midgard extends PHPUnit_FrameWork_TestCase
{
    private $config = null;
    public $dbtype = 'SQLite';
    
    // Ensure PHPUnit doesn't serialize/unserialize $_MIDGARD_CONNECTION
    protected $backupGlobals = false;

    // @codeCoverageIgnoreStart
    public function check_extension()
    {
        if (!extension_loaded('midgard2'))
        {
            $this->markTestSkipped('Midgard extension is not available');
        }
    }
    
    public function check_dbtype()
    {
        if (is_null($this->dbtype))
        {
            // FIXME: better error ??
            $this->markTestSkipped('You need to provide a valid libgda database type');
        }
    }

    public function open_connection()
    {
        // Open connection
        $midgard = midgard_connection::get_instance();

        $this->config = new midgard_config();
        $this->config->dbtype = $this->dbtype;
        $this->config->database = 'midgardmvc_test';
        $this->config->blobdir = "/tmp/midgardmvc_test";
        $this->config->tablecreate = true;
        $this->config->tableupdate = true;
        $this->config->loglevel = 'critical';
 
        if (!$midgard->open_config($this->config))
        {
            $this->markTestSkipped('Could not open Midgard connection to test database: ' . $midgard->get_error_string());
            return false;
        }
        
        return true;
    }

    public function prepare_storage()
    {
        // Generate tables
        midgard_storage::create_base_storage();

        // And update as necessary
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
            
            if (midgard_storage::class_storage_exists($type))
            {
                // FIXME: Skip updates until http://trac.midgard-project.org/ticket/1426 is fixed
                continue;

                if (!midgard_storage::update_class_storage($type))
                {
                    $this->markTestSkipped('Could not update ' . $type . ' tables in test database');
                }
                continue;
            }
            if (!midgard_storage::create_class_storage($type))
            {
                $this->markTestSkipped('Could not create ' . $type . ' tables in test database');
            }
        }
        // And update as necessary
        return;
        
        if (!midgard_user::auth('root', 'password'))
        {
            echo "auth failed\n";
            $this->markTestSkipped('Could not authenticate as ROOT');
        }
    }

    /**
     * Make a special Midgard DB for tests
     */
    public function setUp()
    {
        $this->check_extension();        
        $this->check_dbtype();
        
        static $connection = false;
        if (!$connection)
        {
            $connection = $this->open_connection();
            $this->prepare_storage();
            $this->config->create_blobdir();
        }
    }

    public function tearDown()
    {
        
        /**
         * FIXME: Delete the database here. No API for it now
         */
        // $midgard->close();
    }
    // @codeCoverageIgnoreEnd
}
?>
