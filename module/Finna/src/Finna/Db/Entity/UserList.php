<?php

/**
 * Entity model for user_list table
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
 * Entity model for user_list table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Entity]
class UserList extends \VuFind\Db\Entity\UserList implements UserListEntityInterface
{
    /**
     * Date of last update.
     *
     * @var ?DateTime
     */
    #[ORM\Column(name: 'finna_updated', type: 'datetime', nullable: true)]
    protected ?DateTime $finnaUpdated = null;

    /**
     * Protection status.
     *
     * @var bool
     */
    #[ORM\Column(name: 'finna_protected', type: 'boolean', nullable: false, options: ['default' => false])]
    protected bool $finnaProtected = false;

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
     * Last update date setter
     *
     * @param ?DateTime $dateTime Last updated
     *
     * @return static
     */
    public function setFinnaUpdated(?DateTime $dateTime): static
    {
        $this->finnaUpdated = $dateTime;
        return $this;
    }

    /**
     * Last update date getter
     *
     * @return ?DateTime
     */
    public function getFinnaUpdated(): ?DateTime
    {
        return $this->finnaUpdated;
    }
}
