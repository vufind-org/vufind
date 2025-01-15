<?php

/**
 * Table Definition for finna_resource_list_resource
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\Db\Row;

use DateTime;
use Finna\Db\Entity\FinnaResourceListEntityInterface;
use Finna\Db\Entity\FinnaResourceListResourceEntityInterface;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Table Definition for finna_resource_list_resource
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 *
 * @property int     $id
 * @property int     $user_id
 * @property int     $resource_id
 * @property ?int    $list_id
 * @property ?string $notes
 * @property string  $saved
 */
class FinnaResourceListResource extends \VuFind\Db\Row\RowGateway implements FinnaResourceListResourceEntityInterface
{
    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'finna_resource_list_resource', $adapter);
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
     * Set user
     *
     * @param UserEntityInterface $user User entity
     *
     * @return static
     */
    public function setUser(UserEntityInterface $user): static
    {
        $this->user_id = $user->getId();
        return $this;
    }

    /**
     * Get resource id
     *
     * @return int
     */
    public function getResourceId(): int
    {
        return $this->resource_id;
    }

    /**
     * Set resource id from a resource entity
     *
     * @param ResourceEntityInterface $resource Resource entity
     *
     * @return static
     */
    public function setResource(ResourceEntityInterface $resource): static
    {
        $this->resource_id = $resource->getId();
        return $this;
    }

    /**
     * Get list id
     *
     * @return int
     */
    public function getListId(): int
    {
        return $this->list_id;
    }

    /**
     * Set list id
     *
     * @param FinnaResourceListEntityInterface $list List entity
     *
     * @return static
     */
    public function setList(FinnaResourceListEntityInterface $list): static
    {
        $this->list_id = $list->getId();
        return $this;
    }

    /**
     * Created setter
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setSaved(DateTime $dateTime): static
    {
        $this->saved = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Created getter
     *
     * @return DateTime
     */
    public function getSaved(): Datetime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->saved);
    }

    /**
     * Set notes
     *
     * @param string $note Note
     *
     * @return static
     */
    public function setNotes(string $note): static
    {
        $this->notes = $note;
        return $this;
    }

    /**
     * Get notes
     *
     * @return string
     */
    public function getNotes(): string
    {
        return $this->notes;
    }
}
