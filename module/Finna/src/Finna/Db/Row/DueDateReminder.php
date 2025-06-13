<?php

/**
 * Row Definition for due date reminder.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2024.
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
 * @package  Db_Row
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Db\Row;

use DateTime;
use Finna\Db\Entity\FinnaDueDateReminderEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Row Definition for due date reminder.
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 *
 * @property int $user_id
 * @property string $loan_id
 * @property string $due_date
 * @property string $notification_date
 */
class DueDateReminder extends \VuFind\Db\Row\RowGateway implements
    FinnaDueDateReminderEntityInterface,
    \VuFind\Db\Service\DbServiceAwareInterface
{
    use \VuFind\Db\Service\DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'finna_due_date_reminder', $adapter);
    }

    /**
     * Id getter
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Set user.
     *
     * @param UserEntityInterface $user User owning the list.
     *
     * @return static
     */
    public function setUser(UserEntityInterface $user): static
    {
        $this->user_id = $user->getId();
        return $this;
    }

    /**
     * Get user.
     *
     * @return UserEntityInterface
     */
    public function getUser(): UserEntityInterface
    {
        return $this->getDbService(\VuFind\Db\Service\UserServiceInterface::class)->getUserById($this->user_id);
    }

    /**
     * Loan Id setter
     *
     * @param string $loanId Loan Id
     *
     * @return static
     */
    public function setLoanId(string $loanId): static
    {
        $this->loan_id = $loanId;
        return $this;
    }

    /**
     * Loan Id getter
     *
     * @return string
     */
    public function getLoanId(): string
    {
        return $this->loan_id ?? '';
    }

    /**
     * Due date setter
     *
     * @param DateTime $dateTime Due date
     *
     * @return static
     */
    public function setDueDate(DateTime $dateTime): static
    {
        $this->due_date = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Due date getter
     *
     * @return DateTime
     */
    public function getDueDate(): Datetime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->due_date);
    }

    /**
     * Notification date setter
     *
     * @param ?DateTime $dateTime Notification date
     *
     * @return static
     */
    public function setNotificationDate(?DateTime $dateTime): static
    {
        $this->notification_date = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Notification date getter
     *
     * @return DateTime
     */
    public function getNotificationDate(): Datetime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->notification_date);
    }
}
