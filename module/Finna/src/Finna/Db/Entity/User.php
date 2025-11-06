<?php

/**
 * Entity model for user table
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

/**
 * Entity model for user table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Index(name: 'finna_user_due_date_reminder_key', columns: ['finna_due_date_reminder'])]
#[ORM\Entity]
class User extends \VuFind\Db\Entity\User implements UserEntityInterface
{
    /**
     * Finna due date reminder frequency.
     *
     * @var int
     */
    #[ORM\Column(name: 'finna_due_date_reminder', type: 'integer', nullable: false)]
    protected int $finnaDueDateReminder = 0;

    /**
     * Finna last account expiration reminder date.
     *
     * @var DateTime
     */
    #[ORM\Column(
        name: 'finna_last_expiration_reminder',
        type: 'datetime',
        nullable: false,
        options: ['default' => '2000-01-01 00:00:00']
    )
    ]
    protected DateTime $finnaLastExpirationReminder;

    /**
     * Finna nickname.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'finna_nickname', type: 'string', length: 255, nullable: true)]
    protected ?string $finnaNickname = null;

    /**
     * Finna protection flag.
     *
     * @var bool
     */
    #[ORM\Column(name: 'finna_protected', type: 'boolean', nullable: false, options: ['default' => false])]
    protected bool $finnaProtected = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        // Set the default values as DateTime objects
        $this->finnaLastExpirationReminder = $this->getUnassignedDefaultDateTime();
    }

    /**
     * Due date reminder setting setter
     *
     * @param int $remind New due date reminder setting.
     *
     * @return static
     */
    public function setFinnaDueDateReminder(int $remind): static
    {
        $this->finnaDueDateReminder = $remind;
        return $this;
    }

    /**
     * Due date reminder setting getter
     *
     * @return int
     */
    public function getFinnaDueDateReminder(): int
    {
        return $this->finnaDueDateReminder;
    }

    /**
     * Nickname setter
     *
     * @param ?string $nickname Nickname or null for none
     *
     * @return static
     */
    public function setFinnaNickname(?string $nickname): static
    {
        $this->finnaNickname = $nickname;
        return $this;
    }

    /**
     * Nickname getter
     *
     * @return ?string
     */
    public function getFinnaNickName(): ?string
    {
        return $this->finnaNickname;
    }

    /**
     * Protection status setter
     *
     * @param bool $protected Is the user protected
     *
     * @return static
     */
    public function setFinnaProtected(bool $protected): static
    {
        $this->finnaProtected = $protected;
        return $this;
    }

    /**
     * Protection status getter
     *
     * @return bool
     */
    public function getFinnaProtected(): bool
    {
        return $this->finnaProtected;
    }

    /**
     * Last expiration reminder date setter
     *
     * @param ?DateTime $dateTime Expiration reminder date
     *
     * @return static
     */
    public function setFinnaLastExpirationReminderDate(?DateTime $dateTime): static
    {
        $this->finnaLastExpirationReminder = $this->getNonNullableDateTimeFromNullable($dateTime);
        return $this;
    }

    /**
     * Last expiration reminder date getter
     *
     * @return DateTime
     */
    public function getFinnaLastExpirationReminderDate(): ?DateTime
    {
        return $this->getNullableDateTimeFromNonNullable($this->finnaLastExpirationReminder);
    }

    /**
     * Get a display name
     *
     * @return string
     */
    public function getDisplayName()
    {
        return trim($this->getFirstName() . ' ' . $this->getLastName())
            ?: $this->getEmail()
            ?: $this->getUsername();
    }

    /**
     * Get a displayable version of username
     *
     * @return string
     */
    public function getDisplayableUsername(): string
    {
        $view = null;
        $username = $this->getUsername();
        if (str_contains($username, ':')) {
            [$view, $username] = explode(':', $username, 2);
        }
        if ($this->getAuthMethod() === 'multiils') {
            $parts = explode('.', $username, 2);
            $displayName = $parts[1] ?? $parts[0];
        } else {
            $displayName = $username;
        }
        if ($view) {
            $displayName .= ' (' . $view . ')';
        }
        return $displayName;
    }
}
