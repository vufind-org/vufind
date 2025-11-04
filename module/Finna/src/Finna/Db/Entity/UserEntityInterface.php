<?php

/**
 * Interface for representing a user account record.
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

/**
 * Interface for representing a user account record.
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface UserEntityInterface extends \VuFind\Db\Entity\UserEntityInterface
{
    /**
     * Due date reminder setting setter
     *
     * @param int $remind New due date reminder setting.
     *
     * @return static
     */
    public function setFinnaDueDateReminder(int $remind): static;

    /**
     * Due date reminder setting getter
     *
     * @return int
     */
    public function getFinnaDueDateReminder(): int;

    /**
     * Nickname setter
     *
     * @param ?string $nickname Nickname or null for none
     *
     * @return static
     */
    public function setFinnaNickname(?string $nickname): static;

    /**
     * Nickname getter
     *
     * @return ?string
     */
    public function getFinnaNickName(): ?string;

    /**
     * Protection status setter
     *
     * @param bool $protected Is the user protected
     *
     * @return static
     */
    public function setFinnaProtected(bool $protected): static;

    /**
     * Protection status getter
     *
     * @return bool
     */
    public function getFinnaProtected(): bool;

    /**
     * Last expiration reminder date setter
     *
     * @param ?DateTime $dateTime Expiration reminder date
     *
     * @return static
     */
    public function setFinnaLastExpirationReminderDate(?DateTime $dateTime): static;

    /**
     * Last expiration reminder date getter
     *
     * @return ?DateTime
     */
    public function getFinnaLastExpirationReminderDate(): ?DateTime;
}
