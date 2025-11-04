<?php

/**
 * Entity model for finna_due_date_reminder table
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
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace Finna\Db\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Entity model for finna_due_date_reminder table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'finna_due_date_reminder')]
#[ORM\Index(name: 'user_loan', columns: ['user_id', 'loan_id'])]
#[ORM\Index(name: 'due_date_reminder_ibfk_1', columns: ['user_id'])]
#[ORM\Entity]
class FinnaDueDateReminder implements FinnaDueDateReminderEntityInterface
{
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
     * User ID.
     *
     * @var UserEntityInterface
     */
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: UserEntityInterface::class)]
    protected UserEntityInterface $user;

    /**
     * Loan ID.
     *
     * @var string
     */
    #[ORM\Column(name: 'loan_id', type: 'string', length: 255, nullable: false)]
    protected string $loanId;

    /**
     * Due date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'due_date', type: 'datetime', nullable: false)]
    protected DateTime $dueDate;

    /**
     * Notification date.
     *
     * @var DateTime
     */
    #[ORM\Column(
        name: 'notification_date',
        type: 'datetime',
        nullable: false,
        options: ['default' => 'CURRENT_TIMESTAMP']
    ),
    ]
    protected DateTime $notificationDate;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Set the default value as a DateTime object
        $this->notificationDate = new DateTime();
    }

    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
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
        $this->user = $user;
        return $this;
    }

    /**
     * Get user.
     *
     * @return UserEntityInterface
     */
    public function getUser(): UserEntityInterface
    {
        return $this->user;
    }

    /**
     * Get loan ID.
     *
     * @return string
     */
    public function getLoanId(): string
    {
        return $this->loanId;
    }

    /**
     * Set loan ID.
     *
     * @param string $loanId Loan Id
     *
     * @return static
     */
    public function setLoanId(string $loanId): static
    {
        $this->loanId = $loanId;
        return $this;
    }

    /**
     * Set due date.
     *
     * @return DateTime
     */
    public function getDueDate(): DateTime
    {
        return $this->dueDate;
    }

    /**
     * Get due date.
     *
     * @param DateTime $dateTime Due date
     *
     * @return static
     */
    public function setDueDate(DateTime $dateTime): static
    {
        $this->dueDate = $dateTime;
        return $this;
    }

    /**
     * Get notification date.
     *
     * @return DateTime
     */
    public function getNotificationDate(): DateTime
    {
        return $this->notificationDate;
    }

    /**
     * Set notification date.
     *
     * @param ?DateTime $dateTime Notification date
     *
     * @return static
     */
    public function setNotificationDate(?DateTime $dateTime): static
    {
        $this->notificationDate = $dateTime;
        return $this;
    }
}
