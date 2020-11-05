<?php
/**
 * Online payment controller trait.
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Leszek Manicki <leszek.z.manicki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace Finna\Controller;

use Laminas\Console\Console;

/**
 * Online payment controller trait.
 *
 * @category VuFind
 * @package  Controller
 * @author   Leszek Manicki <leszek.z.manicki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait FinnaOnlinePaymentControllerTrait
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Checks if the given list of fines is identical to the listing
     * preserved in the session variable.
     *
     * @param array $patron Patron.
     * @param int   $amount Total amount to pay without fees
     *
     * @return boolean updated
     */
    protected function checkIfFinesUpdated($patron, $amount)
    {
        $session = $this->getOnlinePaymentSession();

        if (!$session) {
            $this->logError(
                'PaymentSessionError: Session was empty for: '
                . json_encode($patron) . ' and amount was '
                . json_encode($amount)
            );
            return true;
        }

        $finesUpdated = false;
        $sessionId = $this->generateFingerprint($patron);

        if ($session->sessionId !== $sessionId) {
            $this->logError(
                'PaymentSessionError: Session id does not match for: '
                . json_encode($patron) . '. Old id / new id hashes = '
                . $session->sessionId . ' and ' . $sessionId
            );
            $finesUpdated = true;
        }
        if ($session->amount !== $amount) {
            $this->logError(
                'PaymentSessionError: Payment amount updated: '
                . $session->amount . ' and ' . $amount
            );
            $finesUpdated = true;
        }
        return $finesUpdated;
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
     * Return online payment handler.
     *
     * @param string $driver Patron MultiBackend ILS source
     *
     * @return mixed \Finna\OnlinePayment\BaseHandler or false on failure.
     */
    protected function getOnlinePaymentHandler($driver)
    {
        $onlinePayment = $this->serviceLocator
            ->get('Finna\OnlinePayment\OnlinePayment');
        if (!$onlinePayment->isEnabled($driver)) {
            return false;
        }

        try {
            return $onlinePayment->getHandler($driver);
        } catch (\Exception $e) {
            $this->handleError(
                "Error retrieving online payment handler for driver $driver"
                . ' (' . $e->getMessage() . ')'
            );
            return false;
        }
    }

    /**
     * Get session for storing payment data.
     *
     * @return SessionContainer
     */
    protected function getOnlinePaymentSession()
    {
        return $this->serviceLocator->get('Finna\OnlinePayment\Session');
    }

    /**
     * Support for handling online payments.
     *
     * @param array     $patron Patron
     * @param array     $fines  Listing of fines
     * @param ViewModel $view   View
     *
     * @return void
     */
    protected function handleOnlinePayment($patron, $fines, $view)
    {
        $view->onlinePaymentEnabled = false;
        $session = $this->getOnlinePaymentSession();

        $catalog = $this->getILS();

        // Check if online payment configuration exists for the ILS driver
        $paymentConfig = $catalog->getConfig('onlinePayment', $patron);
        if (empty($paymentConfig)) {
            return;
        }

        // Check if payment handler is configured in datasources.ini
        $onlinePayment = $this->serviceLocator
            ->get('Finna\OnlinePayment\OnlinePayment');
        if (!$onlinePayment->isEnabled($patron['source'])) {
            return;
        }

        // Check if online payment is enabled for the ILS driver
        if (!$catalog->checkFunction('markFeesAsPaid', compact('patron'))) {
            return;
        }

        // Check that mandatory settings exist
        if (!isset($paymentConfig['currency'])) {
            $this->handleError(
                "Mandatory setting 'currency' missing from ILS driver for"
                . " '{$patron['source']}'"
            );
            return false;
        }

        $payableOnline = $catalog->getOnlinePayableAmount($patron, $fines);

        // Check if there is a payment in progress
        // or if the user has unregistered payments
        $transactionMaxDuration
            = $paymentConfig['transactionMaxDuration']
            ?? 30
        ;

        $tr = $this->getTable('transaction');
        $paymentPermittedForUser = $tr->isPaymentPermitted(
            $patron['cat_username'], $transactionMaxDuration
        );

        if (!$paymentHandler = $this->getOnlinePaymentHandler($patron['source'])) {
            return;
        }

        $callback = function ($fine) {
            return $fine['payableOnline'];
        };
        $payableFines = array_filter($fines, $callback);

        $view->onlinePayment = true;
        $view->paymentHandler = $onlinePayment->getHandlerName($patron['source']);
        $view->transactionFee = $paymentConfig['transactionFee'] ?? 0;
        $view->minimumFee = $paymentConfig['minimumFee'] ?? 0;
        $view->payableOnline = $payableOnline['amount'];
        $view->payableTotal = $payableOnline['amount'] + $view->transactionFee;
        $view->payableOnlineCnt = count($payableFines);
        $view->nonPayableFines = count($fines) != count($payableFines);

        $paymentParam = 'payment';
        $request = $this->getRequest();
        $pay = $this->formWasSubmitted('pay-confirm');
        $payment = $request->getQuery()->get(
            $paymentParam, $request->getPost($paymentParam)
        );
        if ($pay && $session && $payableOnline
            && $payableOnline['payable'] && $payableOnline['amount']
        ) {
            // Payment started, check that fee list has not been updated
            if (($paymentConfig['exactBalanceRequired'] ?? true)
                && $this->checkIfFinesUpdated($patron, $payableOnline['amount'])
            ) {
                // Fines updated, redirect and show updated list.
                $session->payment_fines_changed = true;
                header("Location: " . $this->getServerUrl('myresearch-fines'));
                exit();
            }
            $finesUrl = $this->getServerUrl('myresearch-fines');
            $ajaxUrl = $this->getServerUrl('home') . 'AJAX';
            list($driver, ) = explode('.', $patron['cat_username'], 2);

            $user = $this->getUser();
            if (!$user) {
                return;
            }

            $patronProfile = array_merge(
                $patron,
                $catalog->getMyProfile($patron)
            );

            // Start payment
            $result = $paymentHandler->startPayment(
                $finesUrl,
                $ajaxUrl,
                $user,
                $patronProfile,
                $driver,
                $payableOnline['amount'],
                $view->transactionFee,
                $payableFines,
                $paymentConfig['currency'],
                $paymentParam
            );
            $this->flashMessenger()->addMessage(
                $result ? $result : 'online_payment_failed', 'error'
            );
            header("Location: " . $this->getServerUrl('myresearch-fines'));
            exit();
        } elseif ($payment) {
            // Payment response received.

            // AJAX/onlinePaymentNotify was called before the user returned to Finna.
            // Display success message and return since the transaction is already
            // processed.
            if (!$payableOnline) {
                $this->flashMessenger()->addMessage(
                    'online_payment_successful', 'success'
                );
                $view->paymentRegistered = true;
                return;
            }

            //  Display page and process via AJAX.
            $view->registerPayment = true;
            $view->registerPaymentParams
                = $this->getRequest()->getQuery()->toArray();
        } else {
            $allowPayment
                = $paymentPermittedForUser === true && $payableOnline
                && $payableOnline['payable'] && $payableOnline['amount'];

            // Display possible warning and store fines to session.
            $this->storeFines($patron, $payableOnline['amount']);
            $session = $this->getOnlinePaymentSession();
            $view->transactionId = $session->sessionId;

            if (!empty($session->payment_fines_changed)) {
                $view->paymentFinesChanged = true;
                $this->flashMessenger()->addMessage(
                    'online_payment_fines_changed', 'error'
                );
                unset($session->payment_fines_changed);
            } elseif (!empty($session->paymentOk)) {
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
                } elseif (!empty($payableOnline['reason'])) {
                    $view->nonPayableReason = $payableOnline['reason'];
                } elseif ($this->formWasSubmitted('pay')) {
                    $view->setTemplate(
                        'Helpers/OnlinePayment/terms-' . $view->paymentHandler
                        . '.phtml'
                    );
                }
            }
        }
    }

    /**
     * Store fines to session.
     *
     * @param object $patron Patron.
     * @param int    $amount Total amount to pay without fees
     *
     * @return void
     */
    protected function storeFines($patron, $amount)
    {
        $session = $this->getOnlinePaymentSession();
        $session->sessionId = $this->generateFingerprint($patron);
        $session->amount = $amount;
    }

    /**
     * Log error message.
     *
     * @param string $msg Error message.
     *
     * @return void
     */
    protected function handleError($msg)
    {
        $this->setLogger($this->serviceLocator->get(\VuFind\Log\Logger::class));
        $this->logError($msg);
    }

    /**
     * Log exception.
     *
     * @param Exception $e Exception
     *
     * @return void
     */
    protected function handleException($e)
    {
        $this->setLogger($this->serviceLocator->get(\VuFind\Log\Logger::class));
        if (!Console::isConsole()) {
            if (is_callable([$this->logger, 'logException'])) {
                $this->logger->logException($e, new \Laminas\Stdlib\Parameters());
            }
        } else {
            $this->logException($e);
        }
    }
}
