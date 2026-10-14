<?php

/**
 * Fines payment action.
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

use Exception;
use Laminas\Psr7Bridge\Psr7Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PaymentServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Exception\PaymentException;
use VuFind\Http\ServerUrlHelper;
use VuFind\ILS\Connection;
use VuFind\ILS\Logic\RecordsHelper;
use VuFind\ILS\Logic\SummaryTrait;
use VuFind\Log\LoggerAwareTrait;
use VuFind\OnlinePayment\OnlinePaymentManager;
use VuFind\Service\CurrencyFormatter;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Validator\CsrfInterface;

use function is_array;

/**
 * Fines payment action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class PayAction extends AbstractFinesAction implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    use SummaryTrait;

    /**
     * Constructor.
     *
     * @param AuthManager                $authManager          Authentication manager
     * @param Connection                 $ilsConnection        ILS connection
     * @param RecordsHelper              $ilsRecordsHelper     ILS records helper
     * @param CurrencyFormatter          $currencyFormatter    Currency formatter
     * @param OnlinePaymentManager       $onlinePaymentManager Online payment manager
     * @param PaymentServiceInterface    $paymentService       Payment database service
     * @param AuditEventServiceInterface $auditEventService    Audit event database service
     * @param array                      $config               VuFind configuration
     * @param CsrfInterface              $csrf                 CSRF validator
     * @param ServerUrlHelper            $serverUrlHelper      Server URL helper
     */
    public function __construct(
        AuthManager $authManager,
        Connection $ilsConnection,
        RecordsHelper $ilsRecordsHelper,
        CurrencyFormatter $currencyFormatter,
        OnlinePaymentManager $onlinePaymentManager,
        #[Autowire(container: DbServicePluginManager::class)]
        PaymentServiceInterface $paymentService,
        #[Autowire(container: DbServicePluginManager::class)]
        AuditEventServiceInterface $auditEventService,
        #[Autowire(config: 'config')]
        array $config,
        protected CsrfInterface $csrf,
        protected ServerUrlHelper $serverUrlHelper,
    ) {
        parent::__construct(
            $authManager,
            $ilsConnection,
            $ilsRecordsHelper,
            $currencyFormatter,
            $onlinePaymentManager,
            $paymentService,
            $auditEventService,
            $config
        );
    }

    /**
     * Pay fines.
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

        $templateParams = $this->getTemplateParams($patron);

        // Handle online payment and return any response (redirect or confirmation):
        $formHelper = $this->getHelper(FormHelper::class);
        if ($formHelper->formWasSubmitted($this->request, 'reset')) {
            return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'fines-list');
        }
        if ($formHelper->formWasSubmitted($this->request, 'pay-confirm')) {
            $result = $this->handleOnlinePaymentRequest($response, $patron, $templateParams);
            if ($result instanceof ResponseInterface) {
                return $result;
            }
        }

        return $this->renderTemplate($request, $response, $templateParams);
    }

    /**
     * Support method for handling an online payment request.
     *
     * @param ResponseInterface $response       Response
     * @param array             $patron         Patron
     * @param array             $templateParams Template params
     *
     * @return ?ResponseInterface Payment handling response, if any
     */
    protected function handleOnlinePaymentRequest(
        ResponseInterface $response,
        array $patron,
        array $templateParams
    ): ?ResponseInterface {
        if (!($user = $this->authManager->getUserObject())) {
            throw new Exception('Could not get user');
        }

        $flashMessages = $this->getHelper(FlashMessagesHelper::class);

        $paymentInProgress = $this->paymentService->getPaidPaymentInProgressForPatron($patron['cat_username']);
        if ($templateParams['payable'] && $templateParams['payableOnline'] && !$paymentInProgress) {
            // Check CSRF:
            if (!$this->csrf->isValid($this->getPostParam('csrf'))) {
                $flashMessages->addErrorMessage('Payment::error_payment_request_failed');
                return null;
            }
            // After successful token verification, clear list to shrink session and ensure that the form is not
            // re-sent:
            $this->csrf->trimTokenList(0);

            // Payment requested, do preliminary checks:
            if ($paymentInProgress) {
                $flashMessages->addErrorMessage('Payment::error_payment_request_failed');
                return null;
            }
            if (
                ($templateParams['exactBalanceRequired'] || $templateParams['creditUnsupported'])
                && !$templateParams['selectFees']
                && $this->onlinePaymentManager->getStoredPayableAmount($patron) !== $templateParams['payableOnline']
            ) {
                // Fines updated, redirect and show updated list.
                $this->getHelper(FlashMessagesHelper::class)->addErrorMessage('Payment::error_fines_changed');
                return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'fines-list');
            }
            $returnUrl = $this->serverUrlHelper
                ->getUrlForPath($this->getRouteHelper()->getUrlFromRoute('fines-list'));
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
                    $templateParams['payableOnline'],
                    $templateParams['selectedFines'],
                    'local_payment_id'
                );
                return Psr7Response::fromLaminas($response);
            } catch (PaymentException $e) {
                $flashMessages->addErrorMessage($e->getMessage());
            }
        }

        return null;
    }
}
