<?php
/**
 * Catalog Connection Class
 *
 * This wrapper works with a driver class to pass information from the ILS to
 * VuFind.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2020.
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
        // Alma driver calls the ILS for getConfig. Avoid useless calls by checking
        // the sources beforehand.
        if ('getConfig' === $method && 'Holds' === ($params[0] ?? '')) {
            // Check source of record id vs. patron id
            list($recordSource) = explode('.', $params[1]['id'] ?? '');
            list($patronSource) = explode('.', $params[1]['patron']['id'] ?? '');
            if ($patronSource && $patronSource !== $recordSource) {
                return false;
            }
        }
        return parent::checkCapability($method, $params, $throw);
    }

    /**
     * Get holdings
     *
     * Retrieve holdings from ILS driver class and normalize result array if needed.
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
        $holdings['page'] = $finalOptions['page'];
        $holdings['itemLimit'] = $finalOptions['itemLimit'];
        return $holdings;
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
        $response = parent::checkMethodHolds($functionConfig, $params);

        if (isset($functionConfig['acceptTermsText'])) {
            $response['acceptTermsText'] = $this->getHelpText(
                $functionConfig['acceptTermsText']
            );
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
        $response = parent::checkMethodStorageRetrievalRequests(
            $functionConfig, $params
        );

        if (isset($functionConfig['acceptTermsText'])) {
            $response['acceptTermsText'] = $this->getHelpText(
                $functionConfig['acceptTermsText']
            );
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
        $response = parent::checkMethodILLRequests($functionConfig, $params);

        if (isset($functionConfig['acceptTermsText'])) {
            $response['acceptTermsText'] = $this->getHelpText(
                $functionConfig['acceptTermsText']
            );
        }

        return $response;
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
     * Check for Staff User Authorization Status
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports getting
     * staff user authorization status.
     *
     * @param array $functionConfig The configuration values
     * @param array $params         Patron data
     *
     * @return mixed On success, an associative array with specific function keys
     * and values for getting authorization status; on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodgetPatronStaffAuthorizationStatus(
        $functionConfig, $params
    ) {
        $capability = $this->checkCapability(
            'getPatronStaffAuthorizationStatus', [$params ?: []]
        );
        if ($capability) {
            return ['function' => 'getPatronStaffAuthorizationStatus'];
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
        if ($functionConfig['method'] === 'database') {
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
     * Check for changeRequestStatus
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports change of
     * request status.
     *
     * @param array $functionConfig The configuration values
     * @param array $params         Patron data
     *
     * @return mixed On success, array of configuration data; on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodchangeRequestStatus($functionConfig, $params)
    {
        if (!isset($functionConfig['method'])) {
            return false;
        }

        if ($this->checkCapability('changeRequestStatus', [$params ?: []])
        ) {
            return $functionConfig;
        }

        return false;
    }

    /**
     * Check for checkMethodupdateTransactionHistoryState
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports change of
     * the checkout history state.
     *
     * @param array $functionConfig The configuration values
     * @param array $params         Patron data
     *
     * @return mixed On success, array of configuration data; on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodupdateTransactionHistoryState($functionConfig,
        $params
    ) {
        if (!isset($functionConfig['method'])) {
            return false;
        }

        $capability = $this->checkCapability(
            'updateTransactionHistoryState', [$params ?: []]
        );
        return $capability ? $functionConfig : false;
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
        if ($functionConfig['method'] === 'database') {
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
     * Check for updateSmsNumber
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
    protected function checkMethodupdateSmsNumber($functionConfig, $params)
    {
        if (!isset($functionConfig['method'])) {
            return false;
        }
        if ($functionConfig['method'] == 'driver'
            && !empty($functionConfig['emailAddress'])
        ) {
            return $functionConfig;
        }
        if ($functionConfig['method'] == 'url' && !empty($functionConfig['url'])) {
            return $functionConfig;
        }
        if ($functionConfig['method'] == 'driver'
            && $this->checkCapability('updateSmsNumber', [$params ?: []])
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

    /**
     * Check if password recovery is supported.
     *
     * @param array $functionConfig Function configuration values
     * @param array $params         An array of function-specific params (or null)
     *
     * @return boolean
     */
    protected function checkMethodgetPasswordRecoveryToken($functionConfig, $params)
    {
        if ($this->checkCapability('getPasswordRecoveryToken', [$params ?: []])) {
            return $functionConfig;
        }
        return false;
    }

    /**
     * Check if password recovery is supported.
     *
     * @param array $functionConfig Function configuration values
     * @param array $params         An array of function-specific params (or null)
     *
     * @return boolean
     */
    protected function checkMethodrecoverPassword($functionConfig, $params)
    {
        if ($this->checkCapability('recoverPassword', [$params ?: []])) {
            return $functionConfig;
        }
        return false;
    }

    /**
     * Check if self-registration.
     *
     * @param array $functionConfig Function configuration values
     * @param array $params         An array of function-specific params (or null)
     *
     * @return boolean
     */
    protected function checkMethodregisterPatron($functionConfig, $params)
    {
        if ($this->checkCapability('registerPatron', [$params ?: []])) {
            if (isset($functionConfig['introductionText'])) {
                $functionConfig['introductionText'] = $this->getHelpText(
                    $functionConfig['introductionText']
                );
            }
            if (isset($functionConfig['registrationHelpText'])) {
                $functionConfig['registrationHelpText'] = $this->getHelpText(
                    $functionConfig['registrationHelpText']
                );
            }
            if (isset($functionConfig['termsUrl'])) {
                $functionConfig['termsUrl'] = $this->getHelpText(
                    $functionConfig['termsUrl']
                );
            }
            return $functionConfig;
        }
        return false;
    }
}
