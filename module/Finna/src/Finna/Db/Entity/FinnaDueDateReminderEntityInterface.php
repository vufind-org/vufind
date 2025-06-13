<?php

/**
 * Interface for representing a due date reminder.
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

use DateTime;
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
interface FinnaDueDateReminderEntityInterface extends EntityInterface
{
    /**
     * Id getter
     *
     * @return ?int
     */
    public function getId(): ?int;

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
     * Loan Id setter
     *
     * @param string $loanId Loan Id
     *
     * @return static
     */
    public function setLoanId(string $loanId): static;

    /**
     * Loan Id getter
     *
     * @return string
     */
    public function getLoanId(): string;

    /**
     * Due date setter
     *
     * @param DateTime $dateTime Due date
     *
     * @return static
     */
    public function setDueDate(DateTime $dateTime): static;

    /**
     * Due date getter
     *
     * @return DateTime
     */
    public function getDueDate(): Datetime;

    /**
     * Notification date setter
     *
     * @param ?DateTime $dateTime Notification date
     *
     * @return static
     */
    public function setNotificationDate(?DateTime $dateTime): static;

    /**
     * Notification date getter
     *
     * @return DateTime
     */
    public function getNotificationDate(): Datetime;
}
