<?php

/**
 * Entity model interface for payment table
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

declare(strict_types=1);

namespace VuFind\Db\Entity;

use DateTime;
use VuFind\Db\Type\PaymentStatus;

/**
 * Entity model interface for payment table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface PaymentEntityInterface extends EntityInterface
{
    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Get local payment identifier.
     *
     * @return string
     */
    public function getLocalIdentifier(): string;

    /**
     * Set local payment identifier.
     *
     * @param string $localIdentifier Local identifier
     *
     * @return static
     */
    public function setLocalIdentifier(string $localIdentifier): static;

    /**
     * Get remote payment identifier.
     *
     * @return ?string
     */
    public function getRemoteIdentifier(): ?string;

    /**
     * Set remote payment identifier.
     *
     * @param ?string $remoteIdentifier Remote identifier
     *
     * @return static
     */
    public function setRemoteIdentifier(?string $remoteIdentifier): static;

    /**
     * Get user (only null if entity has not been populated yet).
     *
     * @return ?UserEntityInterface
     */
    public function getUser(): ?UserEntityInterface;

    /**
     * Set user.
     *
     * @param UserEntityInterface $user User
     *
     * @return static
     */
    public function setUser(UserEntityInterface $user): static;

    /**
     * Get source ILS.
     *
     * @return string
     */
    public function getSourceIls(): string;

    /**
     * Set source ILS.
     *
     * @param string $sourceIls Source ILS
     *
     * @return static
     */
    public function setSourceIls(string $sourceIls): static;

    /**
     * Get amount (in pennies, including any tax).
     *
     * @return int
     */
    public function getAmount(): int;

    /**
     * Set amount (in pennies, including any tax).
     *
     * @param int $amount Amount
     *
     * @return static
     */
    public function setAmount(int $amount): static;

    /**
     * Get currency.
     *
     * @return string
     */
    public function getCurrency(): string;

    /**
     * Set currency.
     *
     * @param string $currency Currency
     *
     * @return static
     */
    public function setCurrency(string $currency): static;

    /**
     * Get service fee (in pennies).
     *
     * @return int
     */
    public function getServiceFee(): int;

    /**
     * Set service fee (in pennies).
     *
     * @param int $amount Amount
     *
     * @return static
     */
    public function setServiceFee(int $amount): static;

    /**
     * Get created date.
     *
     * @return DateTime
     */
    public function getCreated(): Datetime;

    /**
     * Set created date.
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static;

    /**
     * Get paid date.
     *
     * @return DateTime
     */
    public function getPaidDate(): ?Datetime;

    /**
     * Set paid date.
     *
     * @param ?DateTime $dateTime Paid date
     *
     * @return static
     */
    public function setPaidDate(?DateTime $dateTime): static;

    /**
     * Get registration start date.
     *
     * @return ?DateTime
     */
    public function getRegistrationStartDate(): ?Datetime;

    /**
     * Set registration start date.
     *
     * @param ?DateTime $dateTime Registration start date
     *
     * @return static
     */
    public function setRegistrationStartDate(?DateTime $dateTime): static;

    /**
     * Get registration date.
     *
     * @return ?DateTime
     */
    public function getRegistrationDate(): ?Datetime;

    /**
     * Set registration date.
     *
     * @param ?DateTime $dateTime Registration date
     *
     * @return static
     */
    public function setRegistrationDate(?DateTime $dateTime): static;

    /**
     * Get status.
     *
     * @return PaymentStatus
     */
    public function getStatus(): PaymentStatus;

    /**
     * Set status.
     *
     * Note that some other methods override the status, so ensure that this is called last if required!
     *
     * @param PaymentStatus $status Status
     *
     * @return static
     */
    public function setStatus(PaymentStatus $status): static;

    /**
     * Get status message.
     *
     * @return string
     */
    public function getStatusMessage(): string;

    /**
     * Set status message.
     *
     * Note that some other methods override the status message, so ensure that this is called last if required!
     *
     * @param string $msg Status message
     *
     * @return static
     */
    public function setStatusMessage(string $msg): static;

    /**
     * Get catalog username.
     *
     * @return string
     */
    public function getCatUsername(): string;

    /**
     * Set catalog username.
     *
     * @param string $catUsername Catalog username
     *
     * @return static
     */
    public function setCatUsername(string $catUsername): static;

    /**
     * Check if the payment is in progress.
     *
     * @return bool
     */
    public function isInProgress(): bool;

    /**
     * Check if the payment is registered with the ILS
     *
     * @return bool
     */
    public function isRegistered(): bool;

    /**
     * Check if the payment is paid and needs registration with the ILS.
     *
     * @return bool
     */
    public function isRegistrationNeeded(): bool;

    /**
     * Check if registration is in progress (i.e. started within 120 seconds).
     *
     * @return bool
     */
    public function isRegistrationInProgress(): bool;

    /**
     * Set payment canceled.
     *
     * @return static
     */
    public function applyCanceledStatus(): static;

    /**
     * Set payment failed.
     *
     * @return static
     */
    public function applyPaymentFailedStatus(): static;

    /**
     * Set payment paid.
     *
     * @return static
     */
    public function applyPaymentPaidStatus(): static;

    /**
     * Set payment registered.
     *
     * @return static
     */
    public function applyRegisteredStatus(): static;

    /**
     * Set payment status to "registration failed".
     *
     * @param string $msg Message
     *
     * @return static
     */
    public function applyRegistrationFailedStatus(string $msg): static;

    /**
     * Set registration start timestamp.
     *
     * @return static
     */
    public function applyRegistrationStartedStatus(): static;

    /**
     * Set payment status to "registration expired".
     *
     * @return static
     */
    public function applyRegistrationExpiredStatus(): static;

    /**
     * Set payment reported.
     *
     * @return static
     */
    public function applyReportedStatus(): static;

    /**
     * Set payment status to "fines updated".
     *
     * @return static
     */
    public function applyFinesUpdatedStatus(): static;

    /**
     * Set payment registration issues resolved.
     *
     * @return static
     */
    public function applyRegistrationResolvedStatus(): static;
}
