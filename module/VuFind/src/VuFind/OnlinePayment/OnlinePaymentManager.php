<?php

/**
 * Online payment manager
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
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

declare(strict_types=1);

namespace VuFind\OnlinePayment;

use Laminas\Http\PhpEnvironment\Response;
use Laminas\Session\Container as SessionContainer;
use Laminas\Session\SessionManager;
use Laminas\Stdlib\RequestInterface;
use Psr\Log\LoggerAwareInterface;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PaymentFeeServiceInterface;
use VuFind\Db\Service\PaymentServiceInterface;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\PaymentStatus;
use VuFind\Exception\PaymentException;
use VuFind\ILS\Connection;
use VuFind\Log\LoggerAwareTrait;
use VuFind\OnlinePayment\Handler\AbstractBase as BaseHandler;
use VuFind\OnlinePayment\Handler\HandlerInterface;
use VuFind\OnlinePayment\Handler\PluginManager as HandlerPluginManager;

/**
 * Online payment manager
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class OnlinePaymentManager implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    use OnlinePaymentEventTrait;

    /**
     * Constructor.
     *
     * @param HandlerPluginManager       $handlerManager    Handler plugin manager
     * @param Connection                 $ils               ILS Connection
     * @param ILSAuthenticator           $ilsAuthenticator  ILS authenticator
     * @param PaymentServiceInterface    $paymentService    Payment database service
     * @param PaymentFeeServiceInterface $paymentFeeService Payment fee database service
     * @param UserCardServiceInterface   $userCardService   User card database service
     * @param AuditEventServiceInterface $auditEventService Audit event database service
     * @param Receipt                    $receipt           Receipt handler
     * @param SessionManager             $sessionManager    Session manager
     * @param bool                       $testHandlerUsable Is the test payment handler available?
     */
    public function __construct(
        protected HandlerPluginManager $handlerManager,
        protected Connection $ils,
        protected ILSAuthenticator $ilsAuthenticator,
        protected PaymentServiceInterface $paymentService,
        protected PaymentFeeServiceInterface $paymentFeeService,
        protected UserCardServiceInterface $userCardService,
        AuditEventServiceInterface $auditEventService,
        protected Receipt $receipt,
        protected SessionManager $sessionManager,
        protected bool $testHandlerUsable
    ) {
        $this->auditEventService = $auditEventService;
    }

    /**
     * Get online payment handler
     *
     * @param string $sourceIls Source ILS
     *
     * @return HandlerInterface
     *
     * @throws PaymentException
     */
    public function getHandler(string $sourceIls): HandlerInterface
    {
        if (!($handlerName = $this->getHandlerName($sourceIls))) {
            throw new PaymentException("Online payment handler not defined for '$sourceIls'");
        }
        if (!$this->handlerManager->has($handlerName)) {
            throw new PaymentException("Online payment handler '$handlerName' not found for '$sourceIls'");
        }

        $handler = $this->handlerManager->get($handlerName);
        $handler->init($this->getOnlinePaymentConfig($sourceIls));
        return $handler;
    }

    /**
     * Get online payment handler name.
     *
     * @param string $sourceIls Source ILS
     *
     * @return string
     */
    public function getHandlerName(string $sourceIls): string
    {
        if ($config = $this->getOnlinePaymentConfig($sourceIls)) {
            return $config['handler'] ?? '';
        }
        return '';
    }

    /**
     * Check if online payment is enabled for an ILS.
     *
     * @param string $sourceIls Source ILS
     *
     * @return bool
     */
    public function isEnabled(string $sourceIls): bool
    {
        $config = $this->getOnlinePaymentConfig($sourceIls);
        return (bool)($config['enabled'] ?? false);
    }

    /**
     * Start payment.
     *
     * Starts payment with the payment service and redirects the user to the service.
     *
     * @param string              $returnBaseUrl Return URL
     * @param string              $notifyBaseUrl Notify URL
     * @param UserEntityInterface $user          User
     * @param array               $patron        Patron information
     * @param int                 $amount        Amount (excluding service fee)
     * @param array               $fines         Fines data
     * @param string              $paymentParam  Payment status URL parameter
     *
     * @return Response
     *
     * @throws PaymentException
     */
    public function startPayment(
        string $returnBaseUrl,
        string $notifyBaseUrl,
        UserEntityInterface $user,
        array $patron,
        int $amount,
        array $fines,
        string $paymentParam
    ): Response {
        $sourceIls = $this->getSourceIls($patron);
        $paymentHandler = $this->getHandler($sourceIls);

        $patronProfile = array_merge(
            $patron,
            $this->ils->getMyProfile($patron)
        );

        return $paymentHandler->startPayment(
            $returnBaseUrl,
            $notifyBaseUrl,
            $user,
            $patronProfile,
            $amount,
            $fines,
            $paymentParam
        );
    }

    /**
     * Process a response from a payment handler
     *
     * @param PaymentEntityInterface $payment    Payment
     * @param RequestInterface       $request    Request
     * @param bool                   $fromNotify Is the request from notification handler?
     *
     * @return array Associative array with result and markedAsPaid
     *
     * @throws PaymentException
     */
    public function processPaymentHandlerResponse(
        PaymentEntityInterface $payment,
        RequestInterface $request,
        bool $fromNotify
    ): array {
        $paymentHandler = $this->getHandler($payment->getSourceIls());
        $resultCode = $paymentHandler->processPaymentResponse($payment, $request);
        $linkType = $fromNotify ? 'notify handler' : 'backlink';
        $this->debug("Online payment $linkType for " . $payment->getLocalIdentifier() . " result: $resultCode");
        $markedAsPaid = false;
        switch ($resultCode) {
            case BaseHandler::PAYMENT_SUCCESS:
                if ($markedAsPaid = $payment->isInProgress()) {
                    $payment->applyPaymentPaidStatus();
                    $this->persistEntityWithAuditEvent($payment, AuditEventSubtype::Payment, 'Payment marked as paid');
                }
                break;
            case BaseHandler::PAYMENT_CANCEL:
                if (PaymentStatus::InProgress === $payment->getStatus()) {
                    $payment->applyCanceledStatus();
                    $this->persistEntityWithAuditEvent(
                        $payment,
                        AuditEventSubtype::Payment,
                        'Payment marked as canceled'
                    );
                }
                break;
            case BaseHandler::PAYMENT_FAILURE:
                if (PaymentStatus::InProgress === $payment->getStatus()) {
                    $payment->applyPaymentFailedStatus();
                    $this
                        ->persistEntityWithAuditEvent($payment, AuditEventSubtype::Payment, 'Payment marked as failed');
                }
                break;
            case BaseHandler::PAYMENT_PENDING:
                $this->addPaymentEvent($payment, AuditEventSubtype::Payment, 'Payment still pending');
                break;
        }

        // Send receipt if the payment was marked as paid and receipt is enabled:
        if (
            $markedAsPaid
            && ($patron = $this->getPatronForPayment($payment))
            && ($paymentConfig = $this->getOnlinePaymentConfig($this->getSourceIls($patron)))
            && ($paymentConfig['receipt'] ?? false)
        ) {
            try {
                // Get full profile for receipt:
                $patronProfile = array_merge(
                    $patron,
                    $this->ils->getMyProfile($patron)
                );
                $res = $this->receipt->sendEmail($payment->getUser(), $patronProfile, $payment, $paymentConfig);
                $this->addPaymentEvent(
                    $payment,
                    AuditEventSubtype::PaymentReceipt,
                    $res ? 'Receipt sent' : 'Receipt not sent (no email address)'
                );
            } catch (\Exception $e) {
                $this->logError(
                    'Failed to send email receipt for ' . $payment->getLocalIdentifier() . ': ' . (string)$e
                );
                $this->addPaymentEvent(
                    $payment,
                    AuditEventSubtype::PaymentReceipt,
                    'Sending of receipt failed',
                    ['error' => (string)$e]
                );
            }
        }

        return compact('resultCode', 'markedAsPaid');
    }

    /**
     * Find patron for a payment
     *
     * @param PaymentEntityInterface $payment Payment
     *
     * @return array Patron, or null on failure
     */
    public function getPatronForPayment(PaymentEntityInterface $payment): ?array
    {
        if (!($user = $payment->getUser())) {
            return null;
        }

        // Check if user's current credentials match (typical case):
        $catPassword = $this->ilsAuthenticator->getCatPasswordForUser($user);
        if (
            mb_strtolower($user->getCatUsername(), 'UTF-8') === mb_strtolower($payment->getCatUsername(), 'UTF-8')
            && ($patron = $this->ils->patronLogin($user->getCatUsername(), $catPassword))
        ) {
            // Success!
            return $patron;
        }

        // Check for a matching library card:
        $cards = $this->userCardService->getLibraryCards($user, null, $payment->getCatUsername());

        // Make sure to try all cards with a matching user name:
        foreach ($cards as $card) {
            $userCopy = clone $user;
            // Note: these changes are not persisted, so there's no harm in setting them here:
            $userCopy->setCatUsername($card->getCatUsername());
            $userCopy->setRawCatPassword($card->getRawCatPassword());
            $userCopy->setCatPassEnc($card->getCatPassEnc());
            $catPassword = $this->ilsAuthenticator->getCatPasswordForUser($userCopy);

            try {
                if ($patron = $this->ils->patronLogin($userCopy->getCatUsername(), $catPassword)) {
                    // Success!
                    return $patron;
                }
            } catch (\Exception $e) {
                $this->logError('Patron login error: ' . $e->getMessage());
                $this->logException($e);
            }
        }
        return null;
    }

    /**
     * Register the given payment with ILS
     *
     * @param PaymentEntityInterface $payment Payment
     *
     * @return bool
     */
    public function registerPaymentWithILS(PaymentEntityInterface $payment): bool
    {
        if (!($patron = $this->getPatronForPayment($payment))) {
            $this->logError(
                'Error processing payment id ' . $payment->getId()
                . ': patronLogin error (cat_username: ' . $payment->getCatUsername()
                . ', user id: ' . $payment->getUser()->getId() . ')'
            );

            $payment->applyRegistrationFailedStatus('patron login error');
            $this->persistEntityWithAuditEvent($payment, AuditEventSubtype::PaymentRegistration, 'Patron login failed');
            return false;
        }

        $result = $this->registerPaymentForPatron($payment, $patron);
        if ($result) {
            $this->storePaymentSuccessFlag();
        }
        return $result;
    }

    /**
     * Return details on fees payable online.
     *
     * @param array  $patron          Patron
     * @param array  $fines           Patron's fines
     * @param ?array $selectedFineIds Selected fines
     *
     * @throws ILSException
     * @return array Associative array of payment details
     */
    public function getAndCheckOnlinePaymentDetails(array $patron, array $fines, ?array $selectedFineIds): array
    {
        if (!$fines) {
            return [
                'payable' => false,
                'amount' => 0,
                'fines' => [],
                'reason' => 'Payment::minimum_payment',
            ];
        }
        $details = $this->ils->getOnlinePaymentDetails($patron, $fines, $selectedFineIds);
        // Check minimum payment:
        if ($details['payable']) {
            $sourceIls = $this->getSourceIls($patron);
            $paymentConfig = $this->getOnlinePaymentConfig($sourceIls);
            $serviceFee = $paymentConfig['serviceFee'] ?? 0;
            $minimumFee = $paymentConfig['minimumFee'] ?? 0;
            if ($details['amount'] + $serviceFee < $minimumFee) {
                $details['payable'] = false;
                $details['reason'] = 'Payment::minimum_payment';
            }
            // Check that DevTools module is available when using the Test handler:
            if ($this->getHandlerName($sourceIls) === 'Test') {
                if (!$this->testHandlerUsable) {
                    $details['payable'] = false;
                    $details['reason'] = 'Test handler not available (VuFindDevTools module not loaded)';
                }
            }
        }
        return $details;
    }

    /**
     * Register a payment with ILS for the given patron
     *
     * @param PaymentEntityInterface $payment Payment
     * @param array                  $patron  Patron information
     *
     * @return bool
     */
    public function registerPaymentForPatron(PaymentEntityInterface $payment, array $patron): bool
    {
        // Check that registration is not already in progress (i.e. registration started within 120 seconds).
        // Refresh the entity to ensure we don't have stale data:
        $this->paymentService->refreshEntity($payment);
        if ($payment->isRegistrationInProgress()) {
            $this->debug(
                '    Payment ' . $payment->getLocalIdentifier() . ' already being registered since '
                . ($payment->getRegistrationStartDate()?->format('Y-m-d H:i:s') ?? '[date missing]')
            );
            $this->addPaymentEvent(
                $payment,
                AuditEventSubtype::PaymentRegistration,
                'Payment already being registered'
            );
            return false;
        }

        $payment->applyRegistrationStartedStatus();
        $this->persistEntityWithAuditEvent($payment, AuditEventSubtype::PaymentRegistration, 'Started registration');

        $paymentConfig = $this->ils->getConfig('OnlinePayment', $patron);
        $fineIds = $this->paymentFeeService->getFineIdsForPayment($payment);

        if (
            ($paymentConfig['exactBalanceRequired'] ?? true)
            || !empty($paymentConfig['creditUnsupported'])
        ) {
            try {
                $fines = $this->ils->getMyFines($patron);
                // Filter by fines selected for the payment if fineId field is available:
                $paymentDetails = $this->getAndCheckOnlinePaymentDetails(
                    $patron,
                    $fines,
                    $fineIds ?: null
                );
            } catch (\Exception $e) {
                $this->logException($e);
                $payment->applyRegistrationFailedStatus('Failed to process fine details');
                $this->persistEntityWithAuditEvent(
                    $payment,
                    AuditEventSubtype::PaymentRegistration,
                    'Registration failed: could not process fine details',
                    ['error' => (string)$e]
                );
                return false;
            }

            // Check that payable sum has not been updated if exact balance is required
            $exact = $paymentConfig['exactBalanceRequired'] ?? true;
            $noCredit = $exact || !empty($paymentConfig['creditUnsupported']);
            if (
                $paymentDetails['payable'] && !empty($paymentDetails['amount'])
                && (($exact && $payment->getAmount() != $paymentDetails['amount'])
                || ($noCredit && $payment->getAmount() > $paymentDetails['amount']))
            ) {
                // Payable sum updated. Skip registration and inform user
                // that payment processing has been delayed.
                $this->logError(
                    'Payment ' . $payment->getLocalIdentifier() . ': payable sum updated.'
                    . ' Paid amount: ' . $payment->getAmount() . ', payable: '
                    . var_export($paymentDetails, true)
                );
                $payment->applyFinesUpdatedStatus();
                $this->persistEntityWithAuditEvent(
                    $payment,
                    AuditEventSubtype::PaymentRegistration,
                    'Registration failed: fines updated'
                );
                return false;
            }
        }

        try {
            $this->debug('Payment ' . $payment->getLocalIdentifier() . ': start marking fees as paid.');
            $res = $this->ils->registerPayment(
                $patron,
                $payment->getAmount(),
                $payment->getLocalIdentifier(),
                $payment->getRemoteIdentifier(),
                $payment->getId(),
                ($paymentConfig['selectFines'] ?? false) ? $fineIds : null
            );
            $this->debug(
                'Payment ' . $payment->getLocalIdentifier() . ': done marking fees as paid, result: '
                . var_export($res, true)
            );
            if (!$res['success']) {
                $this->logError(
                    'Payment registration error (patron ' . $patron['id'] . '): '
                    . 'registerPayment failed: ' . ($res['reason'] ?? 'no error information')
                );
                if ('Payment::error_fines_changed' === $res['reason']) {
                    $payment->applyFinesUpdatedStatus();
                    $this->persistEntityWithAuditEvent(
                        $payment,
                        AuditEventSubtype::PaymentRegistration,
                        'Registration failed: fines updated'
                    );
                } else {
                    $error = $res['reason'] ?? 'no error information';
                    $payment->applyRegistrationFailedStatus(
                        "Failed to mark fees paid: $error"
                    );
                    $this->persistEntityWithAuditEvent(
                        $payment,
                        AuditEventSubtype::PaymentRegistration,
                        "Registration failed: $error"
                    );
                }
                return false;
            }
            $payment->applyRegisteredStatus();
            $this->persistEntityWithAuditEvent(
                $payment,
                AuditEventSubtype::PaymentRegistration,
                'Successfully registered'
            );
            $this->debug("Registration of payment {$payment->getLocalIdentifier()} successful");
        } catch (\Exception $e) {
            $this->logError('Payment registration error (patron ' . $patron['id'] . '): ' . $e->getMessage());
            $this->logException($e);
            $payment->applyRegistrationFailedStatus($e->getMessage());
            $this->persistEntityWithAuditEvent(
                $payment,
                AuditEventSubtype::PaymentRegistration,
                'Registration failed',
                ['error' => $e->getMessage()]
            );
            return false;
        }
        return true;
    }

    /**
     * Get online payment configuration for an ILS.
     *
     * @param string $sourceIls Source ILS
     *
     * @return array
     */
    public function getOnlinePaymentConfig(string $sourceIls): array
    {
        // There are several instances where false could be returned instead of an array, so account for that:
        return ($this->ils->getConfig('OnlinePayment', ['__source' => $sourceIls]) ?? []) ?: [];
    }

    /**
     * Get and validate online payment configuration for an ILS patron.
     *
     * @param array $patron Patron
     *
     * @return array
     */
    public function getAndValidateOnlinePaymentConfig(array $patron): array
    {
        $sourceIls = $this->getSourceIls($patron);
        $paymentConfig = $this->getOnlinePaymentConfig($sourceIls);

        if (!($paymentConfig['enabled'] ?? false)) {
            return [];
        }

        // Check if online payment is enabled for the ILS driver
        if (!$this->ils->checkFunction('registerPayment', compact('patron'))) {
            $this->debug("registerPayment not available for $sourceIls");
            return [];
        }

        // Check that mandatory handler setting exists
        if (empty($paymentConfig['handler'])) {
            $this->logError("Mandatory setting 'handler' missing from ILS driver for $sourceIls");
            return [];
        }

        return $paymentConfig;
    }

    /**
     * Add a new payment and its fines to database.
     *
     * @param string              $localIdentifier  Local payment identifier
     * @param ?string             $remoteIdentifier Handler's payment identifier
     * @param UserEntityInterface $user             User
     * @param array               $patron           Patron
     * @param int                 $amount           Amount (excluding service fee)
     * @param string              $currencyCode     Currency code
     * @param int                 $serviceFee       Service fee
     * @param array               $fines            Fines data
     *
     * @return PaymentEntityInterface
     */
    public function createPaymentEntity(
        string $localIdentifier,
        ?string $remoteIdentifier,
        UserEntityInterface $user,
        array $patron,
        int $amount,
        string $currencyCode,
        int $serviceFee,
        array $fines
    ): PaymentEntityInterface {
        $this->paymentService->beginTransaction();
        try {
            $payment = $this->paymentService->createInProgressPayment()
                ->setLocalIdentifier($localIdentifier)
                ->setRemoteIdentifier($remoteIdentifier)
                ->setSourceIls($this->getSourceIls($patron))
                ->setUser($user)
                ->setCatUsername($patron['cat_username'])
                ->setAmount($amount)
                ->setCurrency($currencyCode)
                ->setServiceFee($serviceFee);
            $this->paymentService->persistEntity($payment);

            foreach ($fines as $fine) {
                // Sanitize fine strings
                $fee = $this->paymentFeeService->createEntity()
                    ->setPayment($payment)
                    ->setAmount($fine['balance'])
                    ->setTaxPercent($fine['taxPercent'] ?? 0)
                    ->setCurrency($currencyCode)
                    ->setType(iconv('UTF-8', 'UTF-8//IGNORE', $fine['fine'] ?? ''))
                    ->setDescription(iconv('UTF-8', 'UTF-8//IGNORE', $fine['description'] ?? ''))
                    ->setFineId((string)$fine['fineId'])
                    ->setOrganization(iconv('UTF-8', 'UTF-8//IGNORE', $fine['organization'] ?? ''))
                    ->setTitle(iconv('UTF-8', 'UTF-8//IGNORE', $fine['title'] ?? ''));
                $this->paymentFeeService->persistEntity($fee);
            }

            $this->addPaymentEvent($payment, AuditEventSubtype::Payment, 'Payment created');
        } catch (\Exception $e) {
            $this->paymentService->rollbackTransaction();
            throw $e;
        } finally {
            $this->paymentService->commitTransaction();
        }

        return $payment;
    }

    /**
     * Store total amount of fines in session for later checks.
     *
     * @param array $patron Patron
     * @param int   $amount Total payable amount excluding fees
     *
     * @return void
     */
    public function storePayableAmount(array $patron, int $amount): void
    {
        $session = $this->getOnlinePaymentSession();
        $session->catUsername = $patron['cat_username'];
        $session->amount = $amount;
    }

    /**
     * Get stored payable amount from session
     *
     * @param array $patron Patron
     *
     * @return int Payable amount or -1 if patron doesn't match
     */
    public function getStoredPayableAmount(array $patron): int
    {
        $session = $this->getOnlinePaymentSession();
        if (!$session) {
            $this->logError('PaymentSessionError: Session empty for patron: ' . json_encode($patron));
            return -1;
        }
        if ($session->catUsername !== $patron['cat_username']) {
            $this->logError(
                'PaymentSessionError: Patron cat_username does not match session: '
                . $patron['cat_username'] . ' != ' . $session->catUsername
            );
            return -1;
        }
        return $session->amount;
    }

    /**
     * Get any successful payment flag from session and clear the session
     *
     * @return bool
     */
    public function getAndClearPaymentSuccessFlag(): bool
    {
        $session = $this->getOnlinePaymentSession();
        $result = $session->paymentSuccessful === true;
        unset($session->paymentSuccessful);
        return $result;
    }

    /**
     * Persist a payment and add an audit event in the same transaction.
     *
     * @param PaymentEntityInterface $payment      Payment
     * @param AuditEventSubtype      $eventSubtype Audit event subtype
     * @param string                 $auditMessage Audit message
     * @param array                  $eventData    Any additional audit event data
     *
     * @return void
     */
    public function persistEntityWithAuditEvent(
        PaymentEntityInterface $payment,
        AuditEventSubtype $eventSubtype,
        string $auditMessage,
        array $eventData = []
    ): void {
        $this->paymentService->beginTransaction();
        try {
            $this->paymentService->persistEntity($payment);
            $this->auditEventService->addPaymentEvent($payment, $eventSubtype, $auditMessage, $eventData, 1);
        } catch (\Exception $e) {
            $this->paymentService->rollbackTransaction();
            throw $e;
        } finally {
            $this->paymentService->commitTransaction();
        }
    }

    /**
     * Get patron's source ILS
     *
     * @param array $patron Patron
     *
     * @return string
     */
    protected function getSourceIls(array $patron): string
    {
        return $patron['__source'] ?? 'default';
    }

    /**
     * Store a flag for successful payment in the session
     *
     * @return void
     */
    protected function storePaymentSuccessFlag(): void
    {
        $this->getOnlinePaymentSession()->paymentSuccessful = true;
    }

    /**
     * Get session for storing payment data.
     *
     * @return SessionContainer
     */
    protected function getOnlinePaymentSession(): SessionContainer
    {
        return new \Laminas\Session\Container('OnlinePayment', $this->sessionManager);
    }
}
