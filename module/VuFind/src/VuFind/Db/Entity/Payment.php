<?php

/**
 * Entity model for payment table
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
 * @link     https://vufind.org   Main Site
 */

declare(strict_types=1);

namespace VuFind\Db\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use VuFind\Db\Feature\DateTimeTrait;
use VuFind\Db\Type\PaymentStatus;

use function in_array;

/**
 * Entity model for payment table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org   Main Site
 */
#[ORM\Table(name: 'payment')]
#[ORM\Index(name: 'payment_local_identifier_idx', columns: ['local_identifier'])]
#[ORM\Index(name: 'payment_user_id_idx', columns: ['user_id'])]
#[ORM\Index(name: 'payment_status_cat_username_created_idx', columns: ['status', 'cat_username', 'created'])]
#[ORM\Index(name: 'payment_paid_reported_idx', columns: ['paid', 'reported'])]
#[ORM\Entity]
class Payment implements PaymentEntityInterface
{
    use DateTimeTrait;

    /**
     * Timeout (in seconds) before a previously started ILS registration request is considered a failure.
     *
     * @var int
     */
    public const ILS_REGISTRATION_TIMEOUT = 120;

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
     * Local identifier.
     *
     * @var string
     */
    #[ORM\Column(name: 'local_identifier', type: 'string', length: 255, nullable: false)]
    protected string $localIdentifier;

    /**
     * Remote identifier.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'remote_identifier', type: 'string', length: 255, nullable: true)]
    protected ?string $remoteIdentifier = null;

    /**
     * User.
     *
     * @var User
     */
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: \VuFind\Db\Entity\User::class)]
    protected User $user;

    /**
     * Source ILS.
     *
     * @var string
     */
    #[ORM\Column(name: 'source_ils', type: 'string', length: 255, nullable: false)]
    protected string $sourceIls;

    /**
     * Catalog username.
     *
     * @var string
     */
    #[ORM\Column(name: 'cat_username', type: 'string', length: 50, nullable: false)]
    protected string $catUsername;

    /**
     * Amount.
     *
     * @var int
     */
    #[ORM\Column(name: 'amount', type: 'integer', nullable: false)]
    protected int $amount;

    /**
     * Currency.
     *
     * @var string
     */
    #[ORM\Column(name: 'currency', type: 'string', length: 3, nullable: false)]
    protected string $currency;

    /**
     * Service fee.
     *
     * @var int
     */
    #[ORM\Column(name: 'service_fee', type: 'integer', nullable: false)]
    protected int $serviceFee;

    /**
     * Created date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'created', type: 'datetime', nullable: false, options: ['default' => '2000-01-01 00:00:00'])]
    protected DateTime $created;

    /**
     * Paid date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'paid', type: 'datetime', nullable: false, options: ['default' => '2000-01-01 00:00:00'])]
    protected DateTime $paid;

    /**
     * Registration started date.
     *
     * @var DateTime
     */
    #[ORM\Column(
        name: 'registration_started',
        type: 'datetime',
        nullable: false,
        options: ['default' => '2000-01-01 00:00:00']
    )]
    protected DateTime $registrationStarted;

    /**
     * Registered date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'registered', type: 'datetime', nullable: false, options: ['default' => '2000-01-01 00:00:00'])]
    protected DateTime $registered;

    /**
     * Status.
     *
     * @var int
     */
    #[ORM\Column(name: 'status', type: 'integer', nullable: false, options: ['default' => 0])]
    protected int $status;

    /**
     * Status message.
     *
     * @var string
     */
    #[ORM\Column(name: 'status_message', type: 'string', length: 255, nullable: true)]
    protected string $statusMessage;

    /**
     * Reported date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'reported', type: 'datetime', nullable: false, options: ['default' => '2000-01-01 00:00:00'])]
    protected DateTime $reported;

    /**
     * Constructor.
     */
    public function __construct()
    {
        // Set the default values as DateTime objects
        $this->created = $this->getUnassignedDefaultDateTime();
        $this->paid = $this->getUnassignedDefaultDateTime();
        $this->registrationStarted = $this->getUnassignedDefaultDateTime();
        $this->registered = $this->getUnassignedDefaultDateTime();
        $this->reported = $this->getUnassignedDefaultDateTime();
    }

    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get local payment identifier.
     *
     * @return string
     */
    public function getLocalIdentifier(): string
    {
        return $this->localIdentifier;
    }

    /**
     * Set local payment identifier.
     *
     * @param string $localIdentifier Local identifier
     *
     * @return static
     */
    public function setLocalIdentifier(string $localIdentifier): static
    {
        $this->localIdentifier = $localIdentifier;
        return $this;
    }

    /**
     * Get remote payment identifier.
     *
     * @return ?string
     */
    public function getRemoteIdentifier(): ?string
    {
        return $this->remoteIdentifier;
    }

    /**
     * Set remote payment identifier.
     *
     * @param ?string $remoteIdentifier Remote identifier
     *
     * @return static
     */
    public function setRemoteIdentifier(?string $remoteIdentifier): static
    {
        $this->remoteIdentifier = $remoteIdentifier;
        return $this;
    }

    /**
     * Get user (only null if entity has not been populated yet).
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
     * @param UserEntityInterface $user User
     *
     * @return static
     */
    public function setUser(UserEntityInterface $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Get source ILS.
     *
     * @return string
     */
    public function getSourceIls(): string
    {
        return $this->sourceIls;
    }

    /**
     * Set source ILS.
     *
     * @param string $sourceIls Source ILS
     *
     * @return static
     */
    public function setSourceIls(string $sourceIls): static
    {
        $this->sourceIls = $sourceIls;
        return $this;
    }

    /**
     * Get amount (in pennies, including any tax).
     *
     * @return int
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * Set amount (in pennies, including any tax).
     *
     * @param int $amount Amount
     *
     * @return static
     */
    public function setAmount(int $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * Get currency.
     *
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Set currency.
     *
     * @param string $currency Currency
     *
     * @return static
     */
    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * Get service fee (in pennies).
     *
     * @return int
     */
    public function getServiceFee(): int
    {
        return $this->serviceFee;
    }

    /**
     * Set service fee (in pennies).
     *
     * @param int $amount Amount
     *
     * @return static
     */
    public function setServiceFee(int $amount): static
    {
        $this->serviceFee = $amount;
        return $this;
    }

    /**
     * Get created date.
     *
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return $this->created;
    }

    /**
     * Set created date.
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static
    {
        $this->created = $dateTime;
        return $this;
    }

    /**
     * Get paid date.
     *
     * @return DateTime
     */
    public function getPaidDate(): ?DateTime
    {
        return $this->getNullableDateTimeFromNonNullable($this->paid);
    }

    /**
     * Set paid date.
     *
     * @param ?DateTime $dateTime Paid date
     *
     * @return static
     */
    public function setPaidDate(?DateTime $dateTime): static
    {
        $this->paid = $this->getNonNullableDateTimeFromNullable($dateTime);
        return $this;
    }

    /**
     * Get registration start date.
     *
     * @return ?DateTime
     */
    public function getRegistrationStartDate(): ?DateTime
    {
        return $this->getNullableDateTimeFromNonNullable($this->registrationStarted);
    }

    /**
     * Set registration start date.
     *
     * @param ?DateTime $dateTime Registration start date
     *
     * @return static
     */
    public function setRegistrationStartDate(?DateTime $dateTime): static
    {
        $this->registrationStarted = $this->getNonNullableDateTimeFromNullable($dateTime);
        return $this;
    }

    /**
     * Get registration date.
     *
     * @return ?DateTime
     */
    public function getRegistrationDate(): ?DateTime
    {
        return $this->getNullableDateTimeFromNonNullable($this->registered);
    }

    /**
     * Set registration date.
     *
     * @param ?DateTime $dateTime Registration date
     *
     * @return static
     */
    public function setRegistrationDate(?DateTime $dateTime): static
    {
        $this->registered = $this->getNonNullableDateTimeFromNullable($dateTime);
        return $this;
    }

    /**
     * Get status.
     *
     * @return PaymentStatus
     */
    public function getStatus(): PaymentStatus
    {
        return PaymentStatus::from($this->status);
    }

    /**
     * Set status.
     *
     * Note that some other methods override the status, so ensure that this is called last if required!
     *
     * @param PaymentStatus $status Status
     *
     * @return static
     */
    public function setStatus(PaymentStatus $status): static
    {
        $this->status = $status->value;
        return $this;
    }

    /**
     * Get status message.
     *
     * @return string
     */
    public function getStatusMessage(): string
    {
        return $this->statusMessage ?? '';
    }

    /**
     * Set status message.
     *
     * Note that some other methods override the status message, so ensure that this is called last if required!
     *
     * @param string $msg Status message
     *
     * @return static
     */
    public function setStatusMessage(string $msg): static
    {
        $this->statusMessage = $msg;
        return $this;
    }

    /**
     * Get catalog username.
     *
     * @return string
     */
    public function getCatUsername(): string
    {
        return $this->catUsername;
    }

    /**
     * Set catalog username.
     *
     * @param string $catUsername Catalog username
     *
     * @return static
     */
    public function setCatUsername(string $catUsername): static
    {
        $this->catUsername = $catUsername;
        return $this;
    }

    /**
     * Check if the payment is in progress.
     *
     * @return bool
     */
    public function isInProgress(): bool
    {
        return $this->status === PaymentStatus::InProgress->value;
    }

    /**
     * Check if the payment is registered with the ILS.
     *
     * @return bool
     */
    public function isRegistered(): bool
    {
        return $this->status === PaymentStatus::Completed->value;
    }

    /**
     * Check if the payment is paid and needs registration with the ILS.
     *
     * @return bool
     */
    public function isRegistrationNeeded(): bool
    {
        return in_array(
            $this->status,
            [
                PaymentStatus::Paid->value,
                PaymentStatus::RegistrationFailed->value,
            ]
        );
    }

    /**
     * Check if registration is in progress (i.e. started within 120 seconds).
     *
     * @return bool
     */
    public function isRegistrationInProgress(): bool
    {
        $startDate = $this->getRegistrationStartDate();
        return $startDate && (time() - $startDate->getTimestamp() < static::ILS_REGISTRATION_TIMEOUT);
    }

    /**
     * Set payment canceled.
     *
     * @return static
     */
    public function applyCanceledStatus(): static
    {
        $this->status = PaymentStatus::Canceled->value;
        $this->statusMessage = '';
        return $this;
    }

    /**
     * Set payment failed.
     *
     * @return static
     */
    public function applyPaymentFailedStatus(): static
    {
        $this->status = PaymentStatus::PaymentFailed->value;
        $this->statusMessage = '';
        return $this;
    }

    /**
     * Set payment paid.
     *
     * @return static
     */
    public function applyPaymentPaidStatus(): static
    {
        $this->paid = new DateTime();
        $this->status = PaymentStatus::Paid->value;
        $this->statusMessage = '';
        return $this;
    }

    /**
     * Set payment registered.
     *
     * @return static
     */
    public function applyRegisteredStatus(): static
    {
        $this->registered = new DateTime();
        $this->status = PaymentStatus::Completed->value;
        $this->statusMessage = '';
        return $this;
    }

    /**
     * Set payment status to "registration failed".
     *
     * @param string $msg Message
     *
     * @return static
     */
    public function applyRegistrationFailedStatus(string $msg): static
    {
        $this->status = PaymentStatus::RegistrationFailed->value;
        $this->statusMessage = $msg;
        $this->registrationStarted = $this->getUnassignedDefaultDateTime();
        return $this;
    }

    /**
     * Set registration start timestamp.
     *
     * @return static
     */
    public function applyRegistrationStartedStatus(): static
    {
        $this->registrationStarted = new DateTime();
        return $this;
    }

    /**
     * Set payment status to "registration expired".
     *
     * @return static
     */
    public function applyRegistrationExpiredStatus(): static
    {
        $this->status = PaymentStatus::RegistrationExpired->value;
        $this->statusMessage = '';
        return $this;
    }

    /**
     * Set payment reported.
     *
     * @return static
     */
    public function applyReportedStatus(): static
    {
        $this->reported = new DateTime();
        return $this;
    }

    /**
     * Set payment status to "fines updated".
     *
     * @return static
     */
    public function applyFinesUpdatedStatus(): static
    {
        $this->status = PaymentStatus::FinesUpdated->value;
        $this->statusMessage = '';
        return $this;
    }

    /**
     * Set payment registration issues resolved.
     *
     * @return static
     */
    public function applyRegistrationResolvedStatus(): static
    {
        $this->registered = new DateTime();
        $this->status = PaymentStatus::RegistrationResolved->value;
        $this->statusMessage = '';
        return $this;
    }
}
