<?php

/**
 * Abtract base class for online payment actions.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2026.
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

namespace VuFindAdmin\Action\AdminPayment;

use DateTime;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PaymentFeeServiceInterface;
use VuFind\Db\Service\PaymentServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Type\PaymentStatus;
use VuFind\OnlinePayment\OnlinePaymentManager;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Validator\CsrfInterface;
use VuFind\View\GlobalsContainer;
use VuFindAdmin\Action\Admin\AbstractAdminAction;

/**
 * Abtract base class for online payment actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractPaymentAction extends AbstractAdminAction
{
    /**
     * Constructor.
     *
     * @param GlobalsContainer           $globalsContainer     Globals container
     * @param array                      $config               VuFind configuration
     * @param PaymentServiceInterface    $paymentService       Payment database service
     * @param PaymentFeeServiceInterface $paymentFeeService    Payment fee database service
     * @param AuditEventServiceInterface $auditEventService    Audit event database service
     * @param OnlinePaymentManager       $onlinePaymentManager Online payment manager
     * @param CsrfInterface              $csrf                 CSRF validator
     */
    public function __construct(
        GlobalsContainer $globalsContainer,
        #[Autowire(config: 'config')]
        array $config,
        #[Autowire(container: DbServicePluginManager::class)]
        protected PaymentServiceInterface $paymentService,
        #[Autowire(container: DbServicePluginManager::class)]
        protected PaymentFeeServiceInterface $paymentFeeService,
        #[Autowire(container: DbServicePluginManager::class)]
        protected AuditEventServiceInterface $auditEventService,
        protected OnlinePaymentManager $onlinePaymentManager,
        protected CsrfInterface $csrf,
    ) {
        parent::__construct($globalsContainer, $config);
    }

    /**
     * Converts wildcards and null and "ALL" params to null.
     *
     * @param string $param Parameter name
     *
     * @return ?string
     */
    protected function getStringFilter(string $param): ?string
    {
        if ('' === ($result = $this->getPostOrQueryParam($param, ''))) {
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
     * Get a date filter.
     *
     * @param string $param Parameter name
     *
     * @return ?DateTime
     */
    protected function getDateFilter(string $param): ?DateTime
    {
        if (!($value = $this->getPostOrQueryParam($param))) {
            return null;
        }
        return new DateTime($value);
    }

    /**
     * Get available payment statuses.
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
