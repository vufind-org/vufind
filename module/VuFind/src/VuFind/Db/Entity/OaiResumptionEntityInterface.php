<?php

/**
 * Entity model interface for oai_resumption table
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Entity;

use DateTime;

/**
 * Entity model interface for oai_resumption table
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface OaiResumptionEntityInterface extends EntityInterface
{
    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Resumption parameters setter
     *
     * @param ?string $params Resumption parameters.
     *
     * @return static
     */
    public function setResumptionParameters(?string $params): static;

    /**
     * Get resumption parameters.
     *
     * @return ?string
     */
    public function getResumptionParameters(): ?string;

    /**
     * Set token used for identifying.
     *
     * @param string $token Generated token.
     *
     * @return static
     */
    public function setToken(string $token): static;

    /**
     * Get token used for identifying.
     *
     * @return ?string
     */
    public function getToken(): ?string;

    /**
     * Expiry date setter.
     *
     * @param DateTime $dateTime Expiration date
     *
     * @return static
     */
    public function setExpiry(DateTime $dateTime): static;

    /**
     * Get expiry date.
     *
     * @return DateTime
     */
    public function getExpiry(): DateTime;
}
