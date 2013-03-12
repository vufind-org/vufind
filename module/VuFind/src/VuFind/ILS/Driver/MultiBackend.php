<?php
/**
 * Multiple Backend Driver.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2012.
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
 * @category VuFind
 * @package  ILSdrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
namespace VuFind\ILS\Driver;

use VuFind\Exception\ILS as ILSException,
    Zend\ServiceManager\ServiceLocatorAwareInterface,
    Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Multiple Backend Driver.
 *
 * This driver allows to use multiple backends determined by a record id or
 * user id prefix (e.g. source.12345).
 *
 * @category VuFind
 * @package  ILSdrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
class MultiBackend extends AbstractBase implements ServiceLocatorAwareInterface
{
    /**
     * The serviceLocator instance (implementing ServiceLocatorAwareInterface).
     *
     * @var object
     */
    protected $serviceLocator;

    /**
     * The array of configured driver names.
     *
     * @var string[]
     */
    protected $drivers = array();

    /**
     * The default driver to use
     *
     * @var string
     */
    protected $defaultDriver;

    /**
     * The array of cached pre-instantiated drivers
     *
     * @var object[]
     */
     protected $cache = array();

    /**
     * The array of booleans letting us know if a
     * driver in the cache has been initialized.
     *
     * @var boolean[]
     */
     protected $isInitialized = array();

    /**
     * The array of driver configuration options.
     *
     * @var string[]
     */
    protected $config = array();

    /**
     * The seperating values to be used for each ILS.
     * Not yet implemented
     * @var object
     */
    protected $delimiters = array();

    /**
     * Configuration loader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configLoader;

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Configuration loader
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        $this->configLoader = $configLoader;
    }

    /**
     * Set the driver configuration.
     *
     * @param Config $config The configuration to be set
     *
     * @return void
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
    public function init()
    {
        if (empty($this->config)) {
            throw new ILSException('Configuration needs to be set.');
        }
        $this->drivers = $this->config['Drivers'];
        $this->defaultDriver = isset($this->config['General']['default_driver'])?
            $this->config['General']['default_driver']:
            null;
        $this->delimiters['login']
            = (isset($this->config['Delimiters']['login']) ?
                   $this->config['Delimiters']['login'] :
                   "\t");
        $this->getDriverConfig($this->defaultDriver);
    }


    /**
     * Set the service locator.
     *
     * @param ServiceLocatorInterface $serviceLocator Locator to register
     *
     * @return Manager
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }

    /**
     * Get the service locator.
     *
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }

    /**
     * Extract local ID from the given prefixed ID
     *
     * @param string $id        The id to be split
     * @param string $delimiter The delimiter to be used
     *
     * @return string  Local ID
     */
    protected function getLocalId($id, $delimiter = '.')
    {
        $pos = strrpos($id, $delimiter);
        if ($pos > 0) {
            return substr($id, $pos + 1);
        }
        //error_log("MultiBackend: Can't find local id in '$id'");
        return $id;
    }

    /**
     * Extract source from the given ID
     *
     * @param string $id        The id to be split
     * @param string $delimiter The delimiter to be used
     *
     * @return string  Source
     */
    protected function getSource($id, $delimiter = '.')
    {
        $pos = strrpos($id, $delimiter);
        if ($pos > 0) {
            return substr($id, 0, $pos);
        }
        //error_log(
        //    "MultiBackend: Can't find source id in '$id' using '$delimiter'"
        //);
        return $id;
    }

    /**
     * Find the correct driver for the correct configuration file for the
     * given source and cache an initialized copy of it.
     *
     * @param string $source The source name of the driver to get.
     *
     * @return mixed  On success a driver object, otherwise null.
     */
    protected function getDriver($source)
    {
        if (!isset($this->isInitialized[$source])
            || !$this->isInitialized[$source]
        ) {
            $driverInst = null;

            // And we don't have a copy in our cache...
            if (!isset($this->cache[$source])) {
                // Get an uninitialized copy
                $driverInst = $this->getUninitializedDriver($source);
            } else {
                // Otherwise, use the uninitialized cached copy
                $driverInst = $this->cache[$source];
            }

            // If we have a driver, initialize it.  That version has already
            // been cached.
            if ($driverInst) {
                $this->initializeDriver($driverInst, $source);
            } else {
                return null;
            }
        }
        return $this->cache[$source];
    }

    /**
     * Find the correct driver for the correct configuration file
     * for the given source.  For performance reasons, we do not
     * want to initialize the driver yet if it hasn't been already.
     *
     * @param string $source the source title for the driver.
     *
     * @return mixed On success an unintiialized driver object, otherwise null.
     */
    protected function getUninitializedDriver($source)
    {
        // We don't really care if it's initialized here.  If it is, then there's
        // still no added overhead of returning an initialized driver.
        if (isset($this->cache[$source])) {
            return $this->cache[$source];
        }

        if (isset($this->drivers[$source])) {
            $driver = $this->drivers[$source];
            $config = $this->getDriverConfig($source);
            try
            {
                $driverInst = $this->getServiceLocator()->get($driver);
                $driverInst->setConfig($config);
                $this->cache[$source] = $driverInst;
                $this->isInitialized[$source] = false;
                return $driverInst;
            } catch (Exception $e) {
                $msg = "MultiBackend: error initializing driver '$driver': ";
                $msg = $msg . $e->__toString();
                //error_log($msg);
                return null;
            }
        } else {
            //error_log("$source is not in drivers[]");
        }
        return null;
    }

    /**
     * Initialize an uninitialized driver.
     *
     * @param object $driver The driver object to be initialized
     * @param string $source The source related to the driver for caching purposes.
     *
     * @return no returns, getting an error without this comment though.
     */
    protected function initializeDriver($driver, $source)
    {
        if (!isset($this->isInitialized[$source])
            || !$this->isInitialized[$source]
        ) {
            try
            {
                $driver->init();
                $this->isInitialized[$source] = true;
                $this->cache[$source] = $driver;
            } catch (Exception $e) {
                $msg = "MultiBackend: error initializing driver '$driver': ";
                $msg = $msg . $e->__toString();
                //error_log($msg);
            }
        }
    }

    /**
     * Get configuration for the ILS driver.  We will load an .ini file named
     * after the driver class and number if it exists;
     * otherwise we will return an empty array.
     *
     * @param string $source The source id to use for determining the
     * configuration file
     *
     * @return array   The configuration of the driver
     */
    protected function getDriverConfig($source)
    {
        // Determine config file name based on class name:
        try {
            $config = $this->configLoader->get($source);
        } catch (\Zend\Config\Exception\RuntimeException $e) {
            // Configuration loading failed; probably means file does not
            // exist -- just return an empty array in that case:
            return array();
        }
        return $config->toArray();
    }

    /**
     * Change local ID's to global ID's in the given array
     *
     * @param mixed  $data         The data to be modified, normally
     * array or array of arrays
     * @param string $source       Source code
     * @param array  $modifyFields Fields to be modified in the array
     *
     * @return mixed     Modified array or empty/null if that input was
     *                   empty/null
     */
    protected function addIdPrefixes($data, $source,
        $modifyFields = array('id', 'cat_username')
    ) {

        if (!isset($data) || empty($data) ) {
            return $data;
        }
        $array = is_array($data) ? $data : array($data);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->addIdPrefixes(
                    $value, $source, $modifyFields
                );
            } else {
                if (in_array($key, $modifyFields)) {
                    $array[$key] = $source . '.' . $value;
                }
            }
        }
        return is_array($data) ? $array : $array[0];
    }

    /**
    * Change global ID's to local ID's in the given array
    *
    * @param mixed  $data         The data to be modified, normally
    * array or array of arrays
    * @param string $source       Source code
    * @param array  $modifyFields Fields to be modified in the array
    *
    * @return mixed     Modified array or empty/null if that input was
    *                   empty/null
    */
    protected function stripIdPrefixes($data, $source,
        $modifyFields = array('id', 'cat_username')
    ) {

        if (!isset($data) || empty($data)) {
            return $data;
        }
        $array = is_array($data) ? $data : array($data);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->stripIdPrefixes(
                    $value, $source, $modifyFields
                );
            } else {
                if (in_array($key, $modifyFields)
                    && strncmp($source . '.', $value, strlen($source) + 1) == 0
                ) {
                        $array[$key] = substr($value, strlen($source) + 1);
                }
            }
        }
        return is_array($data) ? $array : $array[0];
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber; on
     * failure, a PEAR_Error.
     * @access public
     */
    public function getStatus($id)
    {
        return $this->getHolding($id);
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @return mixed     An array of getStatus() return values on success,
     * a PEAR_Error object otherwise.
     * @access public
     */
    public function getStatuses($ids)
    {
        $items = array();
        foreach ($ids as $id) {
            $items[] = $this->getHolding($id);
        }
        return $items;
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
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber, duedate,
     * number, barcode; on failure, a PEAR_Error.
     * @access public
     */
    public function getHolding($id, $patron = false)
    {
        $source = $this->getSource($id);
        $driver = $this->getDriver($source);
        if ($driver) {
            $holdings = $driver->getHolding($this->getLocalId($id), $patron);
            if ($holdings) {
                return $this->addIdPrefixes($holdings, $source);
            }
        } else {
            //error_log("No driver for '$id' found-");
        }
        return Array();
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @return mixed     An array with the acquisitions data on success, PEAR_Error
     * on failure
     * @access public
     */
    public function getPurchaseHistory($id)
    {
        $source = $this->getSource($id);
        $driver = $this->getDriver($source);
        if ($driver) {
            return $driver->getPurchaseHistory($this->getLocalId($id));
        }
        return null;
    }

    /**
     * patronLogin function.  This function handles patron logins in a multiple
     * backend environment by looping through all available driver configurations
     * until it finds one that allows the login, then returns the login information
     * supplied by that ILS.
     *
     * @param string $username The username to log in with
     * @param string $password The password to log in with
     *
     * @return mixed   Associative array of patron info on successful login,
     *                 null on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {

        $pos = strrpos($username, $this->delimiters['login']);
        $login = null;
        if ($pos > 0) {
            $key = $this->getSource($username, $this->delimiters['login']);
            $user = $this->getLocalID($username, $this->delimiters['login']);
            $login = $this->getDriver($key)->patronLogin($user, $password);
            return $login;
        }
        foreach ($this->drivers as $key => $driver) {
            $login =  $this->getDriver($key)->patronLogin($username, $password);
            if ($login) {
                $login['cat_username']
                    = $key.$this->delimiters['login'].$login['cat_username'];
                return $login;
            }
        }
        return null;
    }

    /**
     * Function developed to reduce code duplication in supportsMethod() and __call()
     *
     * @param array $params Array of passed parameters
     *
     * @return mixed Finds the driver instance associated with a pre-indexed catalog.
     *               Null if cat_username is not pre-indexed with the catalog name.
     */
    protected function getInstanceFromParams($params)
    {
        if (isset($params[0]["cat_username"])) {
            $instName = $this->getSource(
                $params[0]["cat_username"],
                $this->delimiters['login']
            );
            if (strlen($params[0]["cat_username"])>strlen($instName)) {
                return $instName;
            }
        }
        return null;
    }


    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver.  Required method for any smart drivers.
     *
     * @param string $method The name of the called method.
     * @param array  $params Array of passed parameters
     *
     * @return boolean  True if the method can be called with the given
     *                  parameters, false otherwise.
     */
    public function supportsMethod($method, $params)
    {
        // First we see if we can determine what instance the user is connected with
        $instance = $this->getInstanceFromParams($params);
        if ($instance) {
            $driverInst = $this->getUninitializedDriver($instance);
            return  is_callable(array($driverInst, $method));
        }

        // Falling back, we try to use a default driver if it's set
        $instance = $this->defaultDriver;
        if ($instance) {
            $driverInst = $this->getUninitializedDriver($instance);
            return is_callable(array($driverInst, $method));
        }

        // Lastly, we see if any of the drivers we have support the function
        foreach ($this->drivers as $key => $driver) {
            $driverInst = $this->getUninitializedDriver($key);
            if (is_callable(array($driverInst, $method))) {
                  return true;
            }
        }

        return false;
    }

    /**
     * This method runs a given method on a given driver instance with the given
     * params.
     *
     * @param string    $instName   The name of the driver instance to use.
     * @param string    $methodName The name of the method to be called
     * @param array     $params     Array of passed parameters
     * @param reference &$called    A reference to a passed in boolean to determine
     *                              if the function was actually called and returned
     *                              false, or if it just didn't run.
     *
     * @return boolean  False if the method could not be run, and the return
     *                  of the method if it could be.
     */
    protected function runIfPossible($instName, $methodName, $params, &$called)
    {
        if ($instName) {
            $driverInst = $this->getUninitializedDriver($instName);
            if (is_callable(array($driverInst, $methodName))) {
                $this->initializeDriver($driverInst, $instName);
                $funcReturn =  call_user_func_array(
                    array($driverInst, $methodName), $params
                );

                // Because things like getMyFines return false if you have no fines,
                // we need a different way of knowing if the function was called.
                $called = true;
                return $funcReturn;
            }
        }
        $called = false;
        return $called;
    }

    /**
     * Determine what behavior a method should have if we are unable to determine
     * a specific ILS to associate with it.
     *
     * @param string $methodName The name of the method to be called
     *
     * @return string  The behavior to be used.
     */
    protected function getMethodBehavior($methodName)
    {
        $var = 'default_fallback_driver_selection';
        $default = isset($this->config['General'][$var])?
                        $this->config['General'][$var] :
                        "use_first";
        $section = 'FallbackDriverSelectionOverride';
        return isset($this->config[$section][$methodName])?
                        $this->config[$section][$methodName] :
                        $default;
    }


    /**
     * A method to run a given method if we are unable to determine an ILS driver to
     * associate with the method call.
     *
     * @param string    $methodName The name of the method to be called
     * @param array     $params     Array of passed parameters
     * @param reference &$called    A reference to a passed in boolean to determine
     *                              if the function was actually called and returned
     *                              false, or if it just didn't run.
     *
     * @return boolean  False if the method could not be run, and the return
     *                  of the method if it could be.
     */
    protected function runMethodNoILS($methodName, $params, &$called)
    {
        $behavior = $this->getMethodBehavior($methodName);
        $funcWasCalled = false;
        $returnArray = array();
        // Here we loop through evry instance we have access to and change what
        // we do based off of the configuration behavior.
        foreach ($this->drivers as $key => $driver) {
            $funcReturn = $this->runIfPossible($key, $methodName, $params, $called);
            if ($called) {
                if ($behavior == "use_first" || !is_array($funcReturn)) {
                    return $funcReturn;
                } else if ($behavior == "merge") {
                    $funcWasCalled = true;
                    $returnArray = array_merge($returnArray, $funcReturn);
                }
            }
            $called = false;
        }

        //We only return something if we were able to call this method on an ILS,
        //Otherwise it should be handled like an uncallable function below.
        if ($funcWasCalled) {
            $called = true;
            return $returnArray;
        }
        return false;
    }
    /**
     * Default method -- pass along calls to the driver if available; return
     * false otherwise.  This allows custom functions to be implemented in
     * the driver without constant modification to the MultiBackend class.
     *
     * @param string $methodName The name of the called method.
     * @param array  $params     Array of passed parameters.
     *
     * @return mixed             Varies by method (false if undefined method)
     */
    public function __call($methodName, $params)
    {
        $called = false;
        //Try for the driver associated with the user
        $instName = $this->getInstanceFromParams($params);
        $funcReturn = $this->runIfPossible($instName, $methodName, $params, $called);
        if ($called) {
            return $funcReturn;
        }

        //Get the driver associated with the current instance
        $instName = $this->defaultDriver;
        $funcReturn = $this->runIfPossible($instName, $methodName, $params, $called);
        if ($called) {
            return $funcReturn;
        }

        $funcReturn = $this->runMethodNoILS($methodName, $params, $called);
        if ($called) {
            return $funcReturn;
        }
        throw new ILSException('Cannot call method: ' . $methodName);
    }
}

