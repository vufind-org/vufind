<?php

/**
 * Row Definition for login_token
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Row;

use DateTime;
use VuFind\Db\Entity\LoginTokenEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\UserServiceInterface;

/**
 * Row Definition for login_token
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int     $id
 * @property int     $user_id
 * @property string  $token
 * @property string  $series
 * @property string  $last_login
 * @property ?string $browser
 * @property ?string $platform
 * @property int     $expires
 * @property string  $last_session_id
 */
class LoginToken extends RowGateway implements DbServiceAwareInterface, LoginTokenEntityInterface
{
    use DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'login_token', $adapter);
    }

    /**
     * Getter for ID.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Setter for User.
     *
     * @param UserEntityInterface $user User to set
     *
     * @return LoginTokenEntityInterface
     */
    public function setUser(UserEntityInterface $user): LoginTokenEntityInterface
    {
        $this->user_id = $user->getId();
        return $this;
    }

    /**
     * User getter (only null if entity has not been populated yet).
     *
     * @return ?UserEntityInterface
     */
    public function getUser(): ?UserEntityInterface
    {
        return $this->user_id
            ? $this->getDbServiceManager()->get(UserServiceInterface::class)->getUserById($this->user_id)
            : null;
    }

    /**
     * Set token string.
     *
     * @param string $token Token
     *
     * @return LoginTokenEntityInterface
     */
    public function setToken(string $token): LoginTokenEntityInterface
    {
        $this->token = $token;
        return $this;
    }

    /**
     * Get token string.
     *
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Set series string.
     *
     * @param string $series Series
     *
     * @return LoginTokenEntityInterface
     */
    public function setSeries(string $series): LoginTokenEntityInterface
    {
        $this->series = $series;
        return $this;
    }

    /**
     * Get series string.
     *
     * @return string
     */
    public function getSeries(): string
    {
        return $this->series;
    }

    /**
     * Set last login date/time.
     *
     * @param DateTime $dateTime Last login date/time
     *
     * @return LoginTokenEntityInterface
     */
    public function setLastLogin(DateTime $dateTime): LoginTokenEntityInterface
    {
        $this->last_login = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Get last login date/time.
     *
     * @return DateTime
     */
    public function getLastLogin(): DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->last_login);
    }

    /**
     * Set browser details (or null for none).
     *
     * @param ?string $browser Browser details (or null for none)
     *
     * @return LoginTokenEntityInterface
     */
    public function setBrowser(?string $browser): LoginTokenEntityInterface
    {
        $this->browser = $browser;
        return $this;
    }

    /**
     * Get browser details (or null for none).
     *
     * @return ?string
     */
    public function getBrowser(): ?string
    {
        return $this->browser;
    }

    /**
     * Set platform details (or null for none).
     *
     * @param ?string $platform Platform details (or null for none)
     *
     * @return LoginTokenEntityInterface
     */
    public function setPlatform(?string $platform): LoginTokenEntityInterface
    {
        $this->platform = $platform;
        return $this;
    }

    /**
     * Get platform details (or null for none).
     *
     * @return ?string
     */
    public function getPlatform(): ?string
    {
        return $this->platform;
    }

    /**
     * Set expiration timestamp.
     *
     * @param int $expires Expiration timestamp
     *
     * @return LoginTokenEntityInterface
     */
    public function setExpires(int $expires): LoginTokenEntityInterface
    {
        $this->expires = $expires;
        return $this;
    }

    /**
     * Get expiration timestamp.
     *
     * @return int
     */
    public function getExpires(): int
    {
        return $this->expires;
    }

    /**
     * Set last session ID (or null for none).
     *
     * @param ?string $sid Last session ID (or null for none)
     *
     * @return LoginTokenEntityInterface
     */
    public function setLastSessionId(?string $sid): LoginTokenEntityInterface
    {
        $this->last_session_id = $sid;
        return $this;
    }

    /**
     * Get last session ID (or null for none).
     *
     * @return ?string
     */
    public function getLastSessionId(): ?string
    {
        return $this->last_session_id;
    }
}
