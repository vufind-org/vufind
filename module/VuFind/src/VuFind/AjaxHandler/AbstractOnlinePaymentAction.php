<?php

/**
 * Abstract base class for online payment handlers.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

declare(strict_types=1);

namespace VuFind\AjaxHandler;

use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PaymentServiceInterface;
use VuFind\OnlinePayment\OnlinePaymentManager;
use VuFind\Session\Settings as SessionSettings;

/**
 * Abstract base class for online payment handlers.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractOnlinePaymentAction extends \VuFind\AjaxHandler\AbstractBase implements
    \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFind\OnlinePayment\OnlinePaymentEventTrait;

    /**
     * Constructor
     *
     * @param SessionSettings            $sessionSettings      Session settings
     * @param PaymentServiceInterface    $paymentService       Payment database service
     * @param OnlinePaymentManager       $onlinePaymentManager Online payment manager
     * @param AuditEventServiceInterface $auditEventService    Audit event database service
     */
    public function __construct(
        SessionSettings $sessionSettings,
        protected PaymentServiceInterface $paymentService,
        protected OnlinePaymentManager $onlinePaymentManager,
        AuditEventServiceInterface $auditEventService
    ) {
        $this->sessionSettings = $sessionSettings;
        $this->auditEventService = $auditEventService;
    }
}
