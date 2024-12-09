<?php

/**
 * Entity model interface for shortlinks table
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
 * Entity model interface for shortlinks table
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface ShortlinksEntityInterface extends EntityInterface
{
    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Get the path of the URL.
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * Set the path (e.g. /Search/Results?lookfor=foo) of the URL being shortened;
     * shortened URLs are always assumed to be within the hostname where VuFind is running.
     *
     * @param string $path Path
     *
     * @return ShortlinksEntityInterface
     */
    public function setPath(string $path): ShortlinksEntityInterface;

    /**
     * Get shortlinks hash.
     *
     * @return ?string
     */
    public function getHash(): ?string;

    /**
     * Set shortlinks hash.
     *
     * @param ?string $hash Shortlinks hash
     *
     * @return ShortlinksEntityInterface
     */
    public function setHash(?string $hash): ShortlinksEntityInterface;

    /**
     * Get creation timestamp.
     *
     * @return DateTime
     */
    public function getCreated(): DateTime;

    /**
     * Set creation timestamp.
     *
     * @param DateTime $dateTime Creation timestamp
     *
     * @return ShortlinksEntityInterface
     */
    public function setCreated(DateTime $dateTime): ShortlinksEntityInterface;
}
