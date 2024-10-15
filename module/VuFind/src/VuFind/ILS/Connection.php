<?php

/**
 * Catalog Connection Class
 *
 * This wrapper works with a driver class to pass information from the ILS to
 * VuFind.
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS;

use Laminas\Log\LoggerAwareInterface;
use Laminas\Session\Container;
use VuFind\Exception\BadConfig;
use VuFind\Exception\ILS as ILSException;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\ILS\Driver\DriverInterface;
use VuFind\ILS\Logic\AvailabilityStatus;

use function call_user_func_array;
use function count;
use function func_get_args;
use function get_class;
use function in_array;
use function intval;
use function is_array;
use function is_callable;
use function is_object;

/**
 * Catalog Connection Class
 *
 * This wrapper works with a driver class to pass information from the ILS to
 * VuFind.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Connection implements TranslatorAwareInterface, LoggerAwareInterface
{
    use \VuFind\Cache\CacheTrait {
        getCachedData as getSharedCachedData;
        putCachedData as putSharedCachedData;
    }
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

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
    protected $driver = null;

    /**
     * ILS configuration
     *
     * @var \Laminas\Config\Config
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
     * Driver plugin manager
     *
     * @var \VuFind\ILS\Driver\PluginManager
     */
    protected $driverManager;

    /**
     * Configuration loader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configReader;

    /**
     * Is the current ILS driver failing?
     *
     * @var bool
     */
    protected $failing = false;

    /**
     * Request object
     *
     * @var \Laminas\Http\Request
     */
    protected $request;

    /**
     * Cache life time per method
     *
     * @var array
     */
    protected $cacheLifeTime = ['*' => 60];

    /**
     * Cache storage per method
     *
     * Note: Don't cache anything too large in session before
     * https://openlibraryfoundation.atlassian.net/browse/VUFIND-1652 is implemented
     *
     * @var array
     */
    protected $cacheStorage = [
        'patronLogin' => 'session',
        'getProxiedUsers' => 'session',
        'getProxyingUsers' => 'session',
        'getPurchaseHistory' => 'shared',
    ];

    /**
     * Methods that invalidate the session cache
     *
     * @var array
     */
    protected $sessionCacheInvalidatingMethods = ['changePassword'];

    /**
     * Session cache
     *
     * @var Container
     */
    protected $sessionCache = null;

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config           $config        Configuration
     * representing the [Catalog] section of config.ini
     * @param \VuFind\ILS\Driver\PluginManager $driverManager Driver plugin manager
     * @param \VuFind\Config\PluginManager     $configReader  Configuration loader
     * @param \Laminas\Http\Request            $request       Request object
     */
    public function __construct(
        \Laminas\Config\Config $config,
        \VuFind\ILS\Driver\PluginManager $driverManager,
        \VuFind\Config\PluginManager $configReader,
        \Laminas\Http\Request $request = null
    ) {
        if (!isset($config->driver)) {
            throw new \Exception('ILS driver setting missing.');
        }
        if (!$driverManager->has($config->driver)) {
            throw new \Exception('ILS driver missing: ' . $config->driver);
        }
        $this->config = $config;
        $this->configReader = $configReader;
        $this->driverManager = $driverManager;
        $this->request = $request;
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
     * Set session container for cache.
     *
     * @param Container $container Session container
     *
     * @return Connection
     */
    public function setSessionCache(Container $container)
    {
        $this->sessionCache = $container;
        return $this;
    }

    /**
     * Set cache lifetime settings
     *
     * @param array $settings Lifetime settings
     *
     * @return void
     */
    public function setCacheLifeTime(array $settings): void
    {
        $this->cacheLifeTime = array_merge($this->cacheLifeTime, $settings);
    }

    /**
     * Get class name of the driver object.
     *
     * @return string
     */
    public function getDriverClass()
    {
        return get_class($this->getDriver(false));
    }

    /**
     * Initialize the ILS driver.
     *
     * @return void
     */
    protected function initializeDriver()
    {
        try {
            $this->driver->setConfig($this->getDriverConfig());
        } catch (\Exception $e) {
            // Any errors thrown during configuration should be cast to BadConfig
            // so we can handle them differently from other runtime problems.
            throw $e instanceof BadConfig
                ? $e
                : new BadConfig('Failure during configuration.', 0, $e);
        }
        $this->driver->init();
        $this->driverInitialized = true;
    }

    /**
     * Are we configured to fail over to the NoILS driver on error?
     *
     * @return bool
     */
    protected function hasNoILSFailover()
    {
        // If we're configured to fail over to the NoILS driver, do so now:
        return isset($this->config->loadNoILSOnFailure)
            && $this->config->loadNoILSOnFailure;
    }

    /**
     * If configured, fail over to the NoILS driver and return true; otherwise,
     * return false.
     *
     * @param \Exception $e The exception that triggered the failover.
     *
     * @return bool
     */
    protected function failOverToNoILS(\Exception $e = null)
    {
        // If the exception is caused by a configuration error, the administrator
        // needs to fix it, but failing over to NoILS will mask the error and cause
        // confusion. We shouldn't do that!
        if ($e instanceof BadConfig) {
            return false;
        }

        // If we got this far, we want to proceed with failover...
        $this->failing = true;

        // Only fail over if we're configured to allow it and we haven't already
        // done so!
        if ($this->hasNoILSFailover()) {
            $noILS = $this->driverManager->get('NoILS');
            if ($noILS::class != $this->getDriverClass()) {
                $this->setDriver($noILS);
                $this->initializeDriver();
                return true;
            }
        }
        return false;
    }

    /**
     * Get access to the driver object.
     *
     * @param bool $init Should we initialize the driver (if necessary), or load it
     * "as-is"?
     *
     * @throws \Exception
     * @return object
     */
    public function getDriver($init = true)
    {
        if (null === $this->driver) {
            $this->setDriver($this->driverManager->get($this->config->driver));
        }
        if (!$this->driverInitialized && $init) {
            try {
                $this->initializeDriver();
            } catch (\Exception $e) {
                if (!$this->failOverToNoILS($e)) {
                    throw $e;
                }
            }
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
     * Get configuration for the ILS driver. We will load an .ini file named
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
        return is_object($config) ? $config->toArray() : [];
    }

    /**
     * Check Function
     *
     * This is responsible for checking the driver configuration to determine
     * if the system supports a particular function.
     *
     * @param string $function The name of the function to check.
     * @param array  $params   (optional) An array of function-specific parameters
     *
     * @return mixed On success, an associative array with specific function keys
     * and values; on failure, false.
     */
    public function checkFunction($function, $params = null)
    {
        try {
            // Extract the configuration from the driver if available:
            $functionConfig = $this->checkCapability(
                'getConfig',
                [$function, $params],
                true
            ) ? $this->getDriver()->getConfig($function, $params) : false;

            // See if we have a corresponding check method to analyze the response:
            $checkMethod = 'checkMethod' . $function;
            if (!method_exists($this, $checkMethod)) {
                return false;
            }

            // Send back the settings:
            return $this->$checkMethod($functionConfig, $params);
        } catch (ILSException $e) {
            $this->logError(
                "checkFunction($function) with params: " . $this->varDump($params)
                . ' failed: ' . $e->getMessage()
            );
            return false;
        }
    }

    /**
     * Check Holds
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports Holds.
     *
     * @param array $functionConfig The Hold configuration values
     * @param array $params         An array of function-specific params (or null)
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for placing holds via a form or a URL; on failure, false.
     */
    protected function checkMethodHolds($functionConfig, $params)
    {
        $response = false;

        // We pass an array containing $params to checkCapability since $params
        // should contain 'id' and 'patron' keys; this isn't exactly the same as
        // the full parameter expected by placeHold() but should contain the
        // necessary details for determining eligibility.
        if (
            $this->getHoldsMode() != 'none'
            && $this->checkCapability('placeHold', [$params ?: []])
            && isset($functionConfig['HMACKeys'])
        ) {
            $response = ['function' => 'placeHold'];
            $response['HMACKeys'] = explode(':', $functionConfig['HMACKeys']);
            if (isset($functionConfig['defaultRequiredDate'])) {
                $response['defaultRequiredDate']
                    = $functionConfig['defaultRequiredDate'];
            }
            if (isset($functionConfig['extraHoldFields'])) {
                $response['extraHoldFields'] = $functionConfig['extraHoldFields'];
            }
            if (!empty($functionConfig['updateFields'])) {
                $response['updateFields'] = array_map(
                    'trim',
                    explode(':', $functionConfig['updateFields'])
                );
            }
            $response['helpText']
                = $this->getHelpText($functionConfig['helpText'] ?? '');
            $response['updateHelpText']
                = $this->getHelpText($functionConfig['updateHelpText'] ?? '');
            if (isset($functionConfig['consortium'])) {
                $response['consortium'] = $functionConfig['consortium'];
            }
            $response['pickUpLocationCheckLimit']
                = intval($functionConfig['pickUpLocationCheckLimit'] ?? 0);
        } else {
            $id = $params['id'] ?? null;
            if ($this->checkCapability('getHoldLink', [$id, []])) {
                $response = ['function' => 'getHoldLink'];
            }
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
     * @param array $params         An array of function-specific params (or null)
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for cancelling holds via a form or a URL;
     * on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodcancelHolds($functionConfig, $params)
    {
        $response = false;

        // We can't pass exactly accurate parameters to checkCapability in this
        // context, so we'll just pass along $params as the best available
        // approximation.
        if (
            isset($this->config->cancel_holds_enabled)
            && $this->config->cancel_holds_enabled == true
            && $this->checkCapability('cancelHolds', [$params ?: []])
        ) {
            $response = ['function' => 'cancelHolds'];
        } elseif (
            isset($this->config->cancel_holds_enabled)
            && $this->config->cancel_holds_enabled == true
            && $this->checkCapability('getCancelHoldLink', [$params ?: []])
        ) {
            $response = ['function' => 'getCancelHoldLink'];
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
     * @param array $params         An array of function-specific params (or null)
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for renewing items via a form or a URL; on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodRenewals($functionConfig, $params)
    {
        $response = false;

        // We can't pass exactly accurate parameters to checkCapability in this
        // context, so we'll just pass along $params as the best available
        // approximation.
        if (
            isset($this->config->renewals_enabled)
            && $this->config->renewals_enabled == true
            && $this->checkCapability('renewMyItems', [$params ?: []])
        ) {
            $response = ['function' => 'renewMyItems'];
        } elseif (
            isset($this->config->renewals_enabled)
            && $this->config->renewals_enabled == true
            && $this->checkCapability('renewMyItemsLink', [$params ?: []])
        ) {
            $response = ['function' => 'renewMyItemsLink'];
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
     * @param array $params         An array of function-specific params (or null)
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for placing requests via a form; on failure, false.
     */
    protected function checkMethodStorageRetrievalRequests($functionConfig, $params)
    {
        $response = false;

        // $params doesn't include all of the keys used by
        // placeStorageRetrievalRequest, but it is the best we can do in the context.
        $check = $this->checkCapability(
            'placeStorageRetrievalRequest',
            [$params ?: []]
        );
        if ($check && isset($functionConfig['HMACKeys'])) {
            $response = ['function' => 'placeStorageRetrievalRequest'];
            $response['HMACKeys'] = explode(':', $functionConfig['HMACKeys']);
            if (isset($functionConfig['extraFields'])) {
                $response['extraFields'] = $functionConfig['extraFields'];
            }
            $response['helpText']
                = $this->getHelpText($functionConfig['helpText'] ?? '');
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
     * @param array $params         An array of function-specific params (or null)
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for cancelling requests via a form or a URL;
     * on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodcancelStorageRetrievalRequests(
        $functionConfig,
        $params
    ) {
        $response = false;

        if (
            isset($this->config->cancel_storage_retrieval_requests_enabled)
            && $this->config->cancel_storage_retrieval_requests_enabled
        ) {
            $check = $this->checkCapability(
                'cancelStorageRetrievalRequests',
                [$params ?: []]
            );
            if ($check) {
                $response = ['function' => 'cancelStorageRetrievalRequests'];
            } else {
                $cancelParams = [
                    $params ?: [],
                    $params['patron'] ?? null,
                ];
                $check2 = $this->checkCapability(
                    'getCancelStorageRetrievalRequestLink',
                    $cancelParams
                );
                if ($check2) {
                    $response = [
                        'function' => 'getCancelStorageRetrievalRequestLink',
                    ];
                }
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
     * @param array $params         An array of function-specific params (or null)
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for placing requests via a form; on failure, false.
     */
    protected function checkMethodILLRequests($functionConfig, $params)
    {
        $response = false;

        // $params doesn't include all of the keys used by
        // placeILLRequest, but it is the best we can do in the context.
        if (
            $this->checkCapability('placeILLRequest', [$params ?: []])
            && isset($functionConfig['HMACKeys'])
        ) {
            $response = ['function' => 'placeILLRequest'];
            if (isset($functionConfig['defaultRequiredDate'])) {
                $response['defaultRequiredDate']
                    = $functionConfig['defaultRequiredDate'];
            }
            $response['HMACKeys'] = explode(':', $functionConfig['HMACKeys']);
            if (isset($functionConfig['extraFields'])) {
                $response['extraFields'] = $functionConfig['extraFields'];
            }
            $response['helpText']
                = $this->getHelpText($functionConfig['helpText']);
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
     * @param array $params         An array of function-specific params (or null)
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for cancelling requests via a form or a URL;
     * on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodcancelILLRequests($functionConfig, $params)
    {
        $response = false;

        if (
            isset($this->config->cancel_ill_requests_enabled)
            && $this->config->cancel_ill_requests_enabled
        ) {
            $check = $this->checkCapability(
                'cancelILLRequests',
                [$params ?: []]
            );
            if ($check) {
                $response = ['function' => 'cancelILLRequests'];
            } else {
                $cancelParams = [
                    $params ?: [],
                    $params['patron'] ?? null,
                ];
                $check2 = $this->checkCapability(
                    'getCancelILLRequestLink',
                    $cancelParams
                );
                if ($check2) {
                    $response = [
                        'function' => 'getCancelILLRequestLink',
                    ];
                }
            }
        }
        return $response;
    }

    /**
     * Check Password Change
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports changing
     * password.
     *
     * @param array $functionConfig The password change configuration values
     * @param array $params         Patron data
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for cancelling requests via a form or a URL;
     * on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodchangePassword($functionConfig, $params)
    {
        if ($this->checkCapability('changePassword', [$params ?: []])) {
            return ['function' => 'changePassword'];
        }
        return false;
    }

    /**
     * Check Current Loans
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports current
     * loans.
     *
     * @param array $functionConfig Function configuration
     * @param array $params         Patron data
     *
     * @return mixed On success, an associative array with specific function keys
     * and values; on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodgetMyTransactions($functionConfig, $params)
    {
        if ($this->checkCapability('getMyTransactions', [$params ?: []])) {
            return $functionConfig;
        }
        return false;
    }

    /**
     * Check Historic Loans
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports historic
     * loans.
     *
     * @param array $functionConfig Function configuration
     * @param array $params         Patron data
     *
     * @return mixed On success, an associative array with specific function keys
     * and values; on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodgetMyTransactionHistory($functionConfig, $params)
    {
        if ($this->checkCapability('getMyTransactionHistory', [$params ?: []])) {
            return $functionConfig;
        }
        return false;
    }

    /**
     * Check Purge Historic Loans
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports purging of
     * historic loans.
     *
     * @param array $functionConfig Function configuration
     * @param array $params         Patron data
     *
     * @return mixed On success, an associative array with specific function keys
     * and values; on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodpurgeTransactionHistory($functionConfig, $params)
    {
        if ($this->checkCapability('purgeTransactionHistory', [$params ?: []])) {
            return $functionConfig;
        }
        return false;
    }

    /**
     * Check Patron login
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports patron login.
     * It is currently assumed that all drivers do.
     *
     * @param array $functionConfig The patronLogin configuration values
     * @param array $params         An array of function-specific params (or null)
     *
     * @return mixed On success, an associative array with specific function keys
     * and values for login; on failure, false.
     */
    protected function checkMethodpatronLogin($functionConfig, $params)
    {
        return $functionConfig;
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
            $lang = $this->getTranslatorLocale();
            return $helpText[$lang] ?? $helpText['*'] ?? '';
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
        try {
            $params = [$id, $data, $patron];
            if ($this->checkCapability('checkRequestIsValid', $params)) {
                return $this->getDriver()->checkRequestIsValid($id, $data, $patron);
            }
        } catch (\Exception $e) {
            if ($this->failOverToNoILS($e)) {
                return call_user_func_array([$this, __METHOD__], func_get_args());
            }
            throw $e;
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
        try {
            $check = $this->checkCapability(
                'checkStorageRetrievalRequestIsValid',
                [$id, $data, $patron]
            );
            if ($check) {
                return $this->getDriver()->checkStorageRetrievalRequestIsValid(
                    $id,
                    $data,
                    $patron
                );
            }
        } catch (\Exception $e) {
            if ($this->failOverToNoILS($e)) {
                return call_user_func_array([$this, __METHOD__], func_get_args());
            }
            throw $e;
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
        try {
            $params = [$id, $data, $patron];
            if ($this->checkCapability('checkILLRequestIsValid', $params)) {
                return $this->getDriver()->checkILLRequestIsValid(
                    $id,
                    $data,
                    $patron
                );
            }
        } catch (\Exception $e) {
            if ($this->failOverToNoILS($e)) {
                return call_user_func_array([$this, __METHOD__], func_get_args());
            }
            throw $e;
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
     * @param bool $healthCheck Perform a health check in addition to consulting
     * the ILS status?
     *
     * @return string|bool "ils-offline" for systems where the main ILS is offline,
     * "ils-none" for systems which do not use an ILS, false for online systems.
     */
    public function getOfflineMode($healthCheck = false)
    {
        // If we have NoILS failover configured, force driver initialization so
        // we can know we are checking the offline mode against the correct driver.
        if ($this->hasNoILSFailover()) {
            $this->getDriver();
        }

        // If we need to perform a health check, try to do a random item lookup
        // before proceeding.
        if ($healthCheck) {
            $this->getStatus($this->config->healthCheckId ?? '1');
        }

        // If we're encountering failures, let's go into ils-offline mode if
        // the ILS driver does not natively support getOfflineMode().
        $default = $this->failing ? 'ils-offline' : false;

        // Graceful degradation -- return false if no method supported.
        return $this->checkCapability('getOfflineMode')
            ? $this->getDriver()->getOfflineMode() : $default;
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
        try {
            return $this->checkCapability('hasHoldings', [$id])
                ? $this->getDriver()->hasHoldings($id) : true;
        } catch (\Exception $e) {
            if ($this->failOverToNoILS($e)) {
                return call_user_func_array([$this, __METHOD__], func_get_args());
            }
            throw $e;
        }
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
        try {
            return $this->checkCapability('loginIsHidden')
                ? $this->getDriver()->loginIsHidden() : false;
        } catch (\Exception $e) {
            if ($this->failOverToNoILS($e)) {
                return call_user_func_array([$this, __METHOD__], func_get_args());
            }
            throw $e;
        }
    }

    /**
     * Check driver capability -- return true if the driver supports the specified
     * method; false otherwise.
     *
     * @param string $method Method to check
     * @param array  $params Array of passed parameters (optional)
     * @param bool   $throw  Whether to throw exceptions instead of returning false
     *
     * @return bool
     * @throws ILSException
     */
    public function checkCapability($method, $params = [], $throw = false)
    {
        try {
            // If we have NoILS failover disabled, we can check capabilities of
            // the driver class without wasting time initializing it; if NoILS
            // failover is enabled, we have to initialize the driver object now
            // to be sure we are checking capabilities on the appropriate class.
            $driverToCheck = $this->getDriver($this->hasNoILSFailover());

            // First check that the function is callable:
            if (is_callable([$driverToCheck, $method])) {
                // At least drivers implementing the __call() magic method must also
                // implement supportsMethod() to verify that the method is actually
                // usable:
                if (method_exists($driverToCheck, 'supportsMethod')) {
                    return $this->getDriver()->supportsMethod($method, $params);
                }
                return true;
            }
        } catch (ILSException $e) {
            $this->logError(
                "checkCapability($method) with params: " . $this->varDump($params)
                . ' failed: ' . $e->getMessage()
            );
            if ($throw) {
                throw $e;
            }
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
            : ['holdings_notes', 'summary', 'supplements', 'indexes'];
    }

    /**
     * Get the password policy from the driver
     *
     * @param array $patron Patron data
     *
     * @return bool|array Password policy array or false if unsupported
     */
    public function getPasswordPolicy($patron)
    {
        return $this->checkCapability(
            'getConfig',
            ['changePassword', compact('patron')]
        ) ? $this->getDriver()->getConfig('changePassword', compact('patron'))
            : false;
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     * @param array $params Parameters
     *
     * @return mixed        Array of the patron's transactions
     */
    public function getMyTransactions($patron, $params = [])
    {
        $result = $this->__call('getMyTransactions', [$patron, $params]);

        // Support also older driver return value:
        if (!isset($result['count'])) {
            $result = [
                'count' => count($result),
                'records' => $result,
            ];
        }

        return $result;
    }

    /**
     * Get holdings
     *
     * Retrieve holdings from ILS driver class and normalize result array and availability if needed.
     *
     * @param string $id      The record id to retrieve the holdings for
     * @param array  $patron  Patron data
     * @param array  $options Additional options
     *
     * @return array Array with holding data
     */
    public function getHolding($id, $patron = null, $options = [])
    {
        // Get pagination options for holdings tab:
        $params = compact('id', 'patron');
        $config = $this->checkCapability('getConfig', ['Holdings', $params])
            ? $this->getDriver()->getConfig('Holdings', $params) : [];
        if (empty($config['itemLimit'])) {
            // Use itemLimit in Holds as fallback for backward compatibility:
            $config
                = $this->checkCapability('getConfig', ['Holds', $params])
                ? $this->getDriver()->getConfig('Holds', $params) : [];
        }
        $itemLimit = !empty($config['itemLimit']) ? $config['itemLimit'] : null;

        $page = $this->request ? $this->request->getQuery('page', 1) : 1;
        $offset = ($itemLimit && is_numeric($itemLimit))
            ? ($page * $itemLimit) - $itemLimit
            : null;
        $defaultOptions = compact('page', 'itemLimit', 'offset');
        $finalOptions = $options + $defaultOptions;

        // Get the holdings from the ILS
        $holdings = $this->__call('getHolding', [$id, $patron, $finalOptions]);

        // Return all the necessary details:
        if (!isset($holdings['holdings'])) {
            $holdings = [
                'total' => count($holdings),
                'holdings' => $holdings,
                'electronic_holdings' => [],
            ];
        } else {
            if (!isset($holdings['total'])) {
                $holdings['total'] = count($holdings['holdings']);
            }
            if (!isset($holdings['electronic_holdings'])) {
                $holdings['electronic_holdings'] = [];
            }
        }

        // parse availability and status to AvailabilityStatus object
        $holdings['holdings'] = array_map($this->getStatusParser(), $holdings['holdings']);
        $holdings['electronic_holdings'] = array_map($this->getStatusParser(), $holdings['electronic_holdings']);
        $holdings['page'] = $finalOptions['page'];
        $holdings['itemLimit'] = $finalOptions['itemLimit'];
        return $holdings;
    }

    /**
     * Get status
     *
     * Retrieve status from ILS driver class and normalize availability if needed.
     *
     * @param string $id The record id to retrieve the status for
     *
     * @return array Array with holding data
     */
    public function getStatus($id)
    {
        $status = $this->__call('getStatus', [$id]);

        // parse availability and status to AvailabilityStatus object
        return array_map($this->getStatusParser(), $status);
    }

    /**
     * Get statuses
     *
     * Retrieve statuses from ILS driver class and normalize availability if needed.
     *
     * @param string $ids The record ids to retrieve the statuses for
     *
     * @return array Array with holding data
     */
    public function getStatuses($ids)
    {
        $statuses = $this->__call('getStatuses', [$ids]);

        return array_map(function ($status) {
            // parse availability and status to AvailabilityStatus object
            return array_map($this->getStatusParser(), $status);
        }, $statuses);
    }

    /**
     * Get a function that parses availability and status to an AvailabilityStatus object if necessary.
     *
     * @return callable
     */
    public function getStatusParser()
    {
        return function ($item) {
            if (!(($item['availability'] ?? null) instanceof AvailabilityStatus)) {
                $availability = $item['availability'] ?? false;
                if ($item['use_unknown_message'] ?? false) {
                    $availability = Logic\AvailabilityStatusInterface::STATUS_UNKNOWN;
                }
                $item['availability'] = new AvailabilityStatus(
                    $availability,
                    $item['status'] ?? ''
                );
                unset($item['status']);
                unset($item['use_unknown_message']);
            }
            return $item;
        };
    }

    /**
     * Call an ILS method with failover to NoILS if configured.
     *
     * @param string $methodName The name of the called method.
     * @param array  $params     Array of passed parameters.
     *
     * @throws ILSException
     * @return mixed             Varies by method (false if undefined method)
     */
    public function callIlsWithFailover($methodName, $params)
    {
        try {
            if ($this->checkCapability($methodName, $params)) {
                return call_user_func_array(
                    [$this->getDriver(), $methodName],
                    $params
                );
            }
        } catch (\Exception $e) {
            if ($this->failOverToNoILS($e)) {
                return call_user_func_array([$this, __METHOD__], func_get_args());
            }
            throw $e;
        }
        throw new ILSException(
            'Cannot call method: ' . $this->getDriverClass() . '::' . $methodName
        );
    }

    /**
     * Get data for an ILS method from shared or session cache
     *
     * @param array $cacheSettings Cache settings
     *
     * @return ?array
     */
    protected function getCachedData(array $cacheSettings): ?array
    {
        $cacheKey = $cacheSettings['key'];
        if ('shared' === $cacheSettings['storage']) {
            return $this->getSharedCachedData($cacheKey);
        }
        if ($this->sessionCache && ($entry = $this->sessionCache[$cacheKey] ?? null)) {
            if (time() - $entry['ts'] <= $cacheSettings['lifeTime']) {
                return $entry['payload'];
            }
            unset($this->sessionCache[$cacheKey]);
        }
        return null;
    }

    /**
     * Put data for an ILS method to shared or session cache.
     *
     * @param array $cacheSettings Cache settings
     * @param array $data          Data to cache
     *
     * @return void
     */
    protected function putCachedData(array $cacheSettings, array $data): void
    {
        $cacheKey = $cacheSettings['key'];
        if ('shared' === $cacheSettings['storage']) {
            $this->putSharedCachedData($cacheKey, $data, $cacheSettings['lifeTime']);
            return;
        }
        if ($this->sessionCache) {
            $this->sessionCache[$cacheKey] = [
                'ts' => time(),
                'payload' => $data,
            ];
        }
    }

    /**
     * Clear session cache if the given method requires it
     *
     * @param string $methodName Method name
     *
     * @return void
     */
    protected function clearSessionCacheIfRequired($methodName): void
    {
        if ($this->sessionCache && in_array($methodName, $this->sessionCacheInvalidatingMethods)) {
            $this->sessionCache->exchangeArray([]);
        }
    }

    /**
     * Get cache settings for a method
     *
     * @param string $methodName The name of the called method.
     * @param array  $params     Array of passed parameters.
     *
     * @return ?array
     */
    protected function getCacheSettings($methodName, $params): ?array
    {
        $lifeTime = (int)($this->cacheLifeTime[$methodName] ?? $this->cacheLifeTime['*'] ?? 0);
        $storage = $this->cacheStorage[$methodName] ?? null;
        if (!$lifeTime || !$storage) {
            return null;
        }
        $key = $methodName . md5(serialize($params));
        return compact('lifeTime', 'storage', 'key');
    }

    /**
     * Default method -- pass along calls to the driver if available; return
     * false otherwise. This allows custom functions to be implemented in
     * the driver without constant modification to the connection class.
     *
     * Results of certain methods (such as patronLogin) may be cached to avoid
     * hammering the ILS with the same request repeatedly.
     *
     * @param string $methodName The name of the called method.
     * @param array  $params     Array of passed parameters.
     *
     * @throws ILSException
     * @return mixed             Varies by method (false if undefined method)
     */
    public function __call($methodName, $params)
    {
        $cacheSettings = $this->getCacheSettings($methodName, $params);
        // Note: The actual data is cached in an array so that we can differentiate
        // between a missing cache entry and null as a valid value.
        if ($cacheSettings && ($cached = $this->getCachedData($cacheSettings))) {
            return $cached['data'];
        }

        $this->clearSessionCacheIfRequired($methodName);

        $data = $this->callIlsWithFailover($methodName, $params);
        if ($cacheSettings) {
            $this->putCachedData($cacheSettings, compact('data'));
        }
        return $data;
    }
}
