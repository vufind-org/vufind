<?php

/**
 * Entity model interface for payment_fee table
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
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

declare(strict_types=1);

namespace VuFind\Db\Entity;

/**
 * Entity model interface for payment_fee table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface PaymentFeeEntityInterface extends EntityInterface
{
    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Get payment.
     *
     * @return PaymentEntityInterface
     */
    public function getPayment(): PaymentEntityInterface;

    /**
     * Set payment.
     *
     * @param PaymentEntityInterface $payment Payment.
     *
     * @return static
     */
    public function setPayment(PaymentEntityInterface $payment): static;

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * Set title.
     *
     * @param string $title Title
     *
     * @return static
     */
    public function setTitle(string $title): static;

    /**
     * Get type.
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Set type.
     *
     * @param string $type Type
     *
     * @return static
     */
    public function setType(string $type): static;

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Set description.
     *
     * @param string $description Description
     *
     * @return static
     */
    public function setDescription(string $description): static;

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
     * Get tax percent (in 1/100ths of a percent).
     *
     * @return int
     */
    public function getTaxPercent(): int;

    /**
     * Set tax percent (in 1/100ths of a percent).
     *
     * @param int $taxPercent Tax percent
     *
     * @return static
     */
    public function setTaxPercent(int $taxPercent): static;

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
     * Get fine identifier.
     *
     * @return string
     */
    public function getFineId(): string;

    /**
     * Set fine identifier.
     *
     * @param string $fineId Fine identifier (ILS)
     *
     * @return static
     */
    public function setFineId(string $fineId): static;

    /**
     * Get organization.
     *
     * @return string
     */
    public function getOrganization(): string;

    /**
     * Set organization.
     *
     * @param string $organization Organization
     *
     * @return static
     */
    public function setOrganization(string $organization): static;

    /**
     * Get amount excluding any tax (in pennies).
     *
     * @return int
     */
    public function calculateAmountExcludingTax(): int;

    /**
     * Get tax included in amount.
     *
     * @return int
     */
    public function calculateTax(): int;
}
