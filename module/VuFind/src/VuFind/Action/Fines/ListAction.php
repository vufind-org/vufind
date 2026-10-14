<?php

/**
 * Fines list action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2023-2026.
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
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Fines;

use GuzzleHttp\Psr7\Message;
use Laminas\Psr7Bridge\Psr7ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Exception\PaymentException;
use VuFind\OnlinePayment\Handler\AbstractBase as BaseHandler;

use function is_array;

/**
 * Fines list action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ListAction extends AbstractFinesAction
{
    /**
     * Display fines.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        // Handle legacy receipt links:
        if ($this->getQueryParam('paymentReceipt') === 'true') {
            return $this->getHelper(RedirectHelper::class)
                ->redirectToRoute($response, 'fines-receipt', queryParams: $request->getQueryParams());
        }

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->getHelper(LoginHelper::class)->catalogLogin($request, $response))) {
            if (!($patron instanceof ResponseInterface)) {
                throw new \Exception('Unexpected response from LoginHelper::catalogLogin');
            }
            return $patron;
        }

        $templateParams = $this->getTemplateParams($patron);

        if (
            ($localIdentifier = $this->getQueryParam('local_payment_id'))
            && $this->processPaymentHandlerResponse($localIdentifier)
        ) {
            $templateParams['registerPaymentLocalIdentifier'] = $localIdentifier;
        }

        $templateParams = $this->handleOnlinePayment($patron, $templateParams);

        return $this->renderTemplate($request, $response, $templateParams);
    }

    /**
     * Support method for handling online payments.
     *
     * @param array $patron         Patron
     * @param array $templateParams Template params
     *
     * @return array Updated template params
     */
    protected function handleOnlinePayment(array $patron, array $templateParams): array
    {
        $flashMessages = $this->getHelper(FlashMessagesHelper::class);

        $paymentDetails = $this->onlinePaymentManager->getAndCheckOnlinePaymentDetails(
            $patron,
            $templateParams['fines'],
            null
        );

        $paymentInProgress = $this->paymentService->getPaidPaymentInProgressForPatron($patron['cat_username']);
        if (!($templateParams['registerPaymentLocalIdentifier'] ?? false)) {
            if ($paymentInProgress) {
                $flashMessages->addErrorMessage('Payment::registration_failed');
            } else {
                // Check if payment is permitted:
                $allowPayment = $paymentDetails['payable'] && $paymentDetails['amount'];

                // Save current payable amount to session:
                $this->onlinePaymentManager->storePayableAmount($patron, $paymentDetails['amount']);

                if ($this->onlinePaymentManager->getAndClearPaymentSuccessFlag()) {
                    $flashMessages->addSuccessMessage('Payment::Payment Successful');
                }

                $templateParams['onlinePaymentEnabled'] = $allowPayment;
                $templateParams['selectedIds'] = (array)($this->getPostParam('selectedIDS', []));
                if ($reason = $paymentDetails['reason'] ?? null) {
                    $templateParams['nonPayableReason'] = $reason;
                }

                // Check for a started payment:
                $templateParams['startedPayment'] = $this->paymentService->getStartedPaymentForPatron(
                    $patron['cat_username'],
                    $templateParams['paymentMaxDuration']
                );
            }
        }
        return $templateParams;
    }

    /**
     * Process a payment handler response.
     *
     * @param string $localIdentifier Payment local identifier
     *
     * @return bool Is registration needed?
     */
    protected function processPaymentHandlerResponse(string $localIdentifier): bool
    {
        $flashMessages = $this->getHelper(FlashMessagesHelper::class);

        // Fetch payment by its local identifier:
        if ($payment = $this->paymentService->getPaymentByLocalIdentifier($localIdentifier)) {
            $this->debug('Online payment response handler called. Request: ' . Message::toString($this->request));
            $this->auditEventService
                ->addPaymentEvent($payment, AuditEventSubtype::PaymentResponseHandler, 'Response handler called');

            if ($payment->isRegistered()) {
                // Already registered, treat as success:
                $flashMessages->addSuccessMessage('Payment::Payment Successful');
                return false;
            }
            // Process payment response:
            try {
                $result = $this->onlinePaymentManager->processPaymentHandlerResponse(
                    $payment,
                    Psr7ServerRequest::toLaminas($this->request),
                    false
                );
                if (BaseHandler::PAYMENT_SUCCESS === $result['resultCode']) {
                    // Reload payment and check if registration is still pending:
                    $payment = $this->paymentService->getPaymentByLocalIdentifier($localIdentifier);
                    if ($payment?->isRegistrationNeeded()) {
                        // Display success message and request asynchronous registration with ILS:
                        $flashMessages->addSuccessMessage('Payment::Payment Successful');
                        $this->auditEventService->addPaymentEvent(
                            $payment,
                            AuditEventSubtype::PaymentRegistration,
                            'Registration requested'
                        );
                        return true;
                    }
                } elseif (BaseHandler::PAYMENT_CANCEL === $result['resultCode']) {
                    $flashMessages->addSuccessMessage('Payment::Payment Canceled');
                } elseif (BaseHandler::PAYMENT_FAILURE === $result['resultCode']) {
                    $flashMessages->addErrorMessage('Payment::error_payment_request_failed');
                }
            } catch (PaymentException $e) {
                $this->logError(
                    'Error processing payment handler response for ' . $payment->getSourceIls()
                    . ", payment $localIdentifier: " . (string)$e
                );
            }
        }
        return false;
    }
}
