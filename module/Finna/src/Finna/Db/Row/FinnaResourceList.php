<?php

/**
 * Row Definition for finna_resource_list
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
 * @package  Db_Row
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Db\Row;

use DateTime;
use Finna\Db\Entity\FinnaResourceListEntityInterface;
use Laminas\Session\Container;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Row\RowGateway;

/**
 * Row Definition for finna_resource_list
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $title
 * @property string $description
 * @property string $created
 * @property string $institution
 * @property string $list_config_identifier
 * @property string $list_type
 * @property string $ordered
 * @property string $pickup_date
 * @property string $connection
 */
class FinnaResourceList extends RowGateway implements
    \VuFind\Db\Service\DbServiceAwareInterface,
    FinnaResourceListEntityInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;
    use \VuFind\Db\Service\DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     * @param ?Container                  $session Session container
     */
    public function __construct($adapter, protected ?Container $session = null)
    {
        // Parents parent
        parent::__construct('id', 'finna_resource_list', $adapter);
    }

    /**
     * Get the ID of the list.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get user
     *
     * @return UserEntityInterface
     */
    public function getUser(): UserEntityInterface
    {
        return $this->getDbService(\VuFind\Db\Service\UserServiceInterface::class)->getUserById($this->user_id);
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
     * Get title.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Set title.
     *
     * @param string $title Title
     *
     * @return static
     */
    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Created setter
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setCreated(Datetime $dateTime): static
    {
        $this->created = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Created getter
     *
     * @return DateTime
     */
    public function getCreated(): Datetime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->created);
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Set description.
     *
     * @param string $description Description
     *
     * @return static
     */
    public function setDescription(string $description = ''): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get the institution.
     *
     * @return string
     */
    public function getInstitution(): string
    {
        return $this->institution;
    }

    /**
     * Set the institution.
     *
     * @param string $institution Institution
     *
     * @return static
     */
    public function setInstitution(string $institution): static
    {
        $this->institution = $institution;
        return $this;
    }

    /**
     * Set list ordered date.
     *
     * @return static
     */
    public function setOrdered(): static
    {
        $this->ordered = (new DateTime())->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Set list pickup date.
     *
     * @param DateTime $pickup_date Pickup date
     *
     * @return static
     */
    public function setPickupDate(DateTime $pickup_date): static
    {
        $this->pickup_date = $pickup_date->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Set list connection.
     *
     * @param string $connection Connection
     *
     * @return static
     */
    public function setConnection(string $connection): static
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Get list connection.
     *
     * @return string
     */
    public function getConnection(): string
    {
        return $this->connection;
    }

    /**
     * Get list type.
     *
     * @return string
     */
    public function getListType(): string
    {
        return $this->list_type;
    }

    /**
     * Set list type.
     *
     * @param string $listType List type
     *
     * @return static
     */
    public function setListType(string $listType): static
    {
        $this->list_type = $listType;
        return $this;
    }

    /**
     * Get list ordered date.
     *
     * @return ?DateTime
     */
    public function getOrdered(): ?DateTime
    {
        return $this->ordered ? DateTime::createFromFormat('Y-m-d H:i:s', $this->ordered) : null;
    }

    /**
     * Get list pickup date.
     *
     * @return ?DateTime
     */
    public function getPickupDate(): ?DateTime
    {
        return $this->pickup_date ? DateTime::createFromFormat('Y-m-d H:i:s', $this->pickup_date) : null;
    }

    /**
     * Get the list configuration identifier.
     *
     * @return string
     */
    public function getListConfigIdentifier(): string
    {
        return $this->list_config_identifier;
    }

    /**
     * Set the list configuration identifier.
     *
     * @param string $listConfigIdentifier List configuration identifier
     *
     * @return static
     */
    public function setListConfigIdentifier(string $listConfigIdentifier): static
    {
        $this->list_config_identifier = $listConfigIdentifier;
        return $this;
    }

    /**
     * Get the external id
     *
     * @return ?string
     */
    public function getExternalId(): ?string
    {
        return $this->external_id;
    }

    /**
     * Set the external id
     *
     * @param ?string $id External id
     *
     * @return static
     */
    public function setExternalId(?string $id): static
    {
        $this->external_id = $id;
        return $this;
    }
}
