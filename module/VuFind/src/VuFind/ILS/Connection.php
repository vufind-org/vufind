<?php
/**
 * Catalog Connection Class
 *
 * This wrapper works with a driver class to pass information from the ILS to
 * VuFind.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace VuFind\ILS;
use VuFind\Exception\ILS as ILSException,
    VuFind\ILS\Driver\DriverInterface,
    VuFind\I18n\Translator\TranslatorAwareInterface;


/**
 * Catalog Connection Class
 *
 * This wrapper works with a driver class to pass information from the ILS to
 * VuFind.
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class Connection implements TranslatorAwareInterface
{
    /**
     * Translator (or null if unavailable)
     *
     * @var \Zend\I18n\Translator\Translator
     */
    protected $translator = null;

    /**
     * Has the driver been initialized yet?
     *
     * @var bool
     */
    protected $driverInitialized = false;

    /**
     * The object of the appropriate driver.
     *
     * @var object
     */
    protected $driver;

    /**
     * ILS configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Holds mode
     *
     * @var string
     */
    protected $holdsMode = 'disabled';

    /**
     * Title-level holds mode
     *
     * @var string
     */
    protected $titleHoldsMode = 'disabled';

    /**
     * Configuration loader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configReader;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config              $config        Configuration
     * representing the [Catalog] section of config.ini
     * @param \VuFind\ILS\Driver\PluginManager $driverManager Driver plugin manager
     * @param \VuFind\Config\PluginManager     $configReader  Configuration loader
     */
    public function __construct(\Zend\Config\Config $config,
        \VuFind\ILS\Driver\PluginManager $driverManager,
        \VuFind\Config\PluginManager $configReader
    ) {
        $this->config = $config;
        $this->configReader = $configReader;
        if (!isset($this->config->driver)) {
            throw new \Exception('ILS driver setting missing.');
        }
        $service = $this->config->driver;
        if (!$driverManager->has($service)) {
            throw new \Exception('ILS driver missing: ' . $service);
        }
        $this->setDriver($driverManager->get($service));

        // If we're configured to fail over to the NoILS driver, we need
        // to test if the main driver is working.
        if (isset($this->config->loadNoILSOnFailure)
            && $this->config->loadNoILSOnFailure
        ) {
            try {
                $this->getDriver();
            } catch (\Exception $e) {
                $this->setDriver($driverManager->get('NoILS'));
            }
        }
    }

    /**
     * Set a translator
     *
     * @param \Zend\I18n\Translator\Translator $translator Translator
     *
     * @return Connection
     */
    public function setTranslator(\Zend\I18n\Translator\Translator $translator)
    {
        $this->translator = $translator;
        return $this;
    }

    /**
     * Set the hold configuration for the connection.
     *
     * @param \VuFind\ILS\HoldSettings $settings Hold settings
     *
     * @return Connection
     */
    public function setHoldConfig($settings)
    {
        $this->holdsMode = $settings->getHoldsMode();
        $this->titleHoldsMode = $settings->getTitleHoldsMode();
        return $this;
    }

    /**
     * Get class name of the driver object.
     *
     * @return string
     */
    public function getDriverClass()
    {
        return get_class($this->driver);
    }

    /**
     * Get access to the driver object.
     *
     * @throws \Exception
     * @return object
     */
    public function getDriver()
    {
        if (!$this->driverInitialized) {
            if (!is_object($this->driver)) {
                throw new \Exception('ILS driver missing.');
            }
            $this->driver->setConfig($this->getDriverConfig());
            $this->driver->init();
            $this->driverInitialized = true;
        }
        return $this->driver;
    }

    /**
     * Set a driver object.
     *
     * @param DriverInterface $driver      Driver to set.
     * @param bool            $initialized Is this driver already initialized?
     *
     * @return void
     */
    public function setDriver(DriverInterface $driver, $initialized = false)
    {
        $this->driverInitialized = $initialized;
        $this->driver = $driver;
    }

    /**
     * Get configuration for the ILS driver.  We will load an .ini file named
     * after the driver class if it exists; otherwise we will return an empty
     * array.
     *
     * @return array
     */
    public function getDriverConfig()
    {
        // Determine config file name based on class name:
        $parts = explode('\\', $this->getDriverClass());
        $config = $this->configReader->get(end($parts));
        return is_object($config) ? $config->toArray() : array();
    }

    /**
     * Check Function
     *
     * This is responsible for checking the driver configuration to determine
     * if the system supports a particular function.
     *
     * @param string $function The name of the function to check.
     * @param string $id       (optional) A record id used to e.g. identify the used
     * backend with MultiBackend driver
     *
     * @return mixed On success, an associative array with specific function keys
     * and values; on failure, false.
     */
    public function checkFunction($function, $id = null)
    {
        // Extract the configuration from the driver if available:
        $functionConfig = $this->checkCapability(
            'getConfig',
            compact('function', 'id')
        ) ? $this->getDriver()->getConfig($function, $id) : false;

        // See if we have a corresponding check method to analyze the response:
        $checkMethod = "checkMethod".$function;
        if (!method_exists($this, $checkMethod)) {
            return false;
        }

        // Send back the settings:
        return $this->$checkMethod($functionConfig);
    }

    /**
     * Check Holds
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports Holds.
     *
     * @param array $functionConfig The Hold configuration values
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for placing holds via a form or a URL; on failure, false.
     */
    protected function checkMethodHolds($functionConfig)
    {
        $response = false;

        if ($this->getHoldsMode() != "none"
            && $this->checkCapability('placeHold')
            && isset($functionConfig['HMACKeys'])
        ) {
            $response = array('function' => "placeHold");
            $response['HMACKeys'] = explode(":", $functionConfig['HMACKeys']);
            if (isset($functionConfig['defaultRequiredDate'])) {
                $response['defaultRequiredDate']
                    = $functionConfig['defaultRequiredDate'];
            }
            if (isset($functionConfig['extraHoldFields'])) {
                $response['extraHoldFields'] = $functionConfig['extraHoldFields'];
            }
            if (isset($functionConfig['helpText'])) {
                $response['helpText'] = $this->getHelpText(
                    $functionConfig['helpText']
                );
            }
        } else if ($this->checkCapability('getHoldLink')) {
            $response = array('function' => "getHoldLink");
        }
        return $response;
    }

    /**
     * Check Cancel Holds
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports Cancelling Holds.
     *
     * @param array $functionConfig The Cancel Hold configuration values
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for cancelling holds via a form or a URL;
     * on failure, false.
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodcancelHolds($functionConfig)
    {
        $response = false;

        if (isset($this->config->cancel_holds_enabled)
            && $this->config->cancel_holds_enabled == true
            && $this->checkCapability('cancelHolds')
        ) {
            $response = array('function' => "cancelHolds");
        } else if (isset($this->config->cancel_holds_enabled)
            && $this->config->cancel_holds_enabled == true
            && $this->checkCapability('getCancelHoldLink')
        ) {
            $response = array('function' => "getCancelHoldLink");
        }
        return $response;
    }

    /**
     * Check Renewals
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports Renewing Items.
     *
     * @param array $functionConfig The Renewal configuration values
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for renewing items via a form or a URL; on failure, false.
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodRenewals($functionConfig)
    {
        $response = false;

        if (isset($this->config->renewals_enabled)
            && $this->config->renewals_enabled == true
            && $this->checkCapability('renewMyItems')
        ) {
            $response = array('function' => "renewMyItems");
        } else if (isset($this->config->renewals_enabled)
            && $this->config->renewals_enabled == true
            && $this->checkCapability('renewMyItemsLink')
        ) {
            $response = array('function' => "renewMyItemsLink");
        }
        return $response;
    }

    /**
     * Check Storage Retrieval Request
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports storage
     * retrieval requests.
     *
     * @param array $functionConfig The storage retrieval request configuration
     * values
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for placing requests via a form; on failure, false.
     */
    protected function checkMethodStorageRetrievalRequests($functionConfig)
    {
        $response = false;

        if ($this->checkCapability('placeStorageRetrievalRequest')
            && isset($functionConfig['HMACKeys'])
        ) {
            $response = array('function' => 'placeStorageRetrievalRequest');
            $response['HMACKeys'] = explode(':', $functionConfig['HMACKeys']);
            if (isset($functionConfig['extraFields'])) {
                $response['extraFields'] = $functionConfig['extraFields'];
            }
            if (isset($functionConfig['helpText'])) {
                $response['helpText'] = $this->getHelpText(
                    $functionConfig['helpText']
                );
            }
        }
        return $response;
    }

    /**
     * Check Cancel Storage Retrieval Requests
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports Cancelling
     * Storage Retrieval Requests.
     *
     * @param array $functionConfig The Cancel function configuration values
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for cancelling requests via a form or a URL;
     * on failure, false.
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodcancelStorageRetrievalRequests($functionConfig)
    {
        $response = false;

        if (isset($this->config->cancel_storage_retrieval_requests_enabled)
            && $this->config->cancel_storage_retrieval_requests_enabled
        ) {
            if ($this->checkCapability('cancelStorageRetrievalRequests')) {
                $response = array('function' => 'cancelStorageRetrievalRequests');
            } elseif ($this->checkCapability('getCancelStorageRetrievalRequestLink')
            ) {
                $response = array(
                    'function' => 'getCancelStorageRetrievalRequestLink'
                );
            }
        }
        return $response;
    }

    /**
     * Check ILL Request
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports storage
     * retrieval requests.
     *
     * @param array $functionConfig The ILL request configuration values
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for placing requests via a form; on failure, false.
     */
    protected function checkMethodILLRequests($functionConfig)
    {
        $response = false;

        if ($this->checkCapability('placeILLRequest')
            && isset($functionConfig['HMACKeys'])
        ) {
            $response = array('function' => 'placeILLRequest');
            $response['HMACKeys'] = explode(':', $functionConfig['HMACKeys']);
            if (isset($functionConfig['extraFields'])) {
                $response['extraFields'] = $functionConfig['extraFields'];
            }
            if (isset($functionConfig['helpText'])) {
                $response['helpText'] = $this->getHelpText(
                    $functionConfig['helpText']
                );
            }
        }
        return $response;
    }

    /**
     * Check Cancel ILL Requests
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports Cancelling
     * ILL Requests.
     *
     * @param array $functionConfig The Cancel function configuration values
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for cancelling requests via a form or a URL;
     * on failure, false.
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodcancelILLRequests($functionConfig)
    {
        $response = false;

        if (isset($this->config->cancel_ill_requests_enabled)
            && $this->config->cancel_ill_requests_enabled
        ) {
            if ($this->checkCapability('cancelILLRequests')) {
                $response = array('function' => 'cancelILLRequests');
            } elseif ($this->checkCapability('getCancelILLRequestLink')
            ) {
                $response = array(
                    'function' => 'getCancelILLRequestLink'
                );
            }
        }
        return $response;
    }

    /**
     * Get proper help text from the function config
     *
     * @param string|array $helpText Help text(s)
     *
     * @return string Language-specific help text
     */
    protected function getHelpText($helpText)
    {
        if (is_array($helpText)) {
            $lang = !is_null($this->translator)
                ? $this->translator->getLocale()
                : 'en';
            if (isset($helpText[$lang])) {
                return $helpText[$lang];
            }
            return '';
        }
        return $helpText;
    }

    /**
     * Check Request is Valid
     *
     * This is responsible for checking if a request is valid from hold.php
     *
     * @param string $id     A Bibliographic ID
     * @param array  $data   Collected Holds Data
     * @param array  $patron Patron related data
     *
     * @return mixed The result of the checkRequestIsValid function if it
     * exists, true if it does not
     */
    public function checkRequestIsValid($id, $data, $patron)
    {
        if ($this->checkCapability(
            'checkRequestIsValid', compact('id', 'data', 'patron')
        )) {
            return $this->getDriver()->checkRequestIsValid($id, $data, $patron);
        }
        // If the driver has no checkRequestIsValid method, we will assume that
        // all requests are valid - failure can be handled later after the user
        // attempts to place an illegal hold
        return true;
    }

    /**
     * Check Storage Retrieval Request is Valid
     *
     * This is responsible for checking if a storage retrieval request is valid
     *
     * @param string $id     A Bibliographic ID
     * @param array  $data   Collected Holds Data
     * @param array  $patron Patron related data
     *
     * @return mixed The result of the checkStorageRetrievalRequestIsValid
     * function if it exists, false if it does not
     */
    public function checkStorageRetrievalRequestIsValid($id, $data, $patron)
    {
        if ($this->checkCapability(
            'checkStorageRetrievalRequestIsValid', compact('id', 'data', 'patron')
        )) {
            return $this->getDriver()->checkStorageRetrievalRequestIsValid(
                $id, $data, $patron
            );
        }
        // If the driver has no checkStorageRetrievalRequestIsValid method, we
        // will assume that the request is not valid
        return false;
    }

    /**
     * Check ILL Request is Valid
     *
     * This is responsible for checking if an ILL request is valid
     *
     * @param string $id     A Bibliographic ID
     * @param array  $data   Collected Holds Data
     * @param array  $patron Patron related data
     *
     * @return mixed The result of the checkILLRequestIsValid
     * function if it exists, false if it does not
     */
    public function checkILLRequestIsValid($id, $data, $patron)
    {
        if ($this->checkCapability(
            'checkILLRequestIsValid', compact('id', 'data', 'patron')
        )) {
            return $this->getDriver()->checkILLRequestIsValid(
                $id, $data, $patron
            );
        }
        // If the driver has no checkILLRequestIsValid method, we
        // will assume that the request is not valid
        return false;
    }

    /**
     * Get Holds Mode
     *
     * This is responsible for returning the holds mode
     *
     * @return string The Holds mode
     */
    public function getHoldsMode()
    {
        return $this->holdsMode;
    }

    /**
     * Get Offline Mode
     *
     * This is responsible for returning the offline mode
     *
     * @return string|bool "ils-offline" for systems where the main ILS is offline,
     * "ils-none" for systems which do not use an ILS, false for online systems.
     */
    public function getOfflineMode()
    {
        // Graceful degradation -- return false if no method supported.
        return $this->checkCapability('getOfflineMode')
            ? $this->getDriver()->getOfflineMode() : false;
    }

    /**
     * Get Title Holds Mode
     *
     * This is responsible for returning the Title holds mode
     *
     * @return string The Title Holds mode
     */
    public function getTitleHoldsMode()
    {
        return $this->titleHoldsMode;
    }

    /**
     * Has Holdings
     *
     * Obtain information on whether or not the item has holdings
     *
     * @param string $id A bibliographic id
     *
     * @return bool true on success, false on failure
     */
    public function hasHoldings($id)
    {
        // Graceful degradation -- return true if no method supported.
        return $this->checkCapability('hasHoldings', compact('id'))
            ? $this->getDriver()->hasHoldings($id) : true;
    }

    /**
     * Get Hidden Login Mode
     *
     * This is responsible for indicating whether login should be hidden.
     *
     * @return bool true if the login should be hidden, false if not
     */
    public function loginIsHidden()
    {
        // Graceful degradation -- return false if no method supported.
        return $this->checkCapability('loginIsHidden')
            ? $this->getDriver()->loginIsHidden() : false;
    }

    /**
     * Check driver capability -- return true if the driver supports the specified
     * method; false otherwise.
     *
     * @param string $method Method to check
     * @param array  $params Array of passed parameters (optional)
     *
     * @return bool
     */
    public function checkCapability($method, $params = array())
    {
        // First check that the function is callable without the expense of
        // initializing the driver:
        if (is_callable(array($this->getDriverClass(), $method))) {
            // At least drivers implementing the __call() magic method must also
            // implement supportsMethod() to verify that the method is actually
            // usable:
            if (method_exists($this->getDriverClass(), 'supportsMethod')) {
                return $this->getDriver()->supportsMethod($method, $params);
            }
            return true;
        }

        // If we got this far, the feature is unsupported:
        return false;
    }

    /**
     * Get Names of Textual Holdings Fields
     *
     * Obtain information on which textual holdings fields should be displayed
     *
     * @return string[]
     */
    public function getHoldingsTextFieldNames()
    {
        return isset($this->config->holdings_text_fields)
            ? $this->config->holdings_text_fields->toArray()
            : array('notes', 'summary', 'supplements', 'indexes');
    }

    /**
     * Default method -- pass along calls to the driver if available; return
     * false otherwise.  This allows custom functions to be implemented in
     * the driver without constant modification to the connection class.
     *
     * @param string $methodName The name of the called method.
     * @param array  $params     Array of passed parameters.
     *
     * @throws ILSException
     * @return mixed             Varies by method (false if undefined method)
     */
    public function __call($methodName, $params)
    {
        if ($this->checkCapability($methodName, $params)) {
            return call_user_func_array(
                array($this->getDriver(), $methodName), $params
            );
        }
        throw new ILSException(
            'Cannot call method: ' . $this->getDriverClass() . '::' . $methodName
        );
    }
}
