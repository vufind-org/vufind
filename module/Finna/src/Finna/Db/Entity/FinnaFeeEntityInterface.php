<?php

/**
 * Interface for representing a transaction fee.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Db_Interface
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Finna\Db\Entity;

use VuFind\Db\Entity\EntityInterface;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Interface for representing a transaction fee.
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface FinnaFeeEntityInterface extends EntityInterface
{
    /**
     * Id getter
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Transaction setter
     *
     * @param FinnaTransactionEntityInterface $transaction Transaction.
     *
     * @return static
     */
    public function setTransaction(FinnaTransactionEntityInterface $transaction): static;

    /**
     * Transaction getter
     *
     * @return FinnaTransactionEntityInterface
     */
    public function getTransaction(): FinnaTransactionEntityInterface;

    /**
     * Set user.
     *
     * @param UserEntityInterface $user User owning the list.
     *
     * @return static
     */
    public function setUser(UserEntityInterface $user): static;

    /**
     * Get user.
     *
     * @return UserEntityInterface
     */
    public function getUser(): UserEntityInterface;

    /**
     * Title setter
     *
     * @param string $title Title
     *
     * @return static
     */
    public function setTitle(string $title): static;

    /**
     * Title getter
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * Type setter
     *
     * @param string $type Type
     *
     * @return static
     */
    public function setType(string $type): static;

    /**
     * Type getter
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Description setter
     *
     * @param string $description Description
     *
     * @return static
     */
    public function setDescription(string $description): static;

    /**
     * Description getter
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Amount setter
     *
     * @param int $amount Amount
     *
     * @return static
     */
    public function setAmount(int $amount): static;

    /**
     * Amount getter
     *
     * @return int
     */
    public function getAmount(): int;

    /**
     * Currency setter
     *
     * @param string $currency Currency
     *
     * @return static
     */
    public function setCurrency(string $currency): static;

    /**
     * Currency getter
     *
     * @return string
     */
    public function getCurrency(): string;

    /**
     * Fine Id setter
     *
     * @param string $fineId Fine ID (ILS)
     *
     * @return static
     */
    public function setFineId(string $fineId): static;

    /**
     * Fine Id getter
     *
     * @return string
     */
    public function getFineId(): string;

    /**
     * Organization setter
     *
     * @param string $organization Organization
     *
     * @return static
     */
    public function setOrganization(string $organization): static;

    /**
     * Organization getter
     *
     * @return string
     */
    public function getOrganization(): string;
}
