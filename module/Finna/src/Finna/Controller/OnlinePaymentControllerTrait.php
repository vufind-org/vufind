<?php
/**
 * Online payment controller trait.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @package  Controller
 * @author   Leszek Manicki <leszek.z.manicki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Controller;
use Zend\Session\Container as SessionContainer;

/**
 * Online payment controller trait.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Leszek Manicki <leszek.z.manicki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
trait OnlinePaymentControllerTrait
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Checks if the given list of fines is identical to the listing
     * preserved in the session variable.
     *
     * @param object $patron Patron.
     * @param array  $fines  Listing of fines.
     *
     * @return boolean updated
     */
    protected function checkIfFinesUpdated($patron, $fines)
    {
        $session = $this->getOnlinePaymentSession();
        return !$session
            || ($session->sessionId !== $this->generateFingerprint($patron))
            || ($session->fines !== $this->generateFingerprint($fines))
        ;
    }

    /**
     * Utility function for calculating a fingerprint for a object.
     *
     * @param object $data Object
     *
     * @return string fingerprint
     */
    protected function generateFingerprint($data)
    {
        return md5(json_encode($data));
    }

    /**
     * Get session for storing payment data.
     *
     * @return SessionContainer
     */
    protected function getOnlinePaymentSession()
    {
        return new SessionContainer('OnlinePayment');
    }

    /**
     * Support for handling online payments.
     *
     * @param object    $patron Patron.
     * @param array     $fines  Listing of fines.
     * @param ViewModel $view   View
     *
     * @return void
     */
    protected function handleOnlinePayment($patron, $fines, $view)
    {
        $view->onlinePaymentEnabled = false;
        $session = $this->getOnlinePaymentSession();

        $catalog = $this->getILS();

        // Check if online payment configuration exists for ILS-driver
        $paymentConfig = $catalog->getConfig('onlinePayment');
        if (empty($paymentConfig)) {
            return;
        }

        // Check if payment handler is configured in datasources.ini
        $onlinePayment = $this->getServiceLocator()->get('Finna\OnlinePayment');
        if (!$onlinePayment->isEnabled($patron['source'])) {
            return;
        }

        // Check if online payment is enabled for ILS-driver
        if (!$catalog->checkFunction('markFeesAsPaid', $patron)) {
            return;
        }
        $payableOnline = $catalog->getOnlinePayableAmount($patron);

        // Check if there is a payment in progress
        // or if the user has unregistered payments
        $transactionMaxDuration
            = isset($paymentConfig['transactionMaxDuration'])
            ? $paymentConfig['transactionMaxDuration']
            : 30
        ;

        $tr = $this->getTable('transaction');
        $paymentPermittedForUser = $tr->isPaymentPermitted(
            $patron['cat_username'], $transactionMaxDuration
        );

        try {
            $paymentHandler = $onlinePayment->getHandler($patron['source']);
        } catch (\Exception $e) {
            return;
        }

        $f = function ($fine) {
            return $fine['payableOnline'];
        };

        $payableFines = array_filter($fines, $f);

        $view->onlinePayment = true;
        $view->paymentHandler = $onlinePayment->getHandlerName($patron['source']);
        $view->transactionFee = isset($paymentConfig['transactionFee'])
            ? $paymentConfig['transactionFee'] : 0;
        $view->minimumFee = isset($paymentConfig['minimumFee'])
            ? $paymentConfig['minimumFee'] : 0;
        $view->payableOnline = $payableOnline['amount'];
        $view->payableTotal = $payableOnline['amount'] + $view->transactionFee;
        $view->payableOnlineCnt = count($payableFines);
        $view->nonPayableFines = count($fines) != count($payableFines);

        $paymentParam = 'payment';
        $request = $this->getRequest();
        $pay = $request->getQuery()->get('pay', $request->getPost('pay'));
        $payment = $request->getQuery()->get(
            $paymentParam, $request->getPost($paymentParam)
        );
        if ($pay && $session && $payableOnline
            && $payableOnline['payable'] && $payableOnline['amount']
        ) {
            // Payment started, check that fee list has not been updated
            if ($this->checkIfFinesUpdated($patron, $fines)) {
                // Fines updated, redirect and show updated list.
                $session->payment_fines_changed = true;
                header("Location: " . $this->getServerUrl('myresearch-fines'));
                exit();
            }
            $finesUrl = $this->getServerUrl('myresearch-fines');
            $ajaxUrl = $this->getServerUrl('home') . 'AJAX';
            list($driver,) = explode('.', $patron['cat_username'], 2);

            $user = $this->getUser();
            if (!$user) {
                return;
            }

            // Start payment
            $paymentHandler->startPayment(
                $finesUrl,
                $ajaxUrl,
                $user->id,
                $patron['cat_username'],
                $driver,
                $payableOnline['amount'],
                $view->transactionFee,
                $payableFines,
                $paymentConfig['currency'],
                $paymentParam,
                'transaction'
            );
            exit();
        } else if ($payment) {
            // Payment response received. Display page and process via AJAX.
            $view->registerPayment = true;
            $view->registerPaymentParams
                = $this->getRequest()->getQuery()->toArray();
        } else {
            $allowPayment
                = $paymentPermittedForUser === true && $payableOnline
                && $payableOnline['payable'] && $payableOnline['amount']
            ;

            // Display possible warning and store fines to session.
            $this->storeFines($patron, $fines);
            $session = $this->getOnlinePaymentSession();
            $view->transactionId = $session->sessionId;

            if (!empty($session->payment_fines_changed)) {
                $view->paymentFinesChanged = true;
                $this->flashMessenger()->addMessage(
                    'online_payment_fines_changed', 'error'
                );
                unset($session->payment_fines_changed);
            } else if (!empty($session->paymentOk)) {
                $this->flashMessenger()->addMessage(
                    'online_payment_successful', 'success'
                );
                unset($session->paymentOk);
            } else {
                $view->onlinePaymentEnabled = $allowPayment;
                if ($paymentPermittedForUser !== true) {
                    $this->flashMessenger()->addMessage(
                        strip_tags($paymentPermittedForUser), 'error'
                    );
                } else if (!empty($payableOnline['reason'])) {
                    $view->nonPayableReason = $payableOnline['reason'];
                }
            }
        }
    }

    /**
     * Process payment request.
     *
     * @param array $params Key-value list of request variables.
     *
     * @return array Associative array with keys
     *   - 'success' (boolean)
     *   - 'msg' (string) error message if payment could not be processed.
     */
    protected function processPayment($params)
    {
        $this->setLogger($this->getServiceLocator()->get('VuFind\Logger'));

        $transactionId = $params['transaction'];

        $tr = $this->getTable('transaction');
        if (!$t = $tr->getTransaction($transactionId)) {
            $this->logError(
                "Error processing payment: transaction $transactionId not found"
            );
            return ['success' => false];
        }

        if (!$tr->isTransactionInProgress($transactionId)) {
            $this->logError(
                'Error processing payment: '
                . "transaction $transactionId already processed."
            );
            return ['success' => false];
        }

        $driver = $t['driver'];
        $onlinePayment = $this->getServiceLocator()->get('Finna\OnlinePayment');
        if (!$onlinePayment->isEnabled($driver)) {
            return ['success' => false];
        }

        try {
            $paymentHandler = $onlinePayment->getHandler($driver);
        } catch (\Exception $e) {
            return ['success' => false];
        }

        $patronId = $t->cat_username;
        $catalog = $this->getILS();

        if (!is_array($patron = $this->catalogLogin())) {
            $userTable = $this->getTable('User');
            $user
                = $userTable->select(
                    ['cat_username' => $t['cat_username'], 'id' => $t['user_id']]
                )->current();

            try {
                $patron = $catalog->patronLogin(
                    $user['cat_username'], $user['cat_password']
                );
            } catch (\Exception $e) {
                return ['success' => false];
            }
        }

        $res = $paymentHandler->processResponse($params);
        if (!is_array($res) || empty($res['markFeesAsPaid'])) {
            return ['success' => false, 'msg' => $res];
        }

        $tId = $res['transactionId'];
        try {
            $finesAmount = $catalog->getOnlinePayableAmount($patron);
        } catch (\Exception $e) {
            return ['success' => false];
        }
        $transactionTable = $this->getTable('transaction');

        // Check that payable sum has not been updated
        if ($finesAmount['payable']
            && !empty($finesAmount['amount']) && !empty($res['amount'])
            && $finesAmount['amount'] != $res['amount']
        ) {
            // Payable sum updated. Skip registration and inform user
            // that payment processing has been delayed.
            if (!$transactionTable->setTransactionFinesUpdated($tId)) {
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

        try {
            $catalog->markFeesAsPaid($patron, $res['amount']);
            if (!$transactionTable->setTransactionRegistered($tId)) {
                $this->logError(
                    "Error updating transaction $transactionId status: registered"
                );
            }
            $session = $this->getOnlinePaymentSession();
            $session->paymentOk = true;
        } catch (\Exception $e) {
            $this->logError(
                'SIP2 payment error (patron: '
                . $patron['id'] . ': ' . $e->getMessage()
            );
            if (!$transactionTable->setTransactionRegistrationFailed(
                $tId, $e->getMessage()
            )) {
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
     * Store fines to session.
     *
     * @param object $patron Patron.
     * @param array  $fines  Listing of fines.
     *
     * @return void
     */
    protected function storeFines($patron, $fines)
    {
        $session = $this->getOnlinePaymentSession();
        $session->sessionId = $this->generateFingerprint($patron);
        $session->fines = $this->generateFingerprint($fines);
    }
}
