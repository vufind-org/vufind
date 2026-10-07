<?php

/**
 * Fines action.
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

namespace VuFind\Action\MyResearch;

use Exception;
use GuzzleHttp\Psr7\Message;
use Laminas\Psr7Bridge\Psr7Response;
use Laminas\Psr7Bridge\Psr7ServerRequest;
use Laminas\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\Auth\EmailAuthenticator;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Auth\UserSessionPersistenceInterface;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PaymentServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Exception\PaymentException;
use VuFind\Http\ServerUrlHelper;
use VuFind\ILS\Connection;
use VuFind\ILS\Logic\RecordsHelper;
use VuFind\ILS\Logic\SummaryTrait;
use VuFind\Log\LoggerAwareTrait;
use VuFind\Mailer\Mailer;
use VuFind\OnlinePayment\Handler\AbstractBase as BaseHandler;
use VuFind\OnlinePayment\OnlinePaymentManager;
use VuFind\OnlinePayment\Receipt;
use VuFind\RecordDriver\Missing as MissingRecord;
use VuFind\Service\CurrencyFormatter;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;
use VuFind\Validator\CsrfInterface;

use function count;
use function is_array;

/**
 * Fines action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class FinesAction extends AbstractMyResearchAction implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    use SummaryTrait;

    /**
     * Constructor.
     *
     * @param AuthManager                     $authManager          Authentication manager
     * @param FollowupHelper                  $followupHelper       Followup helper
     * @param EmailAuthenticator              $emailAuthenticator   Email authenticator
     * @param UserSessionPersistenceInterface $userSessionService   User session database service
     * @param AuditEventServiceInterface      $auditEventService    Audit event service
     * @param ServerUrlHelper                 $serverUrlHelper      Server URL helper
     * @param Mailer                          $mailer               Mailer
     * @param SessionManager                  $sessionManager       Session manager
     * @param Connection                      $ilsConnection        ILS connection
     * @param array                           $config               VuFind configuration
     * @param RecordsHelper                   $ilsRecordsHelper     ILS records helper
     * @param CurrencyFormatter               $currencyFormatter    Currency formatter
     * @param OnlinePaymentManager            $onlinePaymentManager Online payment manager
     * @param PaymentServiceInterface         $paymentService       Payment database service
     * @param Receipt                         $receipt              Receipt creator
     * @param CsrfInterface                   $csrf                 CSRF validator
     */
    public function __construct(
        AuthManager $authManager,
        FollowupHelper $followupHelper,
        EmailAuthenticator $emailAuthenticator,
        #[Autowire(container: DbServicePluginManager::class)]
        UserSessionPersistenceInterface $userSessionService,
        #[Autowire(container: DbServicePluginManager::class)]
        AuditEventServiceInterface $auditEventService,
        ServerUrlHelper $serverUrlHelper,
        Mailer $mailer,
        SessionManager $sessionManager,
        Connection $ilsConnection,
        #[Autowire(config: 'config')]
        array $config,
        protected RecordsHelper $ilsRecordsHelper,
        protected CurrencyFormatter $currencyFormatter,
        protected OnlinePaymentManager $onlinePaymentManager,
        #[Autowire(container: DbServicePluginManager::class)]
        protected PaymentServiceInterface $paymentService,
        protected Receipt $receipt,
        protected CsrfInterface $csrf,
    ) {
        parent::__construct(
            $authManager,
            $followupHelper,
            $emailAuthenticator,
            $userSessionService,
            $auditEventService,
            $serverUrlHelper,
            $mailer,
            $sessionManager,
            $ilsConnection,
            $config
        );
    }

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
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->getHelper(LoginHelper::class)->catalogLogin($request, $response))) {
            if (!($patron instanceof ResponseInterface)) {
                throw new \Exception('Unexpected response from LoginHelper::catalogLogin');
            }
            return $patron;
        }

        // Get fine details:
        $fines = $this->ilsConnection->getMyFines($patron);
        foreach ($this->ilsRecordsHelper->getDrivers($fines) as $i => $driver) {
            if (!($driver instanceof MissingRecord)) {
                $fines[$i]['driver'] = $driver;
                if (empty($fines[$i]['title'])) {
                    $fines[$i]['title'] = $driver->getTitle();
                }
            }
        }

        // Collect up to date stats for ajax account notifications:
        if (!empty($this->config['Authentication']['enableAjax'])) {
            $accountStatus = $this->getFineSummary($fines, $this->currencyFormatter);
        } else {
            $accountStatus = null;
        }

        // Handle online payment and return any response (redirect, receipt):
        $result = $this->handleOnlinePayment($patron, $fines, compact('fines', 'accountStatus'));
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        return $this->renderTemplate($request, $response, $result);
    }

    /**
     * Support method for handling online payments.
     *
     * @param array $patron         Patron
     * @param array $fines          List of fines
     * @param array $templateParams Template params
     *
     * @return ResponseInterface|array Payment handling response, or updated template params
     */
    protected function handleOnlinePayment(array $patron, array $fines, array $templateParams): ResponseInterface|array
    {
        $templateParams['onlinePaymentEnabled'] = false;
        $sourceIls = $patron['__source'] ?? 'default';

        if (!($user = $this->authManager->getUserObject())) {
            throw new Exception('Could not get user');
        }

        // Check if online payment configuration exists and is valid for the ILS driver
        $paymentConfig = $this->onlinePaymentManager->getAndValidateOnlinePaymentConfig($patron);
        if (!$paymentConfig) {
            $this->debug("No online payment ILS configuration for $sourceIls");
            return $templateParams;
        }

        $selectFees = $paymentConfig['selectFines'] ?? false;
        $pay = $this->getHelper(FormHelper::class)->formWasSubmitted($this->request, 'pay-confirm');
        $selectedIds = ($selectFees && $pay)
            ? (array)($this->getPostParam('selectedIDS', []))
            : null;
        $paymentDetails = $this->onlinePaymentManager->getAndCheckOnlinePaymentDetails(
            $patron,
            $fines,
            $selectedIds
        );
        if ($selectedIds && !$paymentDetails['fines']) {
            $this->logError("Fines to pay missing from ILS driver for $sourceIls");
            return $templateParams;
        }

        $templateParams['onlinePayment'] = true;
        $templateParams['paymentHandler'] = $this->onlinePaymentManager->getHandlerName($sourceIls);
        $templateParams['serviceFee'] = $paymentConfig['serviceFee'] ?? 0;
        $templateParams['minimumFee'] = $paymentConfig['minimumFee'] ?? 0;
        $templateParams['payableOnline'] = $paymentDetails['amount'];
        $templateParams['payableTotal'] = $paymentDetails['amount'] + $templateParams['serviceFee'];
        $templateParams['payableOnlineCnt'] = count($paymentDetails['fines']);
        $templateParams['nonPayableFines'] = count($fines) != count($paymentDetails['fines']);
        $templateParams['registerPayment'] = false;
        $templateParams['selectFees'] = $selectFees;

        $lastPayment = null;
        $receiptEnabled = $paymentConfig['receipt'] ?? false;
        if ($receiptEnabled) {
            $lastPayment = $this->paymentService->getLastPaidPaymentForPatron($patron['cat_username']);
        }
        if ($lastPayment && $this->getQueryParam('paymentReceipt') === 'true') {
            $data = $this->receipt->createReceiptPDF($lastPayment, $paymentConfig);
            if ($this->getQueryParam('html') === 'true') {
                $this->response->getBody()->write($data['html']);
                return $this->response;
            }
            $response = $this->response->withHeader('Content-Type', 'application/pdf')
                ->withHeader('Content-disposition', 'inline; filename="' . addcslashes($data['filename'], '"') . '"');
            $response->getBody()->write($data['pdf']);
            return $response;
        }
        $templateParams['lastPayment'] = $lastPayment;

        $flashMessages = $this->getHelper(FlashMessagesHelper::class);

        $paymentInProgress = $this->paymentService->getPaidPaymentInProgressForPatron($patron['cat_username']);
        if (
            $pay
            && $paymentDetails['payable']
            && $paymentDetails['amount']
            && !$paymentInProgress
        ) {
            // Check CSRF:
            if (!$this->csrf->isValid($this->getPostParam('csrf'))) {
                $flashMessages->addErrorMessage('Payment::error_payment_request_failed');
                return $templateParams;
            }
            // After successful token verification, clear list to shrink session and ensure that the form is not
            // re-sent:
            $this->csrf->trimTokenList(0);

            // Payment requested, do preliminary checks:
            if ($paymentInProgress) {
                $flashMessages->addErrorMessage('Payment::error_payment_request_failed');
                return $templateParams;
            }
            if (
                (($paymentConfig['exactBalanceRequired'] ?? true)
                || !empty($paymentConfig['creditUnsupported']))
                && !$selectFees
                && $this->onlinePaymentManager->getStoredPayableAmount($patron) !== $paymentDetails['amount']
            ) {
                // Fines updated, redirect and show updated list.
                $this->getHelper(FlashMessagesHelper::class)->addErrorMessage('Payment::error_fines_changed');
            }
            $returnUrl = $this->serverUrlHelper
                ->getUrlForPath($this->getRouteHelper()->getUrlFromRoute('myresearch-fines'));
            // Include language in notify url because it's a back-channel request that doesn't have access to user's
            // session:
            $notifyUrl = $this->serverUrlHelper->getUrlForPath(
                $this->getRouteHelper()->getUrlFromRoute(
                    'ajax-onlinepaymentnotify',
                    queryParams: ['lng' => $this->getTranslatorLocale()]
                )
            );

            // Start payment
            try {
                $response = $this->onlinePaymentManager->startPayment(
                    $returnUrl,
                    $notifyUrl,
                    $user,
                    $patron,
                    $paymentDetails['amount'],
                    $paymentDetails['fines'],
                    'local_payment_id'
                );
                return Psr7Response::fromLaminas($response);
            } catch (PaymentException $e) {
                $flashMessages->addErrorMessage($e->getMessage());
            }
            // We should only end up here on error:
            return $templateParams;
        }

        // Now check for local payment identifier in the URL and process any payment handler response:
        $localIdentifier = $this->getQueryParam('local_payment_id');
        if (
            $localIdentifier
            && ($payment = $this->paymentService->getPaymentByLocalIdentifier($localIdentifier))
        ) {
            $this->debug('Online payment response handler called. Request: ' . Message::toString($this->request));
            $this->auditEventService
                ->addPaymentEvent($payment, AuditEventSubtype::PaymentResponseHandler, 'Response handler called');

            if ($payment->isRegistered()) {
                // Already registered, treat as success:
                $flashMessages->addSuccessMessage('Payment::Payment Successful');
            } else {
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
                            // Display page with success message and register payment with ILS asynchronously:
                            $flashMessages->addSuccessMessage('Payment::Payment Successful');
                            $templateParams['registerPaymentLocalIdentifier'] = $payment->getLocalIdentifier();
                            $this->auditEventService->addPaymentEvent(
                                $payment,
                                AuditEventSubtype::PaymentRegistration,
                                'Registration requested'
                            );
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
        }

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
                } elseif ($this->getHelper(FormHelper::class)->formWasSubmitted($this->request, 'pay')) {
                    return $this->renderTemplate(
                        $this->request,
                        $this->response,
                        $templateParams,
                        'myresearch/fines-confirm-pay.phtml'
                    );
                }

                // Check for a started payment:
                $templateParams['startedPayment'] = $this->paymentService->getStartedPaymentForPatron(
                    $patron['cat_username'],
                    (int)($paymentConfig['paymentMaxDuration'] ?? 15)
                );
            }
        }
        return $templateParams;
    }
}
