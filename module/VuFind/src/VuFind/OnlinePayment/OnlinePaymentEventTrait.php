<?php

/**
 * Online payment event log support trait
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023-2025.
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
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

declare(strict_types=1);

namespace VuFind\OnlinePayment;

use VuFind\Controller\AbstractBase as AbstractController;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Type\AuditEventSubtype;

/**
 * Online payment event log support trait.
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait OnlinePaymentEventTrait
{
    /**
     * Audit event log service
     *
     * @var ?AuditEventServiceInterface
     */
    protected ?AuditEventServiceInterface $auditEventService = null;

    /**
     * Add an event log entry for a payment
     *
     * @param PaymentEntityInterface $payment Payment
     * @param AuditEventSubtype      $subtype Event subtype
     * @param string                 $message Status message
     * @param array                  $data    Additional data
     *
     * @return void
     */
    protected function addPaymentEvent(
        PaymentEntityInterface $payment,
        AuditEventSubtype $subtype,
        string $message = '',
        array $data = []
    ): void {
        if (null === $this->auditEventService) {
            if ($this instanceof AbstractController) {
                $this->auditEventService = $this->getDbService(AuditEventServiceInterface::class);
            } else {
                throw new \Exception('Event log service not set');
            }
        }
        $this->auditEventService->addPaymentEvent($payment, $subtype, $message, $data);
    }
}
