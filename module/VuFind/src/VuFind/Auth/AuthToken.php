<?php

/**
 * Class AuthToken
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2021.
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
 * @package  VuFind\Auth
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

declare(strict_types=1);

namespace VuFind\Auth;

/**
 * Class AuthToken
 *
 * @category VuFind
 * @package  VuFind\Auth
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AuthToken
{
    /**
     * Access token
     *
     * @var string
     */
    protected $token;

    /**
     * Token type (usually 'Bearer')
     *
     * @var string
     */
    protected $tokenType;

    /**
     * Number of seconds in token expires
     *
     * @var ?int
     */
    protected $expiresIn;

    /**
     * Timestamp of token creation
     *
     * @var int
     */
    protected $timeCreated;

    /**
     * AuthToken constructor.
     *
     * @param string   $token     Access token string
     * @param int|null $expiresIn Expires in seconds?
     * @param string   $tokenType Type of token
     */
    public function __construct(
        string $token,
        ?int $expiresIn,
        string $tokenType = 'Bearer'
    ) {
        $this->token = $token;
        $this->timeCreated = time();
        $this->expiresIn = $expiresIn ?? null;
        $this->tokenType = $tokenType;
    }

    /**
     * String to be used as Authorization header value
     *
     * @return string
     */
    public function getHeaderValue(): string
    {
        return $this->tokenType . ' ' . $this->token;
    }

    /**
     * To string casting method
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->getHeaderValue();
    }

    /**
     * Is token expired?
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return ($this->timeCreated + $this->expiresIn) <= time();
    }

    /**
     * Return expires in value in seconds
     *
     * @return ?int
     */
    public function getExpiresIn(): ?int
    {
        return $this->expiresIn;
    }
}
