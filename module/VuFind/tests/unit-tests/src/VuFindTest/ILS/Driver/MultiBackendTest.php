<?php
/**
 * ILS driver test
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2014.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\ILS\Driver;
use VuFind\ILS\Driver\MultiBackend, VuFind\Config\Reader as ConfigReader;
use VuFind\Auth\MultiAuth;

/**
 * ILS driver test
 *
 * @category VuFind2
 * @package  Tests
 * @author   Kyle McGrogan <km7717@ship.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
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
        $this->setProperty($driver, 'drivers', $drivers);
        $this->setProperty($driver, 'isInitialized', $isInit);
        $this->setProperty($driver, 'cache', $cache);

        //Call the method
        $patron = $driver->patronLogin("$instance.username", 'password');

        //Check that it added username info properly.
        $this->assertSame(
            $instance . '.' . $patronReturn['cat_username'],
            $patron['cat_username']
        );

        // Try with another delimiter
        $delimiters = array('login' => "\t");
        $this->setproperty($driver, 'delimiters', $delimiters);

        //Call the method
        $patron = $driver->patronLogin("$instance\tusername", 'password');

        //Check that it added username info properly.
        $this->assertSame(
            $instance . "\t" . $patronReturn['cat_username'],
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
            'id' => 'record1',
            'cat_username' => 'record2'
        );
        $expected = array(
            'id' => "$source.record1",
            'cat_username' => "$source.record2"
        );
        $result = $this->callMethod($driver, 'addIdPrefixes', array($data, $source));
        $this->assertEquals($expected, $result);

        $data = array(
            'id' => 'record1',
            'cat_username' => array(
                'id' => 'record2',
                'cat_username' => array(
                    'id' => 'record3',
                    'cat_username' => 'record4'
                ),
                'cat_info' => 'record5',
                'other' => 'something'
            ),
            'cat_info' => 'record6'
        );
        $expected = array(
            'id' => "$source.record1",
            'cat_username' => array(
                'id' => "$source.record2",
                'cat_username' => array(
                    'id' => "$source.record3",
                    'cat_username' => "$source.record4"
                ),
                'cat_info' => "$source.record5",
                'other' => 'something'
            ),
            'cat_info' => "$source.record6"
        );
        $modify = array('id', 'cat_username', 'cat_info');
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
            'id' => 'record1',
            'cat_username' => 'record2'
        );
        $data = array(
            'id' => "$source.record1",
            'cat_username' => "$source\trecord2"
        );
        $result
            = $this->callMethod($driver, 'stripIdPrefixes', array($data, $source));
        $this->assertEquals($expected, $result);

        $expected = array(
            'id' => 'record1',
            'cat_username' => array(
                'id' => 'record2',
                'cat_username' => array(
                    'id' => 'record3',
                    'cat_username' => 'record4'
                ),
                'cat_info' => 'record5',
                'other' => "$source.something"
            ),
            'cat_info' => 'record6'
        );
        $data = array(
            'id' => "$source.record1",
            'cat_username' => array(
                'id' => "$source.record2",
                'cat_username' => array(
                    'id' => "$source.record3",
                    'cat_username' => "$source\trecord4"
                ),
                'cat_info' => "$source.record5",
                'other' => "$source.something"
            ),
            'cat_info' => "$source.record6"
        );
        $modify = array('id', 'cat_username', 'cat_info');
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
        $drivers = array('d1' => 'Voyager');
        $this->setProperty($driver, 'drivers', $drivers);
        $id = '123456';

        $ILS = $this->getMockILS('Voyager', array('init', 'getHolding'));
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
                            return array('id' => '123456', 'status' => 'in');
                        }
                        return array();
                    }
                )
            );

        $sm = $this->getMockSM($this->any(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);

        $expectedReturn = array('id' => 'd1.123456', 'status' => 'in');
        $return = $driver->getHolding("d1.$id");
        $this->assertEquals($expectedReturn, $return);

        $return = $driver->getHolding("fail.$id");
        $this->assertEquals(array(), $return);

        $return = $driver->getHolding('d1.654321');
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
        $drivers = array('d1' => 'Voyager');
        $this->setProperty($driver, 'drivers', $drivers);
        $id = 'd1.123456';

        $driverReturn = array('purchases' => '123456');
        $ILS = $this->getMockILS('Voyager', array('init', 'getPurchaseHistory'));
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
     * Testing method for getLoginDrivers
     *
     * @return void
     */
    public function testGetLoginDrivers()
    {
        $driver = $this->getDriver();

        $result = $driver->getLoginDrivers();
        $this->assertEquals(array('d1', 'd2'), $result);
    }

    /**
     * Testing method for getDefaultLoginDriver
     *
     * @return void
     */
    public function testGetDefaultLoginDriver()
    {
        $driver = $this->getDriver();

        $result = $driver->getDefaultLoginDriver();
        $this->assertEquals('d1', $result);

        $driver->setConfig(
            array(
                'Drivers' => array(),
                'Login' => array(
                    'drivers' => array('d2', 'd1')
                )
            )
        );

        $result = $driver->getDefaultLoginDriver();
        $this->assertEquals('d2', $result);
    }

    /**
     * Testing method for getStatus
     *
     * @return void
     */
    public function testGetStatus()
    {
        $driver = $this->getDriver();
        $drivers = array('d1' => 'Voyager');
        $this->setProperty($driver, 'drivers', $drivers);

        $ILS = $this->getMockILS('Voyager', array('init', 'getStatus'));
        $ILS->expects($this->exactly(2))
            ->method('getStatus')
            ->with(
                $this->logicalOr(
                    $this->equalTo('123456'),
                    $this->equalTo('654321')
                )
            )
            ->will(
                $this->returnCallback(
                    function ($param) {
                        $r_arr = array('id' => $param);
                        if ($param == '123456') {
                            $r_arr['status'] = 'in';
                        } elseif ($param == '654321') {
                            $r_arr['status'] = 'out';
                        } else {
                            $r_arr['status'] = 'out';
                        }
                        return $r_arr;
                    }
                )
            );

        $sm = $this->getMockSM($this->any(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);

        $return = $driver->getStatus('d1.123456');
        $this->assertEquals(array('id' => 'd1.123456', 'status' => 'in'), $return);

        $return = $driver->getStatus('d1.654321');
        $this->assertEquals(array('id' => 'd1.654321', 'status' => 'out'), $return);

        $return = $driver->getStatus('invalid.654321');
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
        $drivers = array('d1' => 'Voyager');
        $this->setProperty($driver, 'drivers', $drivers);

        $ILS = $this->getMockILS('Voyager', array('init', 'getStatus'));
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
                        $r_arr = array('id' => $param);
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
            array('id' => "d1.123456", 'status' => 'in'),
            array('id' => "d1.098765", 'status' => 'out'),
            array('id' => "d1.654321", 'status' => 'out'),
            array('id' => "d1.567890", 'status' => 'in')
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

        $drivers = array(
            'otherinst' => 'Unicorn',
            'institution' => 'Voyager'
        );
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
     * Testing method for getNewItems
     *
     * @return void
     */
    public function testGetNewItems()
    {
        $expected = array('test' => 'true');
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->never(),
            'getNewItems',
            array(1, 10, 5, 0),
            $expected,
            $expected
        );

        // getNewItems only works with a default driver, so the first calls fails
        $result = $driver->getNewItems(1, 10, 5, 0);
        $this->assertEquals(array(), $result);

        $this->setProperty($driver, 'defaultDriver', 'd1');
        $result = $driver->getNewItems(1, 10, 5, 0);
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method for findReserves
     *
     * @return void
     */
    public function testFindReserves()
    {
        $expected = array('test' => 'true');
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->never(),
            'findReserves',
            array('course', 'inst', 'dept'),
            $expected,
            $expected
        );

        // findReserves only works with a default driver, so the first calls fails
        $result = $driver->findReserves('course', 'inst', 'dept');
        $this->assertEquals(array(), $result);

        $this->setProperty($driver, 'defaultDriver', 'd1');
        $result = $driver->findReserves('course', 'inst', 'dept');
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method for getMyProfile
     *
     * @return void
     */
    public function testGetMyProfile()
    {
        $expected1 = array('test' => '1');
        $expected2 = array('test' => '2');
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getMyProfile',
            array($this->getUserObject('username')),
            $expected1,
            $expected2
        );

        $result = $driver->getMyProfile($this->getUserObject('username', 'invalid'));
        $this->assertEquals(array(), $result);

        $result = $driver->getMyProfile($this->getUserObject('username', 'd1'));
        $this->assertEquals($expected1, $result);

        $result = $driver->getMyProfile($this->getUserObject('username', 'd2'));
        $this->assertEquals($expected2, $result);
    }

    /**
     * Testing method for getMyTransactions
     *
     * @return void
     */
    public function testGetMyTransactions()
    {
        $expected1 = array(array('id' => 'd1.1'));
        $expected2 = array(array('id' => 'd2.1'));
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getMyTransactions',
            array($this->getUserObject('username')),
            array(array('id' => '1')),
            array(array('id' => '1'))
        );

        $result = $driver->getMyTransactions($this->getUserObject('username', 'd1'));
        $this->assertEquals($expected1, $result);

        $result = $driver->getMyTransactions($this->getUserObject('username', 'd2'));
        $this->assertEquals($expected2, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getMyTransactions(
            $this->getUserObject('username', 'invalid')
        );
    }

    /**
     * Testing method for getRenewDetails
     *
     * @return void
     */
    public function testGetRenewDetails()
    {
        $expected1 = array(array('id' => 'd1.1'));
        $expected2 = array(array('id' => 'd2.1'));
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getRenewDetails',
            array(array('id' => 'loanid')),
            array(array('id' => '1')),
            array(array('id' => '1'))
        );

        $result = $driver->getRenewDetails(
            array(
                'id' => 'd1.loanid'
            )
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->getRenewDetails(
            array(
                'id' => 'd2.loanid'
            )
        );
        $this->assertEquals($expected2, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getRenewDetails(
            array(
                'id' => 'invalid.loanid'
            )
        );
    }

    /**
     * Testing method for renewMyItems
     *
     * @return void
     */
    public function testRenewMyItems()
    {
        $expected1 = array(
            array('id' => 'd1.1'),
            array('id' => 'd1.2')
        );
        $expected2 = array(
            array('id' => 'd2.1'),
            array('id' => 'd2.2')
        );
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'renewMyItems',
            array(array('patron' => $this->getUserObject('username'))),
            array(array('id' => '1'), array('id' => '2')),
            array(array('id' => '1'), array('id' => '2'))
        );

        $result = $driver->renewMyItems(
            array('patron' => $this->getUserObject('username', 'd1'))
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->renewMyItems(
            array('patron' => $this->getUserObject('username', 'd2'))
        );
        $this->assertEquals($expected2, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->renewMyItems(
            array('patron' => $this->getUserObject('username', 'invalid'))
        );
    }

    /**
     * Testing method for getMyFines
     *
     * @return void
     */
    public function testGetMyFines()
    {
        $expected1 = array(array('id' => 'd1.1'));
        $expected2 = array(array('id' => 'd2.1'));
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getMyFines',
            array($this->getUserObject('username')),
            array(array('id' => '1')),
            array(array('id' => '1'))
        );

        $result = $driver->getMyFines($this->getUserObject('username', 'd1'));
        $this->assertEquals($expected1, $result);

        $result = $driver->getMyFines($this->getUserObject('username', 'd2'));
        $this->assertEquals($expected2, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getMyFines($this->getUserObject('username', 'invalid'));
    }

    /**
     * Testing method for getMyHolds
     *
     * @return void
     */
    public function testGetMyHolds()
    {
        $expected1 = array(array('id' => 'd1.1'));
        $expected2 = array(array('id' => 'd2.1'));
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getMyHolds',
            array($this->getUserObject('username')),
            array(array('id' => '1')),
            array(array('id' => '1'))
        );

        $result = $driver->getMyHolds($this->getUserObject('username', 'd1'));
        $this->assertEquals($expected1, $result);

        $result = $driver->getMyHolds($this->getUserObject('username', 'd2'));
        $this->assertEquals($expected2, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getMyHolds($this->getUserObject('username', 'invalid'));
    }

    /**
     * Testing method for getMyStorageRetrievalRequests
     *
     * @return void
     */
    public function testGetMyStorageRetrievalRequests()
    {
        $expected1 = array(array('id' => 'd1.1'));
        $expected2 = array(array('id' => 'd2.1'));
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getMyStorageRetrievalRequests',
            array($this->getUserObject('username')),
            array(array('id' => '1')),
            array(array('id' => '1'))
        );

        $result = $driver->getMyStorageRetrievalRequests(
            $this->getUserObject('username', 'd1')
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->getMyStorageRetrievalRequests(
            $this->getUserObject('username', 'd2')
        );
        $this->assertEquals($expected2, $result);

        // Try handling of unsupported request
        $result = $driver->getMyStorageRetrievalRequests(
            $this->getUserObject('username', 'd3')
        );
        $this->assertEquals(array(), $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getMyStorageRetrievalRequests(
            $this->getUserObject('username', 'invalid')
        );
    }

    /**
     * Testing method for checkRequestIsValid
     *
     * @return void
     */
    public function testCheckRequestIsValid()
    {
        $expected1 = true;
        $expected2 = false;
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'checkRequestIsValid',
            array(
                'bibid',
                array('id' => 'itemid'),
                $this->getUserObject('username')
            ),
            true,
            false
        );

        $result = $driver->checkRequestIsValid(
            'd1.bibid',
            array(
                'id' => 'd1.itemid'
            ),
            $this->getUserObject('username', 'd1')
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->checkRequestIsValid(
            'd2.bibid',
            array(
                'id' => 'd2.itemid'
            ),
            $this->getUserObject('username', 'd2')
        );
        $this->assertEquals($expected2, $result);

        // Cross-driver request must not be accepted
        $result = $driver->checkRequestIsValid(
            'd1.bibid',
            array(
                'id' => 'd1.itemid'
            ),
            $this->getUserObject('username', 'd2')
        );
        $this->assertEquals(false, $result);

        // Request with a patron missing cat_username must not be accepted
        $result = $driver->checkRequestIsValid(
            'd1.bibid',
            array(
                'id' => 'd1.itemid'
            ),
            array('bad patron')
        );
        $this->assertEquals(false, $result);

        $result = $driver->checkRequestIsValid(
            'invalid.bibid',
            array(
                'id' => 'invalid.itemid'
            ),
            $this->getUserObject('username', 'invalid')
        );
        $this->assertEquals(false, $result);
    }

    /**
     * Testing method for checkStorageRetrievalRequestIsValid
     *
     * @return void
     */
    public function testCheckStorageRetrievalRequestIsValid()
    {
        $expected1 = true;
        $expected2 = false;
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'checkStorageRetrievalRequestIsValid',
            array(
                'bibid',
                array('id' => 'itemid'),
                $this->getUserObject('username')
            ),
            true,
            false
        );

        $result = $driver->checkStorageRetrievalRequestIsValid(
            'd1.bibid',
            array(
                'id' => 'd1.itemid'
            ),
            $this->getUserObject('username', 'd1')
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->checkStorageRetrievalRequestIsValid(
            'd2.bibid',
            array(
                'id' => 'd2.itemid'
            ),
            $this->getUserObject('username', 'd2')
        );
        $this->assertEquals($expected2, $result);

        // Cross-driver request must not be accepted
        $result = $driver->checkStorageRetrievalRequestIsValid(
            'd1.bibid',
            array(
                'id' => 'd1.itemid'
            ),
            $this->getUserObject('username', 'd2')
        );
        $this->assertEquals(false, $result);

        $result = $driver->checkStorageRetrievalRequestIsValid(
            'invalid.bibid',
            array(
                'id' => 'invalid.itemid'
            ),
            $this->getUserObject('username', 'invalid')
        );
        $this->assertEquals(false, $result);
    }

    /**
     * Testing method for getPickUpLocations
     *
     * @return void
     */
    public function testGetPickUpLocations()
    {
        $expected1 = array(array('locationID' => '1'));
        $expected2 = array(array('locationID' => '2'));
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getPickUpLocations',
            array(
                $this->getUserObject('username'),
                array('id' => '1')
            ),
            $expected1,
            $expected2
        );

        $result = $driver->getPickUpLocations(
            $this->getUserObject('username', 'd1'),
            array('id' => 'd1.1')
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->getPickUpLocations(
            $this->getUserObject('username', 'd2'),
            array('id' => 'd2.1')
        );
        $this->assertEquals($expected2, $result);

        // Must return empty set if sources of patron id and bib id differ
        $result = $driver->getPickUpLocations(
            $this->getUserObject('username', 'd2'),
            array('id' => 'd1.1')
        );
        $this->assertEquals(array(), $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getPickUpLocations(
            $this->getUserObject('username', 'invalid'),
            array('id' => '1')
        );
    }

    /**
     * Testing method for getDefaultPickUpLocation
     *
     * @return void
     */
    public function testGetDefaultPickUpLocation()
    {
        $expected1 = '1';
        $expected2 = '1';
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getDefaultPickUpLocation',
            array(
                $this->getUserObject('username'),
                array('id' => '1')
            ),
            $expected1,
            $expected2
        );

        $result = $driver->getDefaultPickUpLocation(
            $this->getUserObject('username', 'd1'),
            array('id' => 'd1.1')
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->getDefaultPickUpLocation(
            $this->getUserObject('username', 'd2'),
            array('id' => 'd2.1')
        );
        $this->assertEquals($expected2, $result);

        // Must return false if sources of patron id and bib id differ
        $result = $driver->getDefaultPickUpLocation(
            $this->getUserObject('username', 'd2'),
            array('id' => 'd1.1')
        );
        $this->assertEquals(false, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getDefaultPickUpLocation(
            $this->getUserObject('username', 'invalid'),
            array('id' => '1')
        );
    }

    /**
     * Testing method for getRequestGroups
     *
     * @return void
     */
    public function testGetRequestGroups()
    {
        $expected1 = array(array('locationID' => '1'));
        $expected2 = array(array('locationID' => '2'));
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getRequestGroups',
            array(
                '1',
                $this->getUserObject('username')
            ),
            $expected1,
            $expected2
        );

        $result = $driver->getRequestGroups(
            'd1.1',
            $this->getUserObject('username', 'd1')
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->getRequestGroups(
            'd2.1',
            $this->getUserObject('username', 'd2')
        );
        $this->assertEquals($expected2, $result);

        // Must return empty set if sources of patron id and bib id differ
        $result = $driver->getRequestGroups(
            'd1.1',
            $this->getUserObject('username', 'd2')
        );
        $this->assertEquals(array(), $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getRequestGroups(
            '1',
            $this->getUserObject('username', 'invalid')
        );
    }

    /**
     * Testing method for getDefaultRequestGroup
     *
     * @return void
     */
    public function testGetDefaultRequestGroup()
    {
        $expected1 = '1';
        $expected2 = '1';
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getDefaultRequestGroup',
            array(
                $this->getUserObject('username'),
                array('id' => '1')
            ),
            $expected1,
            $expected2
        );

        $result = $driver->getDefaultRequestGroup(
            $this->getUserObject('username', 'd1'),
            array('id' => 'd1.1')
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->getDefaultRequestGroup(
            $this->getUserObject('username', 'd2'),
            array('id' => 'd2.1')
        );
        $this->assertEquals($expected2, $result);

        // Must return false if sources of patron id and bib id differ
        $result = $driver->getDefaultRequestGroup(
            $this->getUserObject('username', 'd2'),
            array('id' => 'd1.1')
        );
        $this->assertEquals(false, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getDefaultRequestGroup(
            $this->getUserObject('username', 'invalid'),
            array('id' => '1')
        );
    }

    /**
     * Testing method for placeHold
     *
     * @return void
     */
    public function testPlaceHold()
    {
        $expected1 = array(
            'success' => true,
            'status' => ''
        );
        $expected2 = array(
            'success' => false,
            'status' => 'hold_error_fail'
        );
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'placeHold',
            array(array('patron' => $this->getUserObject('username'), 'id' => 1)),
            $expected1,
            $expected2
        );

        $result = $driver->placeHold(
            array(
                'patron' => $this->getUserObject('username', 'd1'),
                'id' => 'd1.1'
            )
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->placeHold(
            array(
                'patron' => $this->getUserObject('username', 'd2'),
                'id' => 'd2.1'
            )
        );
        $this->assertEquals($expected2, $result);

        // Patron/item source mismatch
        $result = $driver->placeHold(
            array(
                'patron' => $this->getUserObject('username', 'd2'),
                'id' => 'd1.1'
            )
        );
        $this->assertEquals(
            array('success' => false, 'sysMessage' => 'hold_wrong_user_institution'),
            $result
        );

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->placeHold(
            array(
                'patron' => $this->getUserObject('username', 'invalid'),
                'id' => 'invalid.1'
            )
        );
    }

    /**
     * Testing method for cancelHolds
     *
     * @return void
     */
    public function testCancelHolds()
    {
        $expected = array(
            '1' => array(
                'success' => true,
                'status' => 'hold_cancel_success'
            ),
            '2' => array(
                'success' => false,
                'status' => 'hold_cancel_fail'
            )

        );
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'cancelHolds',
            array(
                array(
                    'patron' => $this->getUserObject('username'),
                    'details' => array('1', '2')
                )
            ),
            $expected,
            $expected
        );

        $result = $driver->cancelHolds(
            array(
                'patron' => $this->getUserObject('username', 'd1'),
                'details' => array('1', '2')
            )
        );
        $this->assertEquals($expected, $result);

        $result = $driver->cancelHolds(
            array(
                'patron' => $this->getUserObject('username', 'd2'),
                'details' => array('1', '2')
            )
        );
        $this->assertEquals($expected, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->cancelHolds(
            array(
                'patron' => $this->getUserObject('username', 'invalid'),
                'details' => array('1', '2')
            )
        );
    }

    /**
     * Testing method for getCancelHoldDetails
     *
     * @return void
     */
    public function testGetCancelHoldDetails()
    {
        $expected = array('1', '2');
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getCancelHoldDetails',
            array(array('id' => '1', 'item_id' => '2')),
            $expected,
            $expected
        );

        $result = $driver->getCancelHoldDetails(
            array('id' => 'd1.1', 'item_id' => 2)
        );
        $this->assertEquals($expected, $result);

        $result = $driver->getCancelHoldDetails(
            array('id' => 'd2.1', 'item_id' => 2)
        );
        $this->assertEquals($expected, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getCancelHoldDetails(
            array('id' => 'invalid.1', 'item_id' => 2)
        );
    }

    /**
     * Testing method for placeStorageRetrievalRequest
     *
     * @return void
     */
    public function testPlaceStorageRetrievalRequest()
    {
        $expected1 = array(
            'success' => true,
            'status' => ''
        );
        $expected2 = array(
            'success' => false,
            'status' => 'storage_retrieval_request_error_blocked'
        );
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'placeStorageRetrievalRequest',
            array(array('patron' => $this->getUserObject('username'), 'id' => 1)),
            $expected1,
            $expected2
        );

        $result = $driver->placeStorageRetrievalRequest(
            array(
                'patron' => $this->getUserObject('username', 'd1'),
                'id' => 'd1.1'
            )
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->placeStorageRetrievalRequest(
            array(
                'patron' => $this->getUserObject('username', 'd2'),
                'id' => 'd2.1'
            )
        );
        $this->assertEquals($expected2, $result);

        // Patron/item source mismatch
        $result = $driver->placeStorageRetrievalRequest(
            array(
                'patron' => $this->getUserObject('username', 'd2'),
                'id' => 'd1.1'
            )
        );
        $this->assertEquals(
            array('success' => false, 'sysMessage' => 'hold_wrong_user_institution'),
            $result
        );

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->placeStorageRetrievalRequest(
            array(
                'patron' => $this->getUserObject('username', 'invalid'),
                'id' => 'invalid.1'
            )
        );
    }

    /**
     * Testing method for cancelStorageRetrievalRequests
     *
     * @return void
     */
    public function testCancelStorageRetrievalRequests()
    {
        $expected = array(
            '1' => array(
                'success' => true,
                'status' => 'storage_retrieval_request_cancel_success'
            ),
            '2' => array(
                'success' => false,
                'status' => 'storage_retrieval_request_cancel_fail'
            )

        );
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'cancelStorageRetrievalRequests',
            array(
                array(
                    'patron' => $this->getUserObject('username'),
                    'details' => array('1', '2')
                )
            ),
            $expected,
            $expected
        );

        $result = $driver->cancelStorageRetrievalRequests(
            array(
                'patron' => $this->getUserObject('username', 'd1'),
                'details' => array('1', '2')
            )
        );
        $this->assertEquals($expected, $result);

        $result = $driver->cancelStorageRetrievalRequests(
            array(
                'patron' => $this->getUserObject('username', 'd2'),
                'details' => array('1', '2')
            )
        );
        $this->assertEquals($expected, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->cancelStorageRetrievalRequests(
            array(
                'patron' => $this->getUserObject('username', 'invalid'),
                'details' => array('1', '2')
            )
        );
    }

    /**
     * Testing method for getCancelStorageRetrievalRequestDetails
     *
     * @return void
     */
    public function testGetCancelStorageRetrievalRequestDetails()
    {
        $expected = array('1', '2');
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getCancelStorageRetrievalRequestDetails',
            array(array('id' => '1','item_id' => '2')),
            $expected,
            $expected
        );

        $result = $driver->getCancelStorageRetrievalRequestDetails(
            array('id' => 'd1.1', 'item_id' => 2)
        );
        $this->assertEquals($expected, $result);

        $result = $driver->getCancelStorageRetrievalRequestDetails(
            array('id' => 'd2.1', 'item_id' => 2)
        );
        $this->assertEquals($expected, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getCancelStorageRetrievalRequestDetails(
            array('id' => 'invalid.1', 'item_id' => 2)
        );
    }

    /**
     * Testing method for checkILLRequestIsValid
     *
     * @return void
     */
    public function testCheckILLRequestIsValid()
    {
        $expected1 = true;
        $expected2 = false;
        $driver = $this->initSimpleMethodTest(
            $this->exactly(2),
            $this->once(),
            'checkILLRequestIsValid',
            array(
                'bibid',
                array('id' => 'itemid'),
                $this->logicalOr(
                    $this->getUserObject('username', 'd1'),
                    $this->getUserObject('username', 'd2')
                )
            ),
            true,
            false
        );

        $result = $driver->checkILLRequestIsValid(
            'd1.bibid',
            array('id' => 'd1.itemid'),
            $this->getUserObject('username', 'd1')
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->checkILLRequestIsValid(
            'd2.bibid',
            array('id' => 'd2.itemid'),
            $this->getUserObject('username', 'd2')
        );
        $this->assertEquals($expected2, $result);

        // Cross-driver request must be accepted
        $result = $driver->checkILLRequestIsValid(
            'd1.bibid',
            array('id' => 'd1.itemid'),
            $this->getUserObject('username', 'd2')
        );
        $this->assertEquals(true, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->checkILLRequestIsValid(
            'invalid.bibid',
            array('id' => 'invalid.itemid'),
            $this->getUserObject('username', 'invalid')
        );
        $this->assertEquals(false, $result);
    }

    /**
     * Testing method for getILLPickupLibraries
     *
     * @return void
     */
    public function testGetILLPickupLibraries()
    {
        $expected1 = array(array('locationID' => '1'));
        $expected2 = array(array('locationID' => '2'));
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getILLPickupLibraries',
            array(
                '1',
                $this->logicalOr(
                    $this->getUserObject('username', 'd1'),
                    $this->getUserObject('username', 'd2')
                )
            ),
            $expected1,
            $expected2
        );

        $result = $driver->getILLPickupLibraries(
            'd1.1',
            $this->getUserObject('username', 'd1')
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->getILLPickupLibraries(
            'd2.1',
            $this->getUserObject('username', 'd2')
        );
        $this->assertEquals($expected2, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getILLPickupLibraries(
            '1',
            $this->getUserObject('username', 'invalid')
        );
    }

    /**
     * Testing method for getILLPickupLocations
     *
     * @return void
     */
    public function testGetILLPickupLocations()
    {
        $expected1 = array(array('locationID' => '1'));
        $expected2 = array(array('locationID' => '2'));
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getILLPickupLocations',
            array(
                '1',
                '2',
                $this->logicalOr(
                    $this->getUserObject('username', 'd1'),
                    $this->getUserObject('username', 'd2')
                )
            ),
            $expected1,
            $expected2
        );

        $result = $driver->getILLPickupLocations(
            'd1.1',
            '2',
            $this->getUserObject('username', 'd1')
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->getILLPickupLocations(
            'd2.1',
            '2',
            $this->getUserObject('username', 'd2')
        );
        $this->assertEquals($expected2, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getILLPickupLocations(
            '1',
            '2',
            $this->getUserObject('username', 'invalid')
        );
    }

    /**
     * Testing method for placeILLRequest
     *
     * @return void
     */
    public function testPlaceILLRequest()
    {
        $expected1 = array(
            'success' => true,
            'status' => ''
        );
        $expected2 = array(
            'success' => false,
            'status' => 'ill_request_error_fail'
        );
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'placeILLRequest',
            array(
                $this->logicalOr(
                    array(
                        'patron' => $this->getUserObject('username', 'd1'),
                        'id' => 1
                    ),
                    array(
                        'patron' => $this->getUserObject('username', 'd2'),
                        'id' => 1
                    )
                )
            ),
            $expected1,
            $expected2
        );

        $result = $driver->placeILLRequest(
            array(
                'patron' => $this->getUserObject('username', 'd1'),
                'id' => 'd1.1'
            )
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->placeILLRequest(
            array(
                'patron' => $this->getUserObject('username', 'd2'),
                'id' => 'd2.1'
            )
        );
        $this->assertEquals($expected2, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->placeILLRequest(
            array(
                'patron' => $this->getUserObject('username', 'invalid'),
                'id' => 'invalid.1'
            )
        );
    }

    /**
     * Testing method for getMyILLRequests
     *
     * @return void
     */
    public function testGetMyILLRequests()
    {
        $expected1 = array(array('id' => 'd1.1'));
        $expected2 = array(array('id' => 'd2.1'));
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getMyILLRequests',
            array($this->getUserObject('username')),
            array(array('id' => '1')),
            array(array('id' => '1'))
        );

        $result = $driver->getMyILLRequests($this->getUserObject('username', 'd1'));
        $this->assertEquals($expected1, $result);

        $result = $driver->getMyILLRequests($this->getUserObject('username', 'd2'));
        $this->assertEquals($expected2, $result);

        // Try handling of unsupported request
        $result = $driver->getMyILLRequests($this->getUserObject('username', 'd3'));
        $this->assertEquals(array(), $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getMyILLRequests(
            $this->getUserObject('username', 'invalid')
        );
    }

    /**
     * Testing method for cancelILLRequests
     *
     * @return void
     */
    public function testCancelILLRequests()
    {
        $expected = array(
            '1' => array(
                'success' => true,
                'status' => 'ill_request_cancel_success'
            ),
            '2' => array(
                'success' => false,
                'status' => 'storage_retrieval_request_cancel_fail'
            )

        );
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'cancelILLRequests',
            array(
                array(
                    'patron' => $this->getUserObject('username'),
                    'details' => array('1', '2')
                )
            ),
            $expected,
            $expected
        );

        $result = $driver->cancelILLRequests(
            array(
                'patron' => $this->getUserObject('username', 'd1'),
                'details' => array('1', '2')
            )
        );
        $this->assertEquals($expected, $result);

        $result = $driver->cancelILLRequests(
            array(
                'patron' => $this->getUserObject('username', 'd2'),
                'details' => array('1', '2')
            )
        );
        $this->assertEquals($expected, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->cancelILLRequests(
            array(
                'patron' => $this->getUserObject('username', 'invalid'),
                'details' => array('1', '2')
            )
        );
    }

    /**
     * Testing method for getCancelILLRequestDetails
     *
     * @return void
     */
    public function testGetCancelILLRequestDetails()
    {
        $expected = array('1', '2');
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getCancelILLRequestDetails',
            array(array('id' => '1','item_id' => '2')),
            $expected,
            $expected
        );

        $result = $driver->getCancelILLRequestDetails(
            array('id' => 'd1.1', 'item_id' => 2)
        );
        $this->assertEquals($expected, $result);

        $result = $driver->getCancelILLRequestDetails(
            array('id' => 'd2.1', 'item_id' => 2)
        );
        $this->assertEquals($expected, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getCancelILLRequestDetails(
            array('id' => 'invalid.1', 'item_id' => 2)
        );
    }

    /**
     * Testing method for getConfig
     *
     * @return void
     */
    public function testGetConfig()
    {
        $expected1 = array('config' => 'ok1');
        $expected2 = array('config' => 'ok2');
        $driver = $this->initSimpleMethodTest(
            $this->exactly(2),
            $this->once(),
            'getConfig',
            array(
                $this->logicalOr(
                    'Holds',
                    array('Holds', '123456')
                )
            ),
            $expected1,
            $expected2
        );

        $result = $driver->getConfig('Holds', 'd1.123456');
        $this->assertEquals($expected1, $result);

        $return = $driver->getConfig('Holds', 'fail.123456');
        $this->assertEquals(array(), $return);

        $this->setProperty($driver, 'defaultDriver', 'd2');
        $return = $driver->getConfig('Holds', '123456');
        $this->assertEquals($expected2, $return);

        $mockAuth = $this->getMockILSAuthenticator('d1');
        $this->setProperty($driver, 'ilsAuth', $mockAuth);
        $result = $driver->getConfig('Holds');
        $this->assertEquals($expected1, $result);

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

        //Case: getLoginDrivers and getDefaultLoginDriver are always supported
            //Result: A return of true

        $methodReturn = $driver->supportsMethod('getLoginDrivers', null);
        $this->assertTrue($methodReturn);
        $methodReturn = $driver->supportsMethod('getDefaultLoginDriver', null);
        $this->assertTrue($methodReturn);
    }

    /**
     * Initialize a MultiBackend driver for a simple method test with three drivers:
     * Voyager, Demo and Dummy that doesn't handle anything
     *
     * @param object $times1   The number of times first driver is expected to be
     * called
     * @param object $times2   The number of times second driver is expected to be
     * called
     * @param string $function Function name
     * @param array  $params   Function parameters
     * @param mixed  $return1  What the function should return with first driver
     * @param mixed  $return2  What the function should return with second driver
     *
     * @return object MultiBackend driver
     */
    protected function initSimpleMethodTest(
        $times1, $times2, $function, $params, $return1, $return2
    ) {
        $driver = $this->getDriver();
        $drivers = array('d1' => 'Voyager', 'd2' => 'Demo', 'd3' => 'DummyILS');
        $this->setProperty($driver, 'drivers', $drivers);

        $voyager = $this->getMockILS('Voyager', array('init', $function));
        call_user_func_array(
            array($voyager->expects($times1)->method($function), 'with'), $params
        )->will($this->returnValue($return1));

        $demo = $this->getMockILS('Demo', array('init', $function));
        call_user_func_array(
            array($demo->expects($times2)->method($function), 'with'), $params
        )->will($this->returnValue($return2));

        $dummyILS = new DummyILS();

        $sm = $this->getMockForAbstractClass(
            'Zend\ServiceManager\ServiceLocatorInterface'
        );
        $sm->expects($this->any())
            ->method('get')
            ->with($this->logicalOr('Voyager', 'Demo', 'DummyILS'))
            ->will(
                $this->returnCallback(
                    function ($param) use ($voyager, $demo, $dummyILS) {
                        if ($param == 'Voyager') {
                            return $voyager;
                        } else if ($param == 'Demo') {
                            return $demo;
                        } else if ($param == 'DummyILS') {
                            return $dummyILS;
                        }
                        return null;
                    }
                )
            );
        $driver->setServiceLocator($sm);

        return $driver;
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
        $driver->setConfig(
            array(
                'Drivers' => array(),
                'Login' => array(
                    'drivers' => array('d1', 'd2'),
                    'default_driver' => 'd1'
                )
            )
        );
        $driver->init();
        return $driver;
    }

    /**
     * Get a mock ILS authenticator
     *
     * @param bool $userSource Source id, if the authenticator should emulate a
     * situation where a user has logged in
     *
     * @return \VuFind\Auth\ILSAuthenticator
     */
    protected function getMockILSAuthenticator($userSource = '')
    {
        $mockAuth = $this->getMockBuilder('VuFind\Auth\ILSAuthenticator')
            ->disableOriginalConstructor()
            ->getMock();
        if ($userSource) {
            $mockAuth->expects($this->any())
                ->method('storedCatalogLogin')
                ->will(
                    $this->returnValue($this->getUserObject('username', $userSource))
                );
        }
        return $mockAuth;
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
        $cat_username = $instance ? $instance . '.' . $username : $username;
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
            if ($type == 'Demo') {
                $mock = $this->getMock(
                    "VuFind\ILS\Driver\\$type", $methods,
                    array(
                        new \VuFind\Date\Converter(),
                        $this->getMock('VuFindSearch\Service')
                    )
                );
            } else {
                $mock = $this->getMock(
                    "VuFind\ILS\Driver\\$type", $methods,
                    array(new \VuFind\Date\Converter())
                );
            }
        } catch(\Exception $e) {
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

/**
 * A dummy ILS driver used for testing a driver with unsupported features
 *
 * @category VuFind2
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class DummyILS extends \VuFind\ILS\Driver\AbstractBase
{
    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @return void
     */
    public function init()
    {
        return array();
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @throws \VuFind\Exception\ILS
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        return array();
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @throws \VuFind\Exception\ILS
     * @return array     An array of getStatus() return values on success.
     */
    public function getStatuses($ids)
    {
        return array();
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @throws \VuFind\Exception\ILS
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, array $patron = null)
    {
        return array();
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @throws \VuFind\Exception\ILS
     * @return array     An array with the acquisitions data on success.
     */
    public function getPurchaseHistory($id)
    {
        return array();
    }
}
