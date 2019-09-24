<?php
/**
 * Advanced Dummy ILS Driver -- Returns sample values based on Solr index.
 *
 * Note that some sample values (holds, transactions, fines) are stored in
 * the session.  You can log out and log back in to get a different set of
 * values.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2007.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Greg Pendlebury <vufind-tech@lists.sourceforge.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace Finna\ILS\Driver;

use VuFind\Exception\ILS as ILSException;

/**
 * Advanced Dummy ILS Driver -- Returns sample values based on Solr index.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Greg Pendlebury <vufind-tech@lists.sourceforge.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Demo extends \VuFind\ILS\Driver\Demo
{
    /**
     * Public Function which retrieves renew, hold and cancel settings from the
     * driver ini file.
     *
     * @param string $function The name of the feature to be checked
     * @param array  $params   Optional feature-specific parameters (array)
     *
     * @return array An array with key-value pairs.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConfig($function, $params = null)
    {
        if ($function == 'onlinePayment') {
            $functionConfig = $this->config['OnlinePayment'] ?? [];
            if ($functionConfig) {
                $functionConfig['exactBalanceRequired'] = true;
            }
            return $functionConfig;
        }
        if ('getPasswordRecoveryToken' === $function
            || 'recoverPassword' === $function
        ) {
            return !empty($this->config['PasswordRecovery']['enabled'])
                ? $this->config['PasswordRecovery'] : false;
        }
        if ('changePickupLocation' === $function) {
            return ['method' => 'driver'];
        }

        return parent::getConfig($function, $params);
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's fines on success.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getMyFines($patron)
    {
        $fines = parent::getMyFines($patron);
        if (!empty($fines)) {
            $fines[0]['fine'] = 'Accrued Fine';
        }
        $fines = $this->markOnlinePayableFines($fines);
        $session = $this->getSession($patron['id'] ?? null);
        $session->fines = $fines;
        return $fines;
    }

    /**
     * Return total amount of fees that may be paid online.
     *
     * @param array $patron Patron
     * @param array $fines  Patron's fines
     *
     * @throws ILSException
     * @return array Associative array of payment info,
     * false if an ILSException occurred.
     */
    public function getOnlinePayableAmount($patron, $fines)
    {
        if (!empty($fines)) {
            $nonPayableReason = false;
            $amount = 0;
            foreach ($fines as $fine) {
                if (!$fine['payableOnline'] && !$fine['accruedFine']) {
                    $nonPayableReason
                        = 'online_payment_fines_contain_nonpayable_fees';
                } elseif ($fine['payableOnline']) {
                    $amount += $fine['balance'];
                }
            }
            $config = $this->getConfig('onlinePayment');
            if (!$nonPayableReason
                && isset($config['minimumFee']) && $amount < $config['minimumFee']
            ) {
                $nonPayableReason = 'online_payment_minimum_fee';
            }
            $res = ['payable' => empty($nonPayableReason), 'amount' => $amount];
            if ($nonPayableReason) {
                $res['reason'] = $nonPayableReason;
            }
            return $res;
        }
        return [
            'payable' => false,
            'amount' => 0,
            'reason' => 'online_payment_minimum_fee'
        ];
    }

    /**
     * Support method for getMyFines.
     *
     * Appends booleans 'accruedFine' and 'payableOnline' to a fine.
     *
     * @param array $fines Processed fines.
     *
     * @return array $fines Fines.
     */
    protected function markOnlinePayableFines($fines)
    {
        $accruedType = 'Accrued Fine';

        $config = isset($this->config['OnlinePayment'])
            ? $this->config['OnlinePayment'] : [];
        $nonPayable = $config['nonPayable'] ?? []
        ;
        $nonPayable[] = $accruedType;
        foreach ($fines as &$fine) {
            $payableOnline = true;
            if (isset($fine['fine'])) {
                if (in_array($fine['fine'], $nonPayable)) {
                    $payableOnline = false;
                }
            }
            $fine['accruedFine'] = ($fine['fine'] === $accruedType);
            $fine['payableOnline'] = $payableOnline;
        }

        return $fines;
    }

    /**
     * Mark fees as paid.
     *
     * This is called after a successful online payment.
     *
     * @param array  $patron            Patron
     * @param int    $amount            Amount to be registered as paid
     * @param string $transactionId     Transaction ID
     * @param int    $transactionNumber Internal transaction number
     *
     * @throws ILSException
     * @return boolean success
     */
    public function markFeesAsPaid($patron, $amount, $transactionId,
        $transactionNumber
    ) {
        if ((rand() % 10) > 8) {
            throw new ILSException('online_payment_registration_failed');
        }

        $session = $this->getSession($patron['id'] ?? null);
        if (isset($session->fines)) {
            foreach ($session->fines as $key => $fine) {
                if ($fine['payableOnline']) {
                    unset($session->fines[$key]);
                }
            }
        }

        return true;
    }

    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver.  Required method for any smart drivers.
     *
     * @param string $method The name of the called method.
     * @param array  $params Array of passed parameters
     *
     * @return bool True if the method can be called with the given parameters,
     * false otherwise.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function supportsMethod($method, $params)
    {
        if ($method == 'markFeesAsPaid') {
            $required = [
                'currency', 'enabled'
            ];

            foreach ($required as $req) {
                if (!isset($this->config['OnlinePayment'][$req])
                    || empty($this->config['OnlinePayment'][$req])
                ) {
                    return false;
                }
            }

            if (!$this->config['OnlinePayment']['enabled']) {
                return false;
            }

            return true;
        }
        return is_callable([$this, $method]);
    }

    /**
     * Get a password recovery token for a user
     *
     * @param array $params Required params such as cat_username and email
     *
     * @return array Associative array of the results
     */
    public function getPasswordRecoveryToken($params)
    {
        if ((rand() % 10) > 8) {
            throw new ILSException('ils_connection_failed');
        }
        if ((rand() % 10) > 8) {
            return [
                'success' => false,
                'error' => 'Simulating failure'
            ];
        }
        $session = $this->getSession();
        $session->passwordRecoveryToken = md5(rand());
        return [
            'success' => true,
            'token' => $session->passwordRecoveryToken
        ];
    }

    /**
     * Recover user's password with a token from getPasswordRecoveryToken
     *
     * @param array $params Required params such as cat_username, token and new
     * password
     *
     * @return array Associative array of the results
     */
    public function recoverPassword($params)
    {
        $session = $this->getSession();
        if ($session->passwordRecoveryToken != $params['token']) {
            return [
                'success' => false,
                'error' => 'Recovery token mismatch'
            ];
        }
        return [
            'success' => true
        ];
    }

    /**
     * Change pickup location
     *
     * This is responsible for changing the pickup location of a hold
     *
     * @param string $patron      Patron array
     * @param string $holdDetails The request details
     *
     * @return array Associative array of the results
     */
    public function changePickupLocation($patron, $holdDetails)
    {
        $requestId = $holdDetails['requestId'];
        $pickUpLocation = $holdDetails['pickupLocationId'];

        if (!$this->pickUpLocationIsValid($pickUpLocation, $patron, $holdDetails)) {
            return $this->holdError('hold_invalid_pickup');
        }

        $session = $this->getSession();
        if (!isset($session->holds)) {
            return $this->holdError('ils_connection_failed');
        }
        foreach ($session->holds as &$hold) {
            if (isset($hold['requestId']) && $hold['requestId'] == $requestId) {
                $hold['location'] = $pickUpLocation;
                return ['success' => true];
            }
        }
        return $this->holdError('hold_error_failed');
    }

    /**
     * Generate a list of holds, storage retrieval requests or ILL requests.
     *
     * @param string $requestType Request type (Holds, StorageRetrievalRequests or
     * ILLRequests)
     *
     * @return ArrayObject List of requests
     */
    protected function createRequestList($requestType)
    {
        $list = parent::createRequestList($requestType);
        if ('Holds' === $requestType) {
            $i = 0;
            foreach ($list as $key => $item) {
                $list[$key]['requestId'] = ++$i;
                $list[$key]['is_editable'] = empty($item['available'])
                    && empty($item['inTransit']);
                if (!isset($item['available'])) {
                    $list[$key]['available'] = false;
                }
            }
        }
        return $list;
    }

    /**
     * Return a hold error message
     *
     * @param string $message Error message
     *
     * @return array
     */
    protected function holdError($message)
    {
        return [
            'success' => false,
            'sysMessage' => $message
        ];
    }

    /**
     * Is the selected pickup location valid for the hold?
     *
     * @param string $pickUpLocation Selected pickup location
     * @param array  $patron         Patron information returned by the patronLogin
     * method.
     * @param array  $holdDetails    Details of hold being placed
     *
     * @return bool
     */
    protected function pickUpLocationIsValid($pickUpLocation, $patron, $holdDetails)
    {
        $pickUpLibs = $this->getPickUpLocations($patron, $holdDetails);
        foreach ($pickUpLibs as $location) {
            if ($location['locationID'] == $pickUpLocation) {
                return true;
            }
        }
        return false;
    }
}
