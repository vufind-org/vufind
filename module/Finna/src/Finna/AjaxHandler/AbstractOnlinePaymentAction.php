<?php
/**
 * Abstract base class for online payment handlers.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2018.
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
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use Finna\Db\Table\Transaction as TransactionTable;
use Finna\OnlinePayment\OnlinePayment;
use VuFind\Db\Table\UserCard as UserCardTable;
use VuFind\ILS\Connection;
use VuFind\Session\Settings as SessionSettings;
use Zend\Session\Container as SessionContainer;

/**
 * Abstract base class for online payment handlers.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractOnlinePaymentAction extends \VuFind\AjaxHandler\AbstractBase
    implements \Zend\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * ILS connection
     *
     * @var Connection
     */
    protected $ils;

    /**
     * Transaction table
     *
     * @var TransactionTable
     */
    protected $transactionTable;

    /**
     * UserCard table
     *
     * @var UserCardTable
     */
    protected $userCardTable;

    /**
     * Online payment manager
     *
     * @var OnlinePayment
     */
    protected $onlinePayment;

    /**
     * Online payment session
     *
     * @var SessionContainer
     */
    protected $onlinePaymentSession;

    /**
     * Constructor
     *
     * @param SessionSettings  $ss  Session settings
     * @param Connection       $ils ILS connection
     * @param TransactionTable $tt  Transaction table
     * @param UserCardTable    $uc  UserCard table
     * @param OnlinePayment    $op  Online payment manager
     * @param SessionContainer $os  Online payment session
     */
    public function __construct(SessionSettings $ss, Connection $ils,
        TransactionTable $tt, UserCardTable $uc, OnlinePayment $op,
        SessionContainer $os
    ) {
        $this->sessionSettings = $ss;
        $this->ils = $ils;
        $this->transactionTable = $tt;
        $this->userCardTable = $uc;
        $this->onlinePayment = $op;
        $this->onlinePaymentSession = $os;
    }

    /**
     * Process payment request.
     *
     * @param Zend\Http\Request $request Request
     *
     * @return array Associative array with keys
     *   - 'success' (boolean)
     *   - 'msg' (string) error message if payment could not be processed.
     */
    protected function processPayment(\Zend\Http\Request $request)
    {
        $params = array_merge(
            $request->getQuery()->toArray(),
            $request->getPost()->toArray()
        );

        if (!isset($params['driver'])) {
            $this->logError(
                'Error processing payment: missing parameter "driver" in response.'
            );
            return ['success' => false];
        }

        $driver = $params['driver'];

        $handler = $this->getOnlinePaymentHandler($driver);
        if (!$handler) {
            $this->logError(
                'Error processing payment: could not initialize payment'
                . " handler $driver"
            );
            return ['success' => false];
        }

        $params = $handler->getPaymentResponseParams($request);
        if (false === $params) {
            return ['success' => false];
        }
        $transactionId = $params['transaction'];

        if (!$t = $this->transactionTable->getTransaction($transactionId)) {
            $this->logError(
                "Error processing payment: transaction $transactionId not found"
            );
            return ['success' => false];
        }

        if (!$this->transactionTable->isTransactionInProgress($transactionId)) {
            $this->logger->info(
                "Processing payment: transaction $transactionId already processed."
            );
            return ['success' => true];
        }

        $driver = $t['driver'];

        $userCard = $this->userCardTable->select(
            ['user_id' => $t['user_id'], 'cat_username' => $t['cat_username']]
        )->current();

        if (!$userCard) {
            $this->logError(
                'Error processing transaction id ' . $t['id']
                . ': user card not found (cat_username: ' . $t['cat_username']
                . ', user id: ' . $t['user_id'] . ')'
            );
            return ['success' => false];
        }

        $patron = null;
        try {
            $patron = $this->ils->patronLogin(
                $userCard['cat_username'], $userCard->getCatPassword()
            );
        } catch (\Exception $e) {
            $this->logger->logException($e, new \Zend\Stdlib\Parameters());
        }

        // Process the payment request regardless of whether patron login succeeds to
        // update the status properly
        $res = $handler->processResponse($request);

        if (!$patron) {
            $this->logError(
                'Error processing transaction id ' . $t['id']
                . ': patronLogin error (cat_username: ' . $t['cat_username']
                . ', user id: ' . $t['user_id'] . ')'
            );

            $this->transactionTable->setTransactionRegistrationFailed(
                $t['transaction_id'], 'patronLogin error'
            );
            return ['success' => false];
        }

        if (!is_array($res) || empty($res['markFeesAsPaid'])) {
            return ['success' => false, 'msg' => $res];
        }

        $tId = $res['transactionId'];
        $paymentConfig = $this->ils->getConfig('onlinePayment', $patron);
        if ($paymentConfig['exactBalanceRequired'] ?? true) {
            try {
                $fines = $this->ils->getMyFines($patron);
                $finesAmount = $this->ils->getOnlinePayableAmount($patron, $fines);
            } catch (\Exception $e) {
                $this->logger->logException($e, new \Zend\Stdlib\Parameters());
                return ['success' => false];
            }

            // Check that payable sum has not been updated
            if ($finesAmount['payable']
                && !empty($finesAmount['amount']) && !empty($res['amount'])
                && $finesAmount['amount'] != $res['amount']
            ) {
                // Payable sum updated. Skip registration and inform user
                // that payment processing has been delayed.
                if (!$this->transactionTable->setTransactionFinesUpdated($tId)) {
                    $this->logError(
                        "Error updating transaction $transactionId"
                        . " status: payable sum updated"
                    );
                }
                return [
                    'success' => false,
                    'msg' => 'online_payment_registration_failed'
                ];
            }
        }

        try {
            $this->ils->markFeesAsPaid($patron, $res['amount'], $tId, $t['id']);
            if (!$this->transactionTable->setTransactionRegistered($tId)) {
                $this->logError(
                    "Error updating transaction $transactionId status: registered"
                );
            }
            $this->onlinePaymentSession->paymentOk = true;
        } catch (\Exception $e) {
            $this->logError(
                'Payment registration error (patron ' . $patron['id'] . '): '
                . $e->getMessage()
            );
            $this->logger->logException($e, new \Zend\Stdlib\Parameters());

            $result = $this->transactionTable->setTransactionRegistrationFailed(
                $tId, $e->getMessage()
            );
            if (!$result) {
                $this->logError(
                    "Error updating transaction $transactionId status: "
                    . 'registering failed'
                );
            }
            return ['success' => false, 'msg' => $e->getMessage()];
        }
        return ['success' => true];
    }

    /**
     * Return online payment handler.
     *
     * @param string $driver Patron MultiBackend ILS source
     *
     * @return mixed \Finna\OnlinePayment\BaseHandler or false on failure.
     */
    protected function getOnlinePaymentHandler($driver)
    {
        if (!$this->onlinePayment->isEnabled($driver)) {
            return false;
        }

        try {
            return $this->onlinePayment->getHandler($driver);
        } catch (\Exception $e) {
            $this->logError(
                "Error retrieving online payment handler for driver $driver"
                . ' (' . $e->getMessage() . ')'
            );
            return false;
        }
    }
}
