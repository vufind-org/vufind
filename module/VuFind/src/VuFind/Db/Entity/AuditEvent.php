<?php

/**
 * Entity model for audit_event table
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
 * @link     http://vufind.org   Main Site
 */

namespace VuFind\Db\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use VuFind\Db\Feature\DateTimeTrait;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;

use function is_string;

/**
 * Entity model for audit_event table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
#[ORM\Table(name: 'audit_event')]
#[ORM\Index(name: 'audit_event_user_id_idx', columns: ['user_id'])]
#[ORM\Index(name: 'audit_event_payment_id_idx', columns: ['payment_id'])]
#[ORM\Entity]
class AuditEvent implements AuditEventEntityInterface
{
    use DateTimeTrait;

    /**
     * Unique ID.
     *
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    protected int $id;

    /**
     * Date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'date', type: 'datetime', nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    protected DateTime $date;

    /**
     * Event type.
     *
     * @var string
     */
    #[ORM\Column(name: 'type', type: 'string', length: 50, nullable: false)]
    protected string $type;

    /**
     * Event subtype.
     *
     * @var string
     */
    #[ORM\Column(name: 'subtype', type: 'string', length: 50, nullable: false)]
    protected string $subtype;

    /**
     * User.
     *
     * @var ?UserEntityInterface
     */
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[ORM\ManyToOne(targetEntity: UserEntityInterface::class)]
    protected ?UserEntityInterface $user = null;

    /**
     * Payment.
     *
     * @var ?Payment
     */
    #[ORM\JoinColumn(name: 'payment_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: \VuFind\Db\Entity\Payment::class)]
    protected ?PaymentEntityInterface $payment = null;

    /**
     * Session ID.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'session_id', type: 'string', length: 128, nullable: true)]
    protected ?string $sessionId = null;

    /**
     * Username.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'username', type: 'string', length: 255, nullable: true)]
    protected ?string $username = null;

    /**
     * Client IP address.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'client_ip', type: 'string', length: 255, nullable: true)]
    protected ?string $clientIp = null;

    /**
     * Server IP address.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'server_ip', type: 'string', length: 255, nullable: true)]
    protected ?string $serverIp = null;

    /**
     * Server name.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'server_name', type: 'string', length: 255, nullable: true)]
    protected ?string $serverName = null;

    /**
     * Log message.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'message', type: 'string', length: 255, nullable: true)]
    protected ?string $message = null;

    /**
     * Additional data (JSON).
     *
     * @var ?string
     */
    #[ORM\Column(name: 'data', type: 'json', nullable: true)]
    protected ?string $data = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Set the default value as a DateTime object
        $this->date = new DateTime();
    }

    /**
     * Get date.
     *
     * @return DateTime
     */
    public function getDate(): DateTime
    {
        return $this->date;
    }

    /**
     * Set date.
     *
     * @param DateTime $dateTime Date
     *
     * @return static
     */
    public function setDate(DateTime $dateTime): static
    {
        $this->date = $dateTime;
        return $this;
    }

    /**
     * Get type.
     *
     * @return ?string
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Set type.
     *
     * @param AuditEventType $type Type
     *
     * @return static
     */
    public function setType(AuditEventType|string $type): static
    {
        $this->type = is_string($type) ? $type : $type->value;
        return $this;
    }

    /**
     * Get subtype.
     *
     * @return ?string
     */
    public function getSubtype(): ?string
    {
        return $this->subtype;
    }

    /**
     * Set subtype.
     *
     * @param AuditEventSubtype|string $subtype Subtype
     *
     * @return static
     */
    public function setSubtype(AuditEventSubtype|string $subtype): static
    {
        $this->subtype = is_string($subtype) ? $subtype : $subtype->value;
        return $this;
    }

    /**
     * Get user.
     *
     * @return ?UserEntityInterface
     */
    public function getUser(): ?UserEntityInterface
    {
        return $this->user;
    }

    /**
     * Set user.
     *
     * @param ?UserEntityInterface $user User
     *
     * @return static
     */
    public function setUser(?UserEntityInterface $user): static
    {
        // Set user only if it's an existing one:
        $this->user = $user?->getId() ? $user : null;
        // Set username always:
        $this->username = $user?->getUsername();
        return $this;
    }

    /**
     * Get payment.
     *
     * @return ?PaymentEntityInterface
     */
    public function getPayment(): ?PaymentEntityInterface
    {
        return $this->payment;
    }

    /**
     * Set payment.
     *
     * @param ?PaymentEntityInterface $payment Payment
     *
     * @return static
     */
    public function setPayment(?PaymentEntityInterface $payment): static
    {
        $this->payment = $payment;
        return $this;
    }

    /**
     * Get username.
     *
     * @return ?string
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * Get session ID.
     *
     * @return ?string
     */
    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * Set session ID.
     *
     * @param ?string $sessionId Session ID
     *
     * @return static
     */
    public function setSessionId(?string $sessionId): static
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    /**
     * Get client IP address.
     *
     * @return ?string
     */
    public function getClientIp(): ?string
    {
        return $this->clientIp;
    }

    /**
     * Set client IP address.
     *
     * @param ?string $clientIp Client IP address
     *
     * @return static
     */
    public function setClientIp(?string $clientIp): static
    {
        $this->clientIp = $clientIp;
        return $this;
    }

    /**
     * Get server IP address.
     *
     * @return ?string
     */
    public function getServerIp(): ?string
    {
        return $this->serverIp;
    }

    /**
     * Set server IP address.
     *
     * @param ?string $serverIp Server IP address
     *
     * @return static
     */
    public function setServerIp(?string $serverIp): static
    {
        $this->serverIp = $serverIp;
        return $this;
    }

    /**
     * Get server name.
     *
     * @return ?string
     */
    public function getServerName(): ?string
    {
        return $this->serverName;
    }

    /**
     * Set server name.
     *
     * @param ?string $serverName Server name
     *
     * @return static
     */
    public function setServerName(?string $serverName): static
    {
        $this->serverName = $serverName;
        return $this;
    }

    /**
     * Get message.
     *
     * @return ?string
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Set message.
     *
     * @param ?string $message Message
     *
     * @return static
     */
    public function setMessage(?string $message): static
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Get additional data.
     *
     * @return ?string
     */
    public function getData(): ?string
    {
        return $this->data;
    }

    /**
     * Set additional data.
     *
     * @param ?string $data Data
     *
     * @return static
     */
    public function setData(?string $data): static
    {
        $this->data = $data;
        return $this;
    }
}
