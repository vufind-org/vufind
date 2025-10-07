<?php

/**
 * Online payment controller feature trait.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2025.
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
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

declare(strict_types=1);

namespace VuFind\Controller\Feature;

use Laminas\Http\PhpEnvironment\Response;
use Laminas\View\Model\ViewModel;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Exception\PaymentException;
use VuFind\OnlinePayment\Handler\AbstractBase as BaseHandler;
use VuFind\OnlinePayment\OnlinePaymentEventTrait;
use VuFind\OnlinePayment\OnlinePaymentManager;

use function count;

/**
 * Online payment controller feature trait.
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait OnlinePaymentTrait
{
    use \VuFind\Log\LoggerAwareTrait;
    use OnlinePaymentEventTrait;

    /**
     * Support method for handling online payments.
     *
     * @param array     $patron Patron
     * @param array     $fines  List of fines
     * @param ViewModel $view   View
     *
     * @return ?Response Payment handling response, if any
     */
    protected function handleOnlinePayment(array $patron, array $fines, ViewModel $view): ?Response
    {
        $view->onlinePaymentEnabled = false;
        $sourceIls = $patron['__source'] ?? 'default';

        if (!($user = $this->getUser())) {
            $this->handleError('Could not get user');
            return null;
        }

        // Check if online payment configuration exists and is valid for the ILS driver
        $onlinePaymentManager = $this->serviceLocator->get(OnlinePaymentManager::class);
        $paymentConfig = $onlinePaymentManager->getAndValidateOnlinePaymentConfig($patron);
        if (!$paymentConfig) {
            $this->handleDebugMsg("No online payment ILS configuration for $sourceIls");
            return null;
        }

        $selectFees = $paymentConfig['selectFines'] ?? false;
        $pay = $this->formWasSubmitted('pay-confirm');
        $selectedIds = ($selectFees && $pay)
            ? $this->getRequest()->getPost()->get('selectedIDS', [])
            : null;
        $paymentDetails = $onlinePaymentManager->getAndCheckOnlinePaymentDetails(
            $patron,
            $fines,
            $selectedIds
        );
        if ($selectedIds && !$paymentDetails['fines']) {
            $this->handleError("Fines to pay missing from ILS driver for $sourceIls");
            return null;
        }

        $view->onlinePayment = true;
        $view->paymentHandler = $onlinePaymentManager->getHandlerName($sourceIls);
        $view->serviceFee = $paymentConfig['serviceFee'] ?? 0;
        $view->minimumFee = $paymentConfig['minimumFee'] ?? 0;
        $view->payableOnline = $paymentDetails['amount'];
        $view->payableTotal = $paymentDetails['amount'] + $view->serviceFee;
        $view->payableOnlineCnt = count($paymentDetails['fines']);
        $view->nonPayableFines = count($fines) != count($paymentDetails['fines']);
        $view->registerPayment = false;
        $view->selectFees = $selectFees;

        $paymentService = $this->getDbService(\VuFind\Db\Service\PaymentServiceInterface::class);
        $lastPayment = null;
        $receiptEnabled = $paymentConfig['receipt'] ?? false;
        if ($receiptEnabled) {
            $lastPayment = $paymentService->getLastPaidPaymentForPatron($patron['cat_username']);
        }
        if (
            $lastPayment
            && $this->params()->fromQuery('paymentReceipt') === 'true'
        ) {
            $receipt = $this->serviceLocator->get(\VuFind\OnlinePayment\Receipt::class);
            $data = $receipt->createReceiptPDF($lastPayment, $paymentConfig);
            $response = $this->getResponse();
            if ($this->params()->fromQuery('html') === 'true') {
                $response->setContent($data['html']);
            } else {
                $response->getHeaders()->addHeaders([
                    'Content-Type' => 'application/pdf',
                    'Content-disposition' => 'inline; filename="' . addcslashes($data['filename'], '"') . '"',
                ]);
                $response->setContent($data['pdf']);
            }
            return $response;
        }
        $view->lastPayment = $lastPayment;

        $paymentInProgress = $paymentService->getPaidPaymentInProgressForPatron($patron['cat_username']);
        if (
            $pay
            && $paymentDetails['payable']
            && $paymentDetails['amount']
            && !$paymentInProgress
        ) {
            // Check CSRF:
            $csrfValidator = $this->serviceLocator->get(\VuFind\Validator\CsrfInterface::class);
            $csrf = $this->getRequest()->getPost()->get('csrf');
            if (!$csrfValidator->isValid($csrf)) {
                $this->flashMessenger()->addErrorMessage('Payment::error_payment_request_failed');
                return $this->redirect()->toRoute('myresearch-fines');
            }
            // After successful token verification, clear list to shrink session and
            // ensure that the form is not re-sent:
            $csrfValidator->trimTokenList(0);

            // Payment requested, do preliminary checks:
            if ($paymentInProgress) {
                $this->flashMessenger()->addErrorMessage('Payment::error_payment_request_failed');
                return $this->redirect()->toRoute('myresearch-fines');
            }
            if (
                (($paymentConfig['exactBalanceRequired'] ?? true)
                || !empty($paymentConfig['creditUnsupported']))
                && !$selectFees
                && $onlinePaymentManager->getStoredPayableAmount($patron) !== $paymentDetails['amount']
            ) {
                // Fines updated, redirect and show updated list.
                $this->flashMessenger()->addErrorMessage('Payment::error_fines_changed');
                return $this->redirect()->toRoute('myresearch-fines');
            }
            $returnUrl = $this->getServerUrl('myresearch-fines');
            // Include language in notify url because it's a back-channel request that
            // doesn't have access to user's session:
            $notifyUrl = $this->getServerUrl('home') . 'AJAX/onlinePaymentNotify?lng='
                . urlencode($this->getTranslatorLocale());

            // Start payment
            try {
                return $onlinePaymentManager->startPayment(
                    $returnUrl,
                    $notifyUrl,
                    $user,
                    $patron,
                    $paymentDetails['amount'],
                    $paymentDetails['fines'],
                    'local_payment_id'
                );
            } catch (PaymentException $e) {
                $this->flashMessenger()->addErrorMessage($e->getMessage());
            }
            // We should only end up here on error, but redirect always just in case
            // the payment handler somehow misbehaves:
            return $this->redirect()->toRoute('myresearch-fines');
        }

        // Now check for local payment identifier in the URL and process any payment handler response:
        $request = $this->getRequest();
        $localIdentifier = $request->getQuery()->get('local_payment_id');
        if (
            $localIdentifier
            && ($payment = $paymentService->getPaymentByLocalIdentifier($localIdentifier))
        ) {
            $this->handleDebugMsg('Online payment response handler called. Request: ' . (string)$request);
            $this->addPaymentEvent($payment, AuditEventSubtype::PaymentResponseHandler, 'Response handler called');

            if ($payment->isRegistered()) {
                // Already registered, treat as success:
                $this->flashMessenger()->addSuccessMessage('Payment::Payment Successful');
            } else {
                // Process payment response:
                try {
                    $result = $onlinePaymentManager->processPaymentHandlerResponse($payment, $request, false);
                    if (BaseHandler::PAYMENT_SUCCESS === $result['resultCode']) {
                        // Reload payment and check if registration is still pending:
                        $payment = $paymentService->getPaymentByLocalIdentifier($localIdentifier);
                        if ($payment?->isRegistrationNeeded()) {
                            // Display page with success message and register payment with ILS asynchronously:
                            $this->flashMessenger()->addSuccessMessage('Payment::Payment Successful');
                            $view->registerPaymentLocalIdentifier = $payment->getLocalIdentifier();
                            $this->addPaymentEvent(
                                $payment,
                                AuditEventSubtype::PaymentRegistration,
                                'Registration requested'
                            );
                        }
                    } elseif (BaseHandler::PAYMENT_CANCEL === $result['resultCode']) {
                        $this->flashMessenger()->addSuccessMessage('Payment::Payment Canceled');
                    } elseif (BaseHandler::PAYMENT_FAILURE === $result['resultCode']) {
                        $this->flashMessenger()->addErrorMessage('Payment::error_payment_request_failed');
                    }
                } catch (PaymentException $e) {
                    $this->handleError(
                        'Error processing payment handler response for ' . $payment->getSourceIls()
                        . ", payment $localIdentifier: " . (string)$e
                    );
                }
            }
        }

        if (!$view->registerPaymentLocalIdentifier) {
            if ($paymentInProgress) {
                $this->flashMessenger()->addErrorMessage('Payment::registration_failed');
            } else {
                // Check if payment is permitted:
                $allowPayment = $paymentDetails['payable'] && $paymentDetails['amount'];

                // Save current payable amount to session:
                $onlinePaymentManager->storePayableAmount($patron, $paymentDetails['amount']);

                if ($onlinePaymentManager->getAndClearPaymentSuccessFlag()) {
                    $this->flashMessenger()->addSuccessMessage('Payment::Payment Successful');
                }

                $view->onlinePaymentEnabled = $allowPayment;
                $view->selectedIds = $this->getRequest()->getPost()->get('selectedIDS', []);
                if (!empty($paymentDetails['reason'])) {
                    $view->nonPayableReason = $paymentDetails['reason'];
                } elseif ($this->formWasSubmitted('pay')) {
                    $view->setTemplate('myresearch/fines-confirm-pay.phtml');
                } else {
                    // Check for a started payment:
                    $view->startedPayment = $paymentService->getStartedPaymentForPatron(
                        $patron['cat_username'],
                        (int)($paymentConfig['paymentMaxDuration'] ?? 15)
                    );
                }
            }
        }
        return null;
    }

    /**
     * Make sure that logger is available.
     *
     * @return void
     */
    protected function ensureLogger(): void
    {
        if (null === $this->getLogger()) {
            $this->setLogger($this->serviceLocator->get(\VuFind\Log\Logger::class));
        }
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
        $this->ensureLogger();
        $this->logError($msg);
    }

    /**
     * Log a debug message.
     *
     * @param string $msg Debug message.
     *
     * @return void
     */
    protected function handleDebugMsg($msg)
    {
        $this->ensureLogger();
        $this->logger->debug($msg);
    }
}
