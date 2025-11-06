<?php

/**
 * Multiple Backend Driver.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2024.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  ILSdrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace Finna\ILS\Driver;

use VuFind\Exception\ILS as ILSException;
use VuFind\I18n\Translator\TranslatorAwareInterface;

use function func_get_args;
use function is_array;

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
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class MultiBackend extends \VuFind\ILS\Driver\MultiBackend implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

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
        parent::init();

        if (
            null === $this->defaultDriver
            || !isset($this->drivers[$this->defaultDriver])
        ) {
            // Try default login driver
            $driver = $this->getDefaultLoginDriver();
            if ($driver && isset($this->drivers[$driver])) {
                $this->defaultDriver = $driver;
            } elseif ($this->drivers) {
                // Use first available driver
                $drivers = array_keys($this->drivers);
                $this->defaultDriver = reset($drivers);
            }
        }
    }

    /**
     * Get available login targets (drivers enabled for login)
     *
     * @return string[] Source ID's
     */
    public function getLoginDrivers()
    {
        $drivers = parent::getLoginDrivers();
        $drivers = array_intersect($drivers, array_keys($this->drivers));
        if ($this->config['General']['sort_login_drivers'] ?? true) {
            usort(
                $drivers,
                function ($a, $b) {
                    $at = $this->translate("source_$a", null, $a);
                    $bt = $this->translate("source_$b", null, $b);
                    return strcmp($at, $bt);
                }
            );
        }
        return $drivers;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron user id or barcode
     * @param string $password The patron password
     *
     * @return mixed           Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {
        $patron = $this->callMethodIfSupported(null, 'patronLogin', func_get_args());
        if (is_array($patron)) {
            $patron['source'] = $this->getSource($username);
            $patron['__source'] = $patron['source'];
            $patron['__local_cat_username'] = $this->getLocalId($patron['cat_username']);
            $patron['__local_id'] = $this->getLocalId($patron['id']);
        }
        return $patron ?: null;
    }

    /**
     * Get Renew Details
     *
     * In order to renew an item, the ILS requires information on the item and
     * patron. This function returns the information as a string which is then used
     * as submitted form data in checkedOut.php. This value is then extracted by
     * the RenewMyItems function.
     *
     * @param array $checkoutDetails An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getRenewDetails($checkoutDetails)
    {
        if (empty($checkoutDetails['id'])) {
            return '';
        }
        return $this
            ->callMethodIfSupported(null, 'getRenewDetails', func_get_args());
    }

    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver. Required method for any smart drivers.
     *
     * @param string $method The name of the called method.
     * @param array  $params Array of passed parameters.
     *
     * @return bool True if the method can be called with the given parameters,
     * false otherwise.
     */
    public function supportsMethod($method, $params)
    {
        if ($method == 'loginIsHidden') {
            // Workaround for too early call before theme init
            return false;
        }

        return parent::supportsMethod($method, $params);
    }

    /**
     * Get configuration for the ILS driver. We will load an .ini file named
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
        $config = [];
        try {
            $config = $this->configManager->getConfigArray(
                $this->drivers[$source] . '_' . $source
            );
            // Fallback for KohaRestSuomi to also look for KohaRest_$source.ini
            if (!$config && 'KohaRestSuomi' === $this->drivers[$source]) {
                $config = $this->configManager->getConfigArray(
                    'KohaRest_' . $source
                );
            }
        } catch (\Laminas\Config\Exception\RuntimeException $e) {
            // Fall through
        }
        if (!$config) {
            $config = parent::getDriverConfig($source);
        }

        // Remap online payment settings and merge settings from datasources.ini for back-compatibility:
        if (empty($config['OnlinePayment']) && !empty($config['onlinePayment'])) {
            $config['OnlinePayment'] = $config['onlinePayment'];
        }
        if (isset($config['OnlinePayment'])) {
            $config['OnlinePayment'] = $this->remapPaymentConfig($config['OnlinePayment']);
            $datasourceConfig = $this->configManager->getConfigArray('datasources');
            if ($paymentConfig = $datasourceConfig[$source]['onlinePayment'] ?? null) {
                $config['OnlinePayment']
                    = array_merge($this->remapPaymentConfig($paymentConfig), $config['OnlinePayment']);
            }
        }

        return $config;
    }

    /**
     * Remap legacy online payment configuration
     *
     * @param array $config Payment configuration
     *
     * @return array
     */
    protected function remapPaymentConfig(array $config): array
    {
        static $map = [
            'organizationProductCodeMappings' => 'organizationProductCodePrefixMappings',
            'transactionFee' => 'serviceFee',
            'transactionMaxDuration' => 'paymentMaxDuration',
        ];

        $result = [];
        foreach ($config as $key => $value) {
            if ('handler' === $key && 'PaytrailPaymentAPI' === $value) {
                $value = 'Paytrail';
            }
            $result[$map[$key] ?? $key] = $value;
        }
        if (!isset($result['vatBreakdown'])) {
            $result['vatBreakdown'] = true;
        }
        return $result;
    }
}
