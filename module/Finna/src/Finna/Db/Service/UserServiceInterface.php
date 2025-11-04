<?php

/**
 * Database service interface for User.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace Finna\Db\Service;

use DateTime;
use Finna\Db\Entity\UserEntityInterface;

/**
 * Database service interface for User.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface UserServiceInterface extends \VuFind\Db\Service\UserServiceInterface
{
    /**
     * Update due date reminder setting for a user
     *
     * @param UserEntityInterface $user            User
     * @param int                 $dueDateReminder Due date reminder (days in advance)
     *
     * @return void
     */
    public function setDueDateReminderForUser(UserEntityInterface $user, int $dueDateReminder): void;

    /**
     * Retrieve protected users.
     *
     * @return UserEntityInterface[]
     */
    public function getProtectedUsers(): array;

    /**
     * Get users that haven't logged in since the given date.
     *
     * @param DateTime $lastLoginDateThreshold Last login date threshold
     *
     * @return UserEntityInterface[]
     */
    public function getExpiringUsers(DateTime $lastLoginDateThreshold): array;

    /**
     * Get users with due date reminders.
     *
     * @return UserEntityInterface[]
     */
    public function getUsersWithDueDateReminders(): array;

    /**
     * Check if given nickname is available
     *
     * @param string $nickname Nickname
     *
     * @return bool
     */
    public function isNicknameAvailable(string $nickname): bool;
}
