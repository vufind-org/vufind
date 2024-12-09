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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
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
     * Id getter
     *
     * @return int
     */
    public function getId(): int;

    /**
     * Resumption parameters setter
     *
     * @param ?string $params Resumption parameters.
     *
     * @return OaiResumptionEntityInterface
     */
    public function setResumptionParameters(?string $params): OaiResumptionEntityInterface;

    /**
     * Get resumption parameters.
     *
     * @return ?string
     */
    public function getResumptionParameters(): ?string;

    /**
     * Expiry date setter.
     *
     * @param DateTime $dateTime Expiration date
     *
     * @return OaiResumptionEntityInterface
     */
    public function setExpiry(DateTime $dateTime): OaiResumptionEntityInterface;

    /**
     * Get expiry date.
     *
     * @return DateTime
     */
    public function getExpiry(): DateTime;
}
