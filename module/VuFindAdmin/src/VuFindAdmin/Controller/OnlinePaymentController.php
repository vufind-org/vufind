<?php

/**
 * Online Payment Controller
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @link     https://vufind.org Main Site
 */

declare(strict_types=1);

namespace VuFindAdmin\Controller;

use DateTime;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PaymentFeeServiceInterface;
use VuFind\Db\Service\PaymentServiceInterface;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\PaymentStatus;
use VuFind\OnlinePayment\OnlinePaymentEventTrait;
use VuFind\OnlinePayment\OnlinePaymentManager;
use VuFind\Validator\CsrfInterface;

/**
 * Online Payment Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class OnlinePaymentController extends AbstractAdmin
{
    use OnlinePaymentEventTrait;

    /**
     * List payments
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function homeAction()
    {
        $paymentService = $this->getDbService(PaymentServiceInterface::class);
        $payments = $paymentService->getPaymentPaginator(
            $this->getStatusFilter(),
            $this->getStringFilter('local_identifier'),
            $this->getStringFilter('remote_identifier'),
            $this->getStringFilter('source_ils'),
            $this->getStringFilter('cat_username'),
            $this->getDateFilter('created_from'),
            $this->getDateFilter('created_until'),
            $this->getDateFilter('paid_from'),
            $this->getDateFilter('paid_until'),
            (int)$this->getParam('page', default: 1)
        );
        $params = $this->params()->fromQuery() + $this->params()->fromPost();
        $view = $this->createViewModel(
            [
                'payments' => $payments,
                'statuses' => $this->getStatuses(),
                'defaultStatuses' => $this->getDefaultSelectedStatuses(),
                'sourceIlsList' => $paymentService->getUniqueSourceIlsList(),
                'resolvableStatuses' => $this->getResolvableStatuses(),
                'params' => $params,
            ]
        );
        $view->setTemplate('admin/payment/home');
        return $view;
    }

    /**
     * Details
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function detailsAction()
    {
        $id = (int)$this->params()->fromRoute('id');
        $this->setFollowupUrlToReferer();

        $paymentService = $this->getDbService(PaymentServiceInterface::class);
        $paymentFeeService = $this->getDbService(PaymentFeeServiceInterface::class);
        $auditEventService = $this->getDbService(AuditEventServiceInterface::class);
        $paymentEntity = $paymentService->getPaymentById($id);

        // Check if we have recipient organizations:
        $feeSpecificOrganizations = false;
        if ($paymentEntity) {
            $fees = $paymentFeeService->getFeesForPayment($paymentEntity);
            foreach ($fees as $fee) {
                $feeOrg = $fee->getOrganization();
                if ($feeOrg) {
                    $feeSpecificOrganizations = true;
                    break;
                }
            }
        } else {
            $fees = [];
        }

        $view = $this->createViewModel([
            'paymentEntity' => $paymentEntity,
            'paymentFees' => $fees,
            'feeSpecificOrganizations' => $feeSpecificOrganizations,
            'paymentEvents' => $paymentEntity ? $auditEventService->getEvents(payment: $paymentEntity) : [],
            'statuses' => $this->getStatuses(),
        ]);
        $view->setTemplate('admin/payment/details');
        return $view;
    }

    /**
     * Mark payment resolved
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function resolveAction()
    {
        $id = $this->params()->fromRoute('id');
        $paymentService = $this->getDbService(PaymentServiceInterface::class);
        $paymentEntity = $paymentService->getPaymentById($id);
        if ($this->formWasSubmitted('resolve-confirm')) {
            $csrf = $this->getService(CsrfInterface::class);
            if (!$csrf->isValid($this->params()->fromPost('csrf'))) {
                throw new \VuFind\Exception\BadRequest('error_inconsistent_parameters');
            }

            $paymentEntity->applyRegistrationResolvedStatus();
            $onlinePaymentManager = $this->serviceLocator->get(OnlinePaymentManager::class);
            $onlinePaymentManager->persistEntityWithAuditEvent(
                $paymentEntity,
                AuditEventSubtype::PaymentRegistration,
                'Payment marked as resolved'
            );

            if ($this->inLightbox()) {
                $this->clearFollowupUrl();
                return $this->getRefreshResponse();
            }
            if ($url = $this->getAndClearFollowupUrl(true)) {
                return $this->redirect()->toUrl($url);
            }
            return $this->forwardTo('Admin', 'Payment');
        }
        $this->setFollowupUrlToReferer();

        $view = $this->createViewModel([
            'paymentEntity' => $paymentEntity,
            'statuses' => $this->getStatuses(),
        ]);
        $view->setTemplate('admin/payment/resolve');
        return $view;
    }

    /**
     * Converts wildcards and null and "ALL" params to null
     *
     * @param string $param Parameter name
     *
     * @return ?string
     */
    protected function getStringFilter(string $param): ?string
    {
        if ('' === ($result = $this->getParam($param, default: ''))) {
            return null;
        }
        if (str_starts_with($result, '*')) {
            $result = '%' . substr($result, 1);
        }
        if (str_ends_with($result, '*')) {
            $result = substr($result, 0, -1) . '%';
        }
        return $result;
    }

    /**
     * Get a date filter
     *
     * @param string $param Parameter name
     *
     * @return ?DateTime
     */
    protected function getDateFilter(string $param): ?DateTime
    {
        if (!($value = $this->getParam($param))) {
            return null;
        }
        return new DateTime($value);
    }

    /**
     * Get a status set filter
     *
     * @return array
     */
    protected function getStatusFilter(): array
    {
        $statuses = [];
        foreach ((array)($this->getParam('statuses') ?? $this->getDefaultSelectedStatuses()) as $current) {
            if (null !== ($status = PaymentStatus::tryFrom((int)$current))) {
                $statuses[] = $status;
            }
        }
        return $statuses;
    }

    /**
     * Get available payment statuses
     *
     * @return array
     */
    protected function getStatuses(): array
    {
        return [
            PaymentStatus::InProgress->value => 'In Progress',
            PaymentStatus::Completed->value => 'Completed',
            PaymentStatus::Canceled->value => 'Canceled',
            PaymentStatus::Paid->value => 'Waiting for ILS Registration',
            PaymentStatus::PaymentFailed->value => 'Payment Failed',
            PaymentStatus::RegistrationFailed->value => 'ILS Registration Failed',
            PaymentStatus::RegistrationExpired->value => 'ILS Registration Expired',
            PaymentStatus::RegistrationResolved->value => 'ILS Registration Resolved',
            PaymentStatus::FinesUpdated->value => 'ILS Fines Updated',
        ];
    }

    /**
     * Get list of statuses to display by default.
     *
     * @return array
     */
    protected function getDefaultSelectedStatuses(): array
    {
        return [
            PaymentStatus::RegistrationFailed->value,
            PaymentStatus::RegistrationExpired->value,
            PaymentStatus::FinesUpdated->value,
        ];
    }

    /**
     * Get list of statuses that can be marked as resolved.
     *
     * @return array
     */
    protected function getResolvableStatuses(): array
    {
        return [
            PaymentStatus::RegistrationFailed->value,
            PaymentStatus::RegistrationExpired->value,
            PaymentStatus::FinesUpdated->value,
        ];
    }
}
