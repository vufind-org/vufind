<?php

/**
 * Entity model interface for event table
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
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Entity;

use DateTime;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;

/**
 * Entity model interface for event table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface AuditEventEntityInterface extends EntityInterface
{
    /**
     * Get date.
     *
     * @return DateTime
     */
    public function getDate(): DateTime;

    /**
     * Set date.
     *
     * @param DateTime $dateTime Date
     *
     * @return static
     */
    public function setDate(DateTime $dateTime): static;

    /**
     * Get type.
     *
     * @return ?string
     */
    public function getType(): ?string;

    /**
     * Set type.
     *
     * @param AuditEventType|string $type Type
     *
     * @return static
     */
    public function setType(AuditEventType|string $type): static;

    /**
     * Get subtype.
     *
     * @return ?string
     */
    public function getSubtype(): ?string;

    /**
     * Set subtype.
     *
     * @param AuditEventSubtype|string $subtype Subtype
     *
     * @return static
     */
    public function setSubtype(AuditEventSubtype|string $subtype): static;

    /**
     * Get user.
     *
     * @return ?UserEntityInterface
     */
    public function getUser(): ?UserEntityInterface;

    /**
     * Set user.
     *
     * @param ?UserEntityInterface $user User
     *
     * @return static
     */
    public function setUser(?UserEntityInterface $user): static;

    /**
     * Get payment.
     *
     * @return ?PaymentEntityInterface
     */
    public function getPayment(): ?PaymentEntityInterface;

    /**
     * Set payment.
     *
     * @param ?PaymentEntityInterface $payment Payment
     *
     * @return static
     */
    public function setPayment(?PaymentEntityInterface $payment): static;

    /**
     * Get username.
     *
     * @return ?string
     */
    public function getUsername(): ?string;

    /**
     * Get session ID.
     *
     * @return ?string
     */
    public function getSessionId(): ?string;

    /**
     * Set session ID.
     *
     * @param ?string $sessionId Session ID
     *
     * @return static
     */
    public function setSessionId(?string $sessionId): static;

    /**
     * Get client IP address.
     *
     * @return ?string
     */
    public function getClientIp(): ?string;

    /**
     * Set client IP address.
     *
     * @param ?string $clientIp Client IP address
     *
     * @return static
     */
    public function setClientIp(?string $clientIp): static;

    /**
     * Get server name.
     *
     * @return ?string
     */
    public function getServerName(): ?string;

    /**
     * Set server name.
     *
     * @param ?string $serverName Server name
     *
     * @return static
     */
    public function setServerName(?string $serverName): static;

    /**
     * Get server IP address.
     *
     * @return ?string
     */
    public function getServerIp(): ?string;

    /**
     * Set server IP address.
     *
     * @param ?string $serverIp Server IP address
     *
     * @return static
     */
    public function setServerIp(?string $serverIp): static;

    /**
     * Get message.
     *
     * @return ?string
     */
    public function getMessage(): ?string;

    /**
     * Set message.
     *
     * @param ?string $message Message
     *
     * @return static
     */
    public function setMessage(?string $message): static;

    /**
     * Get additional data.
     *
     * @return ?string
     */
    public function getData(): ?string;

    /**
     * Set additional data.
     *
     * @param ?string $data Data
     *
     * @return static
     */
    public function setData(?string $data): static;
}
