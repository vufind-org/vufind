<?php

/**
 * Database service for due date reminders.
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

namespace Finna\Db\Service;

use DateTime;
use Finna\Db\Entity\FinnaDueDateReminderEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\AbstractDbService;

/**
 * Database service for due date reminders.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class FinnaDueDateReminderService extends AbstractDbService implements
    FinnaDueDateReminderServiceInterface
{
    /**
     * Create a Finna entity object.
     *
     * @return FinnaDueDateReminderEntityInterface
     */
    public function createEntity(): FinnaDueDateReminderEntityInterface
    {
        return $this->entityPluginManager->get(FinnaDueDateReminderEntityInterface::class);
    }

    /**
     * Get a reminded loan
     *
     * @param UserEntityInterface $user    User
     * @param string              $loanId  Loan Id
     * @param DateTime            $dueDate Due date
     *
     * @return ?FinnaDueDateReminderEntityInterface
     */
    public function getRemindedLoan(
        UserEntityInterface $user,
        string $loanId,
        DateTime $dueDate
    ): ?FinnaDueDateReminderEntityInterface {
        return $this->entityManager->getRepository(FinnaDueDateReminderEntityInterface::class)
            ->findOneBy(compact('user', 'loanId', 'dueDate'));
    }

    /**
     * Add a reminded loan
     *
     * @param UserEntityInterface $user    User
     * @param string              $loanId  Loan Id
     * @param DateTime            $dueDate Due date
     *
     * @return void
     */
    public function addRemindedLoan(UserEntityInterface $user, string $loanId, DateTime $dueDate): void
    {
        $entity = $this->createEntity();
        $entity->setUser($user)
            ->setLoanId($loanId)
            ->setDueDate($dueDate)
            ->setNotificationDate(new DateTime());
        $this->persistEntity($entity);
    }

    /**
     * Delete a reminded loan
     *
     * @param UserEntityInterface $user   User
     * @param string              $loanId Loan Id
     *
     * @return void
     */
    public function deleteRemindedLoan(UserEntityInterface $user, string $loanId): void
    {
        $dql = 'DELETE ' . FinnaDueDateReminderEntityInterface::class . ' d'
            . ' WHERE user = :user AND loanId = :loanId';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(compact('user', 'loanId'));
        $query->execute();
    }
}
