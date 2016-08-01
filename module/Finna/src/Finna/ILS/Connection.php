<?php
/**
 * Catalog Connection Class
 *
 * This wrapper works with a driver class to pass information from the ILS to
 * VuFind.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace Finna\ILS;
use VuFind\Exception\ILS as ILSException;

/**
 * Catalog Connection Class
 *
 * This wrapper works with a driver class to pass information from the ILS to
 * VuFind.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Connection extends \VuFind\ILS\Connection
{
    /**
     * Change Password
     *
     * Attempts to change patron password (PIN code)
     *
     * @param array $details An array of patron id and old and new password
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function changePassword($details)
    {
        if (!$this->checkCapability('changePassword', compact('details'))) {
            throw new ILSException(
                'Cannot call method: ' . $this->getDriverClass() . '::changePassword'
            );
        }
        return $this->getDriver()->changePassword($details);
    }

    /**
     * Check for Authorization Status
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports getting
     * authorization status.
     *
     * @param array $functionConfig The configuration values
     * @param array $params         Patron data
     *
     * @return mixed On success, an associative array with specific function keys
     * and values for getting authorization status; on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodgetPatronAuthorizationStatus(
        $functionConfig, $params
    ) {
        if ($this->checkCapability('getPatronAuthorizationStatus', [$params ?: []])
        ) {
            return ['function' => 'getPatronAuthorizationStatus'];
        }
        return false;
    }

    /**
     * Check for updateAddress
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports updating address.
     *
     * @param array $functionConfig The configuration values
     * @param array $params         Patron data
     *
     * @return mixed On success, array of configuration data; on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodupdateAddress(
        $functionConfig, $params
    ) {
        if (!isset($functionConfig['method'])) {
            return false;
        }
        if ($functionConfig['method'] == 'email'
            && !empty($functionConfig['emailAddress'])
        ) {
            return $functionConfig;
        }
        if ($functionConfig['method'] == 'url' && !empty($functionConfig['url'])) {
            return $functionConfig;
        }
        if ($functionConfig['method'] == 'driver'
            && $this->checkCapability('updateAddress', [$params ?: []])
        ) {
            return $functionConfig;
        }

        return false;
    }

    /**
     * Check for changePickupLocation
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports change of
     * the pickup location.
     *
     * @param array $functionConfig The configuration values
     * @param array $params         Patron data
     *
     * @return mixed On success, array of configuration data; on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodchangePickupLocation($functionConfig, $params)
    {
        if (!isset($functionConfig['method'])) {
            return false;
        }

        if ($this->checkCapability('changePickupLocation', [$params ?: []])
        ) {
            return $functionConfig;
        }

        return false;
    }

    /**
     * Check for updateEmail
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports updating email
     * address.
     *
     * @param array $functionConfig The configuration values
     * @param array $params         Patron data
     *
     * @return mixed On success, array of configuration data; on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodupdateEmail($functionConfig, $params)
    {
        if (!isset($functionConfig['method'])) {
            return false;
        }
        if ($functionConfig['method'] == 'email'
            && !empty($functionConfig['emailAddress'])
        ) {
            return $functionConfig;
        }
        if ($functionConfig['method'] == 'url' && !empty($functionConfig['url'])) {
            return $functionConfig;
        }
        if ($functionConfig['method'] == 'driver'
            && $this->checkCapability('updateEmail', [$params ?: []])
        ) {
            return $functionConfig;
        }

        return false;
    }

    /**
     * Check for updateMessagingSettings
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports updating
     * messaging settings.
     *
     * @param array $functionConfig The configuration values
     * @param array $params         Patron data
     *
     * @return mixed On success, array of configuration data; on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodupdateMessagingSettings($functionConfig, $params)
    {
        if (!isset($functionConfig['method'])) {
            return false;
        }
        if ($functionConfig['method'] == 'email'
            && !empty($functionConfig['emailAddress'])
        ) {
            return $functionConfig;
        }
        if ($functionConfig['method'] == 'url' && !empty($functionConfig['url'])) {
            return $functionConfig;
        }
        if ($functionConfig['method'] == 'driver'
            && $this->checkCapability('updateMessagingSettings', [$params ?: []])
        ) {
            return $functionConfig;
        }

        return false;
    }

    /**
     * Check for updatePhone
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports updating phone
     * number.
     *
     * @param array $functionConfig The configuration values
     * @param array $params         Patron data
     *
     * @return mixed On success, array of configuration data; on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodupdatePhone($functionConfig, $params)
    {
        if (!isset($functionConfig['method'])) {
            return false;
        }
        if ($functionConfig['method'] == 'email'
            && !empty($functionConfig['emailAddress'])
        ) {
            return $functionConfig;
        }
        if ($functionConfig['method'] == 'url' && !empty($functionConfig['url'])) {
            return $functionConfig;
        }
        if ($functionConfig['method'] == 'driver'
            && $this->checkCapability('updatePhone', [$params ?: []])
        ) {
            return $functionConfig;
        }

        return false;
    }

    /**
     * Check if catalog login is availale
     *
     * @return bool true if the login is available
     */
    public function loginAvailable()
    {
        if (!$this->supportsMethod('getLoginDrivers', [])) {
            return true;
        }
        $loginDrivers = $this->getLoginDrivers();
        return !empty($loginDrivers);
    }

    /**
     * Check if online payment is supported.
     *
     * @param array $functionConfig Function configuration values
     * @param array $params         An array of function-specific params (or null)
     *
     * @return boolean
     */
    protected function checkMethodmarkFeesAsPaid($functionConfig, $params)
    {
        if ($this->checkCapability('markFeesAsPaid', [$params ?: []])) {
            return ['function' => 'markFeesAsPaid'];
        }
        return false;
    }
}
