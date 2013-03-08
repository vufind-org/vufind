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
        $test = new MultiBackend(new \VuFind\Config\PluginManager());
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
     * Test that MultiBackend can pull instance information from parameters.
     *
     * @return void
     */
    public function testGetInstanceFromParams()
    {
        //Case: Can't find the delimiter
            //Result: Null
        $driver = $this->getDriver();
        $patronParam = array(array(
            $this->getUserObject('username', 'institution')
        ));
        $delimiters = array('login' => "thiswillnotbefound");
        $this->setProperty($driver, 'delimiters', $delimiters);
        $instance = $this->callMethod(
            $driver,
            'getInstanceFromParams',
            $patronParam
        );
        $this->assertNull($instance);

        //Case: Can find the delimiter
            //Result: Return part before the delimiter
        $delimiters['login'] = "\t";
        $this->setProperty($driver, 'delimiters', $delimiters);
        $instance = $this->callMethod(
            $driver,
            'getInstanceFromParams',
            $patronParam
        );
        $this->assertSame('institution', $instance);
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
        $ILS->expects($this->once())
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
        $patron = $driver->patronLogin('username', 'password');
 
        //Check that it added username info properly.
        $this->assertSame(
            $instance."\t".$patronReturn['cat_username'],
            $patron['cat_username']
        );           

        
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
        $this->assertSame($ILS, $unInitDriver);

        //Check the cache arrays to make sure they get set correctly
        $isInit = $this->getProperty($driver, 'isInitialized');
        $cache = $this->getproperty($driver, 'cache');
        $this->assertSame($ILS, $cache['testing']);
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
        $ILS->expects($this->once())
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
        $this->assertSame($ILS, $returnDriver);
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
        $ILS = $this->getMockILS('Voyager', array('setConfig'));
        $ILS->expects($this->once())
            ->method('setConfig')
            ->with(array('config' => 'values'));

        //Set up the ServiceLocator so it returns our mock driver
        $sm = $this->getMockSM($this->once(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);


        //Add an entry for our test driver to the array of drivers
        $drivers = array('testing3' => 'Voyager');
        $this->setProperty($driver, 'drivers', $drivers);

        //Case: The method doesn't exists in any of the drivers
            //Result: A return of false
        $methodReturn = $this->callMethod(
            $driver,
            'supportsMethod',
            array('fail',null)
        );
        $this->assertFalse($methodReturn);

        //Case: The method exists in at least 1 of the drivers
            //Result: A return of true
        $methodReturn = $this->callMethod(
            $driver,
            'supportsMethod',
            array('getStatus',null)
        );
        $this->assertTrue($methodReturn);
    }

    /**
     * Test that MultiBackend can properly tell whether or not
     * a driver is has contains a specified method.
     *
     * @return void
     */
    public function testRunIfPossible()
    {
        $driver = $this->getDriver();
        //Set up the mock driver to be retrieved
        $ILS = $this->getMockILS('Voyager', array('init', 'getStatus'));
        $ILS->expects($this->once())
            ->method('getStatus')
            ->with('custID')
            ->will($this->returnValue('worked'));

        //Set up the ServiceLocator so it returns our mock driver
        $sm = $this->getMockSM($this->once(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);

        //Add an entry for our test driver to the array of drivers
        $drivers = array('testing4' => 'Voyager');
        $this->setProperty($driver, 'drivers', $drivers);

        //Prepare variables
        $called = false;
        $params = array('testing4', //$instName
                        'getStatus', //$methodName
                        array('custID'), //$params
                        &$called);  //&$called
        //Case: Method should be called
            //Result: $called is true, we return the called functions return

        $returnVal = $this->callMethod($driver, 'runIfPossible', $params);
        $this->assertTrue($called);
        $this->assertSame('worked', $returnVal);

        //Case: Method does not exist
            //Result: $called is false, returns false
        
        $called = false;
        $params[1] = 'fakeMethod';
        $returnVal = $this->callMethod($driver, 'runIfPossible', $params);
        $this->assertFalse($called);
        $this->assertFalse($returnVal);

        //Case: No instance is given to run the method on
            //Result: Same as previous case

        $params[0] = null;
        $params[1] = 'getStatus';
        $returnVal = $this->callMethod($driver, 'runIfPossible', $params);
        $this->assertFalse($called);
        $this->assertFalse($returnVal);     
    }

    /**
     * Test that MultiBackend can properly tell what functionality a
     * method should use for their return values.
     *
     * @return void
     */
    public function testGetMethodBehavior()
    {
        //Case: There is no configured behavior
            //Result: We use 'use_first', hardcoded into MultiBackend.
        $driver = $this->getDriver();
        $returnVal = $this->callMethod(
            $driver,
            'getMethodBehavior',
            array('method')
        );
        $this->assertSame('use_first', $returnVal);

        //Case: The default is overridden by the config
            //Result:  We use the value for the default selection method.
        $var = 'default_fallback_driver_selection';
        $config = array(
            'General'=> array(
                $var => 'usingThis'),
            );
        $this->setProperty($driver, 'config', $config);
        $returnVal = $this->callMethod(
            $driver,
            'getMethodBehavior',
            array('method')
        );
        $this->assertSame('usingThis', $returnVal);

        //Case: A specific function is overridden in the config
            //Result:  That fuction will use that specific functionality
        $section = 'FallbackDriverSelectionOverride';
        $config[$section] = array('method' => 'overridden');
        $this->setProperty($driver, 'config', $config);
        $returnVal = $this->callMethod(
            $driver,
            'getMethodBehavior',
            array('method')
        );
        $this->assertSame('overridden', $returnVal);
    }

    /**
     * Test that MultiBackend can find and use the correct ILS driver if it is given
     * a method and parameters, but no direction as towards what driver to use.
     *
     * @return void
     */
    public function testRunMethodNoILS()
    {
        $driver = $this->getDriver();
        $config = array(
            'General'=> array(
                'default_fallback_driver_selection' => 'use_first'),

            'FallbackDriverSelectionOverride' =>array(
                'getStatuses' => 'merge')
            );
        $this->setProperty($driver, 'config', $config);

        //Case: Nonexistent method
            //Result: return false, $called false

        //Set up the mock driver to be retrieved
        $ILS = $this->getMockILS('Voyager', array('getStatus', 'init'));
        $ILS->expects($this->once())
            ->method('getStatus')
            ->with('custID')
            ->will($this->returnValue('worked'));

        //Set up the ServiceLocator so it returns our mock driver
        $sm = $this->getMockSM($this->any(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);

        //Add an entry for our test driver to the array of drivers
        $drivers = array('testing5' => 'Voyager');
        $this->setProperty($driver, 'drivers', $drivers);
        $called = false;
        $params = array('fake method', array('custID'), &$called);
        $returnVal = $this->callMethod($driver, 'runMethodNoILS', $params);
        $this->assertFalse($called);
        $this->assertFalse($returnVal);

        //Case: Method use_first/not an array
            //Result: return method data, $called true

        $params[0] = 'getStatus';
        $returnVal = $this->callMethod($driver, 'runMethodNoILS', $params);
        $this->assertTrue($called);
        $this->assertSame('worked', $returnVal);

        //Case: Method merge, need a second ILS to test
            //Result: return combined data in an array, $called true
        $ILS = $this->getMockILS('Voyager', array('getStatuses', 'init'));
        $ILS->expects($this->once())
            ->method('getStatuses')
            ->with('custID')
            ->will($this->returnValue(array('worked1', 'worked2'))); 

        $ILS2 = $this->getMockILS('Voyager', array('getStatuses', 'init'));
        $ILS2->expects($this->once())
            ->method('getStatuses')
            ->with('custID')
            ->will($this->returnValue(array('worked3', 'worked4')));   
        // We have to do it this way because we're not actualy setting different
        // configurations.  Can't use out method because we're doing tricky stuff
        // with PHPunit
        $sm = $this->getMockForAbstractClass(
            'Zend\ServiceManager\ServiceLocatorInterface'
        );
        $sm->expects($this->exactly(2))
            ->method('get')
            ->with('Voyager')
            ->will($this->onConsecutiveCalls($ILS, $ILS2));
        $driver->setServiceLocator($sm);

        $drivers = array('testing6' => 'Voyager', 'testing7' => 'Voyager');
        $this->setProperty($driver, 'drivers', $drivers);

        $params[0] = 'getStatuses';
        $called = false;
        $returnVal = $this->callMethod($driver, 'runMethodNoILS', $params);
        $this->assertTrue($called);
        $shouldReturn = array('worked1', 'worked2', 'worked3', 'worked4');      
        $this->assertSame($shouldReturn, $returnVal);  
    }

    /**
     * Test that MultiBackend can find and use the correct ILS driver given a call
     * to a function that it does not know about
     *
     * @return void
     */
    public function testCall()
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
        

        $ILS = $this->getMockILS('Voyager', array('getMyTransactions', 'init'));
        $ILS->expects($this->atLeastOnce())
            ->method('getMyTransactions')
            ->with($patron)
            ->will($this->returnValue(true));

        $sm = $this->getMockSM($this->any(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);
        //Run the method invoking the __call method on our $user object
        //which has the instance set to 'institution'
        $returnVal = $driver->getMyTransactions($patron);
        $this->assertTrue($returnVal);


        //Case: There is a default driver set in the configuration
            //Result: return the function results for that driver

        // We need to clear patron login information so __call has to fall back on
        // the defaultDriver implementation
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



        //Case: No idea what ILS to use
            //Result: the result of runMethodNoILS
        $config = array(
            'General'=> array(
                'default_fallback_driver_selection' => 'use_first')
            );

        // Need to clear the default driver.  We already cleared patron 
        // information in the last set of asserts
        // Koha has "getHoldLink" and Horizon does not, we can use this to test
        // to make sure that it won't call the function on a driver that
        // does not have that method.
        $drivers = array(
            'inst1' => 'Horizon',
            'inst2' => 'Koha'
        );
        $this->setProperty($driver, 'drivers', $drivers);
        $this->setProperty($driver, 'defaultDriver', null);
        $this->setProperty($driver, 'config', $config);

        // It'll use the first driver it hits, so we want to prep
        // our mocks to use that one. Have to do a manual SM setup
        // for this one.
        $ILS = $this->getMockILS('Koha', array('getHoldLink', 'init'));
        $ILS->expects($this->once())
            ->method('getHoldLink')
            ->with('id', 'details')
            ->will($this->returnValue(true));
        $ILS2 = $this->getMockILS('Horizon');
        $ILS2->expects($this->never())
            ->method('getHoldLink');
        $sm = $this->getMockForAbstractClass(
            'Zend\ServiceManager\ServiceLocatorInterface'
        );
        $sm->expects($this->at(0))
            ->method('get')
            ->with('Horizon')
            ->will($this->returnValue($ILS2));
        $sm->expects($this->at(1))
            ->method('get')
            ->with('Koha')
            ->will($this->returnValue($ILS));
        $sm->expects($this->exactly(2))
            ->method('get');
        $driver->setServiceLocator($sm);

        $returnVal = $driver->getHoldLink('id', 'details');
        $this->assertTrue($returnVal);
        

        //Case: Nothing to do
            //Result: new ILSException

        $this->setExpectedException('VuFind\Exception\ILS');
        $returnVal = $driver->ThisIsNotAMethodOfAnyDriver($patron);
    }

    /**
     * Method to get a fresh MultiBackend Driver.
     *
     * @return mixed A MultiBackend instance.
     */
    protected function getDriver()
    {
        $driver = new MultiBackend($this->getPluginManager());
        $driver->setConfig(array('Drivers' => array()));
        $driver->init();
        return $driver;
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


