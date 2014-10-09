<?php
/**
 * ILS driver test
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Kyle McGrogan <km7717@ship.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\ILS\Driver;
use VuFind\ILS\Driver\MultiBackend, VuFind\Config\Reader as ConfigReader;

/**
 * ILS driver test
 *
 * @category VuFind2
 * @package  Tests
 * @author   Kyle McGrogan <km7717@ship.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class MultiBackendTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test that driver complains about missing configuration.
     *
     * @return void
     */
    public function testMissingConfiguration()
    {

        $this->setExpectedException('VuFind\Exception\ILS');
        $test = new MultiBackend(
            new \VuFind\Config\PluginManager(), $this->getMockILSAuthenticator()
        );
        $test->init();
    }

    /**
     * Test that MultiBackend can be properly initialized.
     *
     * @return void
     */
    public function testInitialization()
    {
        $driver = $this->getDriver();
        $driverList = $this->getProperty($driver, 'drivers');
        $this->assertNotNull($driverList);
    }

    /**
     *  Tests that getDriverConfig works correctly
     *
     *  @return void
     */
    public function testGetDriverConfig()
    {
        $configData = array('config' => 'values');
        $driver = $this->getDriver();
        $val = $this->callMethod($driver, 'getDriverConfig', array('good'));
        $this->assertEquals($configData, $val);


        $config = new \Zend\Config\Config($configData);
        $mockPM = $this->getMock('\VuFind\Config\PluginManager');
        $mockPM->expects($this->any())
            ->method('get')
            ->will(
                $this->throwException(new \Zend\Config\Exception\RuntimeException())
            );
        $driver = new MultiBackend($mockPM, $this->getMockILSAuthenticator());
        $driver->setConfig(array('Drivers' => array()));
        $driver->init();
        $val = $this->callMethod($driver, 'getDriverConfig', array('bad'));
        $this->assertEquals(array(), $val);
    }

    /**
     * Test that MultiBackend can properly find a driver and pass
     * log in credentials to it
     *
     * @return void
     */
    public function testPatronLogin()
    {
        $driver = $this->getDriver();
        $patronReturn = $this->getUserObject('username');
        $instance = 'institution';

        //Set up the mock object and prepare its expectations
        $ILS = $this->getMockILS('Voyager', array('patronLogin'));
        $ILS->expects($this->at(0))
            ->method('patronLogin')
            ->with('username', 'password')
            ->will($this->returnValue($patronReturn));
        $ILS->expects($this->at(1))
            ->method('patronLogin')
            ->with('username', 'password')
            ->will($this->returnValue($patronReturn));

        //Prep MultiBackend with values it will need
        $drivers = array($instance => 'Voyager');
        $isInit = array($instance => true);
        $cache = array($instance => $ILS);
        $delimiters = array('login' => "\t");
        $this->setProperty($driver, 'drivers', $drivers);
        $this->setProperty($driver, 'isInitialized', $isInit);
        $this->setProperty($driver, 'cache', $cache);
        $this->setproperty($driver, 'delimiters', $delimiters);

        //Call the method
        $patron = $driver->patronLogin("$instance\tusername", 'password');

        //Check that it added username info properly.
        $this->assertSame(
            $instance."\t".$patronReturn['cat_username'],
            $patron['cat_username']
        );

        $patron = $driver->patronLogin("$instance\tusername", 'password');
        $this->assertSame(
            $instance."\t".$patronReturn['cat_username'],
            $patron['cat_username']
        );

        $this->setExpectedException('VuFind\Exception\ILS');
        $driver->patronLogin("bad", "info");
    }

    /**
     * Test that MultiBackend can properly initialize a driver it
     * is given, and cache it.
     *
     * @return void
     */
    public function testInitializeDriver()
    {
        $driver = $this->getDriver();
        //Set up the mock driver to be initialized.
        $ILS = $this->getMockILS('Voyager', array('init'));
        $ILS->expects($this->once())
            ->method('init');

        //Run the test method
        $this->callMethod($driver, 'initializeDriver', array($ILS, 'test'));

        //Check the cache arrays
        $isInit = $this->getProperty($driver, 'isInitialized');
        $cache = $this->getproperty($driver, 'cache');
        $this->assertSame($ILS, $cache['test']);
        $this->assertTrue($isInit['test']);

        $this->setProperty($driver, 'isInitialized', array());
        $d = new \VuFind\ILS\Driver\Voyager(new \VuFind\Date\Converter());
        $this->setExpectedException('VuFind\Exception\ILS');
        $this->callMethod($driver, 'initializeDriver', array($d, 'fail'));
    }

    /**
     * Test that MultiBackend can properly retrieve an uninitialized
     * driver.
     *
     * @return void
     */
    public function testGetUninitializedDriver()
    {
        $driver = $this->getDriver();
        //Set up the mock driver to be retrieved
        $ILS = $this->getMockILS('Voyager', array('setConfig'));
        $ILS->expects($this->once())
            ->method('setConfig')
            ->with(array('config' => 'values'));

        //Set up the ServiceLocator so it returns our mock driver
        $sm = $this->getMockSM($this->once(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);
        //Add an entry for our test driver to the array of drivers
        $drivers = array('testing' => 'Voyager');
        $this->setProperty($driver, 'drivers', $drivers);


        //Case: A driver is associated with the given name
            //Result: Return that driver. Cached, but not initialized.
        $unInitDriver = $this->callMethod(
            $driver,
            'getUninitializedDriver',
            array('testing')
        );
        $this->assertEquals($ILS, $unInitDriver);

        //Check the cache arrays to make sure they get set correctly
        $isInit = $this->getProperty($driver, 'isInitialized');
        $cache = $this->getproperty($driver, 'cache');
        $this->assertEquals($ILS, $cache['testing']);
        $this->assertFalse($isInit['testing']);

        //Case: No driver associated with that name exists
            //Result: Return of null
        $unInitDriver = $this->callMethod(
            $driver,
            'getUninitializedDriver',
            array('noDriverWithThisName')
        );
        $this->assertNull($unInitDriver);
    }

    /**
     * Test that MultiBackend can properly retrieve a new driver
     * Almost the same as testing the Uninitialized driver, we just
     * have to expect it to get initialized.
     *
     * @return void
     */
    public function testGetDriver()
    {
        $driver = $this->getDriver();
        //Set up the mock driver to be retrieved
        $ILS = $this->getMockILS('Voyager', array('init', 'setConfig'));
        $ILS->expects($this->exactly(2))
            ->method('init');
        $ILS->expects($this->once())
            ->method('setConfig')
            ->with(array('config' => 'values'));

        //Set up the ServiceLocator so it returns our mock driver
        $sm = $this->getMockSM($this->once(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);

        //Add an entry for our test driver to the array of drivers
        $drivers = array('testing2' => 'Voyager');
        $this->setProperty($driver, 'drivers', $drivers);

        $returnDriver = $this->callMethod($driver, 'getDriver', array('testing2'));
        $this->assertEquals($ILS, $returnDriver);

        $this->setProperty($driver, 'isInitialized', array());
        $returnDriver = $this->callMethod($driver, 'getDriver', array('testing2'));
        $this->assertEquals($ILS, $returnDriver);

        $returnDriver
            = $this->callMethod($driver, 'getDriver', array('nonexistant'));
        $this->assertNull($returnDriver);
    }

    /**
     * Test that MultiBackend can properly tell whether or not
     * a driver is has contains a specified method.
     *
     * @return void
     */
    public function testSupportsMethod()
    {
        $driver = $this->getDriver();
        //Set up the mock driver to be retrieved
        $ILS = $this->getMockILS('Voyager', array('setConfig', 'init'));
        $ILS->expects($this->once())
            ->method('setConfig')
            ->with(array('config' => 'values'));
        $ILS->expects($this->once())
            ->method('init');

        //Set up the ServiceLocator so it returns our mock driver
        $sm = $this->getMockSM($this->once(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);

        //Add an entry for our test driver to the array of drivers
        $drivers = array('testing3' => 'Voyager');
        $this->setProperty($driver, 'drivers', $drivers);
        $this->setProperty($driver, 'delimiters', array('login' => "\t"));

        //Case: No driver info in params, but default driver has method
            //Result: A return of false

        $this->setProperty($driver, 'defaultDriver', 'testing3');
        $methodReturn = $driver->supportsMethod('fail', null);
        $this->assertFalse($methodReturn);

        //Case: No driver info in params, though default driver has method
            //Result: A return of true

        $methodReturn = $driver->supportsMethod('getStatus', null);
        $this->assertTrue($methodReturn);
        $this->setProperty($driver, 'defaultDriver', null);


        //Case: Instance to use is in parameters but does not have method
            //Result: A return of false

        $patron = array($this->getUserObject('username', 'testing3'));
        $methodReturn = $driver->supportsMethod('fail', $patron);
        $this->assertFalse($methodReturn);

        //Case: Instance to use is in parameters and has method
            //Result: A return of true

        $methodReturn = $driver->supportsMethod('getStatus', $patron);
        $this->assertTrue($methodReturn);

        //Case: The method doesn't exists in any of the drivers
            //Result: A return of false

        $methodReturn = $driver->supportsMethod('fail', null);
        $this->assertFalse($methodReturn);
    }

    /**
     * Testing method for addIdPrefixes
     *
     * @return void
     */
    public function testAddIdPrefixes()
    {
        $driver = $this->getDriver();
        $source = 'source';
        $data = array();

        $result = $this->callMethod($driver, 'addIdPrefixes', array($data, $source));
        $this->assertEquals($data, $result);

        $data = array(
            "id" => "record1",
            "cat_username" => "record2"
        );
        $expected = array(
            "id" => "$source.record1",
            "cat_username" => "$source.record2");
        $result = $this->callMethod($driver, 'addIdPrefixes', array($data, $source));
        $this->assertEquals($expected, $result);

        $data = array(
            "id" => "record1",
            "cat_username" =>array(
                "id" => "record2",
                "cat_username" => array(
                    "id" => "record3",
                    "cat_username" => "record4"),
                "cat_info" => "record5"),
            "cat_info" => "record6"
        );
        $expected = array(
            "id" => "$source.record1",
            "cat_username" => array(
                "id" => "$source.record2",
                "cat_username" => array(
                    "id" => "$source.record3",
                    "cat_username" => "$source.record4"),
                "cat_info" => "$source.record5"),
            "cat_info" => "$source.record6"
        );
        $modify = array("id", "cat_username", "cat_info");
        $result = $this->callMethod(
            $driver, 'addIdPrefixes', array($data, $source, $modify)
        );
        $this->assertEquals($expected, $result);
    }


    /**
     * Testing method for stripIdPrefixes
     *
     * @return void
     */
    public function testStripIdPrefixes()
    {
        $driver = $this->getDriver();
        $source = 'source';
        $data = array();

        $result
            = $this->callMethod($driver, 'stripIdPrefixes', array($data, $source));
        $this->assertEquals($data, $result);

        $data = "$source.record";
        $result
            = $this->callMethod($driver, 'stripIdPrefixes', array($data, $source));
        $this->assertEquals("record", $result);

        $delimiters = array('login' => "\t");
        $this->setproperty($driver, 'delimiters', $delimiters);
        $expected = array(
            "id" => "record1",
            "cat_username" => "record2"
        );
        $data = array(
            "id" => "$source.record1",
            "cat_username" => "$source\trecord2");
        $result
            = $this->callMethod($driver, 'stripIdPrefixes', array($data, $source));
        $this->assertEquals($expected, $result);

        $expected = array(
            "id" => "record1",
            "cat_username" =>array(
                "id" => "record2",
                "cat_username" => array(
                    "id" => "record3",
                    "cat_username" => "record4"),
                "cat_info" => "record5"),
            "cat_info" => "record6"
        );
        $data = array(
            "id" => "$source.record1",
            "cat_username" => array(
                "id" => "$source.record2",
                "cat_username" => array(
                    "id" => "$source.record3",
                    "cat_username" => "$source\trecord4"),
                "cat_info" => "$source.record5"),
            "cat_info" => "$source.record6"
        );
        $modify = array("id", "cat_username", "cat_info");
        $result = $this->callMethod(
            $driver, 'stripIdPrefixes', array($data, $source, $modify)
        );
        $this->assertEquals($expected, $result);
    }


    /**
     * Testing method for getHolding
     *
     * @return void
     */
    public function testGetHolding()
    {
        $driver = $this->getDriver();
        $drivers = array("d1" => "Voyager");
        $this->setProperty($driver, 'drivers', $drivers);
        $id = '123456';

        $ILS = $this->getMockILS("Voyager", array('init', 'getHolding'));
        $ILS->expects($this->exactly(2))
            ->method('getHolding')
            ->with(
                $this->logicalOr(
                    $this->equalTo('123456'),
                    $this->equalTo('654321')
                )
            )
            ->will(
                $this->returnCallback(
                    function ($param) {
                        if ($param == '123456') {
                            return array("id" => "123456", "status" => "in");
                        }
                        return array();
                    }
                )
            );

        $sm = $this->getMockSM($this->any(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);

        $expectedReturn = array("id" => "d1.123456", "status" => "in");
        $return = $driver->getHolding("d1.$id");
        $this->assertEquals($expectedReturn, $return);

        $return = $driver->getHolding("fail.$id");
        $this->assertEquals(array(), $return);

        $return = $driver->getHolding("d1.654321");
        $this->assertEquals(array(), $return);
    }

    /**
     * Testing method for getPurchaseHistory
     *
     * @return void
     */
    public function testGetPurchaseHistory()
    {
        $driver = $this->getDriver();
        $drivers = array("d1" => "Voyager");
        $this->setProperty($driver, 'drivers', $drivers);
        $id = 'd1.123456';

        $driverReturn = array("purchases" => "123456");
        $ILS = $this->getMockILS("Voyager", array('init', 'getPurchaseHistory'));
        $ILS->expects($this->once())
            ->method('getPurchaseHistory')
            ->with('123456')
            ->will($this->returnValue($driverReturn));


        $sm = $this->getMockSM($this->any(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);

        $return = $driver->getPurchaseHistory($id);
        $this->assertEquals($driverReturn, $return);

        $return = $driver->getPurchaseHistory("fail.$id");
        $this->assertEquals(array(), $return);
    }


    /**
     * Testing method for getStatuses
     *
     * @return void
     */
    public function testGetStatuses()
    {
        $driver = $this->getDriver();
        $drivers = array("d1" => "Voyager");
        $this->setProperty($driver, 'drivers', $drivers);

        $ILS = $this->getMockILS("Voyager", array('init', 'getStatus'));
        $ILS->expects($this->exactly(4))
            ->method('getStatus')
            ->with(
                $this->logicalOr(
                    $this->equalTo('123456'),
                    $this->equalTo('654321'),
                    $this->equalTo('567890'),
                    $this->equalTo('098765')
                )
            )
            ->will(
                $this->returnCallback(
                    function ($param) {
                        $r_arr = array("id" => $param);
                        if ($param == '123456') {
                            $r_arr['status'] = 'in';
                        } elseif ($param == '654321') {
                            $r_arr['status'] = 'out';
                        } elseif ($param == '567890') {
                            $r_arr['status'] = 'in';
                        } else {
                            $r_arr['status'] = 'out';
                        }
                        return $r_arr;
                    }
                )
            );

        $sm = $this->getMockSM($this->any(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);

        $ids = array(
            'd1.123456', 'd1.098765', 'd1.654321', 'd1.567890'
        );
        $expectedReturn = array(
            array("id" => "d1.123456", "status" => "in"),
            array("id" => "d1.098765", "status" => "out"),
            array("id" => "d1.654321", "status" => "out"),
            array("id" => "d1.567890", "status" => "in")
        );
        $return = $driver->getStatuses($ids);
        $this->assertEquals($expectedReturn, $return);

        $return = $driver->getStatuses(array());
        $this->assertEquals(array(), $return);
    }

    /**
     * This method tests getLocalId.
     *
     * @return mixed A MultiBackend instance.
     */
    public function testGetLocalId()
    {
        $driver = $this->getDriver();
        $term = "source.local";
        $return = $this->callMethod($driver, 'getLocalId', array($term));
        $this->assertEquals("local", $return);

        $return = $this->callMethod($driver, 'getLocalId', array($term, '!'));
        $this->assertEquals($term, $return);
    }

    /**
     * Test that MultiBackend can find and use the default ILS driver if parameters
     * don't include a detectable source id
     *
     * @return void
     */
    public function testDefaultDriver()
    {
        $driver = $this->getDriver();
        //Case: The parameters let it know what driver to use
            //Result: return the function results for that driver
        $patron = $this->getUserObject('username', 'institution');

        $delimiters = array('login' => "\t");
        $drivers = array(
            'otherinst' => 'Unicorn',
            'institution' => 'Voyager'
        );
        $this->setProperty($driver, 'delimiters', $delimiters);
        $this->setProperty($driver, 'drivers', $drivers);

        $patronPrefixless = $this->callMethod(
            $driver, 'stripIdPrefixes', array($patron, 'institution')
        );

        $ILS = $this->getMockILS('Voyager', array('getMyTransactions', 'init'));
        $ILS->expects($this->atLeastOnce())
            ->method('getMyTransactions')
            ->with($patronPrefixless)
            ->will($this->returnValue(true));

        $sm = $this->getMockSM($this->any(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);

        $returnVal = $driver->getMyTransactions($patron);
        $this->assertTrue($returnVal);


        //Case: There is a default driver set in the configuration
            //Result: return the function results for that driver

        // We need to clear patron login information so that MultiBackend has to
        // fall back on the defaultDriver implementation
        $patron['cat_username'] = 'username';

        $ILS = $this->getMockILS('Unicorn', array('getMyTransactions', 'init'));
        $ILS->expects($this->atLeastOnce())
            ->method('getMyTransactions')
            ->with($patron)
            ->will($this->returnValue(true));

        $sm = $this->getMockSM($this->any(), 'Unicorn', $ILS);
        $driver->setServiceLocator($sm);

        $this->setProperty($driver, 'defaultDriver', 'otherinst');
        $returnVal = $driver->getMyTransactions($patron);
        $this->assertTrue($returnVal);
    }

    /**
     * Method to get a fresh MultiBackend Driver.
     *
     * @return mixed A MultiBackend instance.
     */
    protected function getDriver()
    {
        $driver = new MultiBackend(
            $this->getPluginManager(), $this->getMockILSAuthenticator()
        );
        $driver->setConfig(array('Drivers' => array()));
        $driver->init();
        return $driver;
    }

    /**
     * Get a mock ILS authenticator
     *
     * @return \VuFind\Auth\ILSAuthenticator
     */
    protected function getMockILSAuthenticator()
    {
        return $this->getMockBuilder('VuFind\Auth\ILSAuthenticator')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Method to get a fresh Plugin Manager.
     *
     * @return mixed A MultiBackend instance.
     */
    protected function getPluginManager()
    {
        $configData = array('config' => 'values');
        $config = new \Zend\Config\Config($configData);
        $mockPM = $this->getMock('\VuFind\Config\PluginManager');
        $mockPM->expects($this->any())
            ->method('get')
            ->will($this->returnValue($config));
        return $mockPM;
    }

    /**
     * Method to get a user object with the given username
     *
     * @param string $username The username to use
     * @param string $instance The instance to append before the username
     *
     * @return array A patron object.
     */
    protected function getUserObject($username, $instance = null)
    {
        $cat_username = $instance ? $instance."\t".$username : $username;
        return array(
                    'id' => 1,
                    'firstname' => 'JANE',
                    'lastname' => 'DOE',
                    'cat_username' => $cat_username,
                    'cat_password' => 'password',
                    'email' => '',
                    'major' => '',
                    'college' => ''
        );
    }

    /**
     * This function returns a mock service manager with the given parameters
     * For examples of what is to be passed, see:
     * http://www.phpunit.de/manual/3.0/en/mock-objects.html
     *
     * @param object $times  The number of times it is expected to be called.
     * @param object $driver The driver type this SM will expect to be called with.
     * @param mixed  $return What that get function should return.
     *
     * @return object The Mock Service Manager created.
     */
    protected function getMockSM($times, $driver, $return)
    {
        $sm = $this->getMockForAbstractClass(
            'Zend\ServiceManager\ServiceLocatorInterface'
        );
        $sm->expects($times)
            ->method('get')
            ->with($driver)
            ->will($this->returnValue($return));
        return $sm;
    }

    /**
     * Get a mock driver
     *
     * @param string $type    Type of driver to make
     * @param array  $methods Array of methods to stub
     *
     * @return \VuFind\ILS\Driver\$type
     */
    protected function getMockILS($type, $methods = null)
    {
        $mock = null;
        try {
            $mock = $this->getMock(
                "VuFind\ILS\Driver\\$type", $methods,
                array(new \VuFind\Date\Converter())
            );
        }catch(\Exception $e){
            $mock = $this->getMock(
                "VuFind\ILS\Driver\\$type", $methods
            );
        }
        if ($methods && in_array('init', $methods)) {
            $mock->expects($this->any())
                ->method('init')
                ->will($this->returnValue(null));
        }
        $mock->setConfig(array('dummy_config' => true));
        return $mock;
    }

}

