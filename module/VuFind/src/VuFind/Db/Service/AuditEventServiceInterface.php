<?php

/**
 * Database service interface for Event.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024-2025.
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
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use DateTime;
use VuFind\Db\Entity\AuditEventEntityInterface;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;

/**
 * Database service interface for Event.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface AuditEventServiceInterface extends DbServiceInterface
{
    /**
     * Create an event entity object.
     *
     * @return AuditEventEntityInterface
     */
    public function createEntity(): AuditEventEntityInterface;

    /**
     * Add an event.
     *
     * @param AuditEventType|string    $type    Event type
     * @param AuditEventSubtype|string $subtype Event subtype
     * @param ?UserEntityInterface     $user    User
     * @param string                   $message Status message
     * @param array                    $data    Additional data
     *
     * @return void
     */
    public function addEvent(
        AuditEventType|string $type,
        AuditEventSubtype|string $subtype,
        ?UserEntityInterface $user = null,
        string $message = '',
        array $data = []
    ): void;

    /**
     * Add a payment event.
     *
     * @param PaymentEntityInterface   $payment Payment
     * @param AuditEventSubtype|string $subtype Event subtype
     * @param string                   $message Status message
     * @param array                    $data    Additional data
     *
     * @return void
     */
    public function addPaymentEvent(
        PaymentEntityInterface $payment,
        AuditEventSubtype|string $subtype,
        string $message = '',
        array $data = []
    ): void;

    /**
     * Get an array of events.
     *
     * @param ?DateTime                     $fromDate   Start date
     * @param ?DateTime                     $untilDate  End date
     * @param AuditEventType|string|null    $type       Event type
     * @param AuditEventSubtype|string|null $subtype    Event subtype
     * @param UserEntityInterface|int|null  $userOrId   User entity or ID of user
     * @param ?string                       $username   User's username
     * @param ?string                       $clientIp   Client's IP address
     * @param ?string                       $serverIp   Server's IP address
     * @param ?string                       $serverName Server's host name
     * @param ?PaymentEntityInterface       $payment    Payment entity
     * @param ?array                        $sort       Sort order (null for default descending order)
     *
     * @return AuditEventEntityInterface[]
     */
    public function getEvents(
        ?DateTime $fromDate = null,
        ?DateTime $untilDate = null,
        AuditEventType|string|null $type = null,
        AuditEventSubtype|string|null $subtype = null,
        UserEntityInterface|int|null $userOrId = null,
        ?string $username = null,
        ?string $clientIp = null,
        ?string $serverIp = null,
        ?string $serverName = null,
        ?PaymentEntityInterface $payment = null,
        ?array $sort = null,
    ): array;
}
