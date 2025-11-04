<?php

/**
 * Finna resource list
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Db\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use VuFind\Db\Entity\ExchangeArrayTrait;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Feature\DateTimeTrait;

/**
 * Finna resource list
 *
 * @category VuFind
 * @package  Database
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
#[ORM\Table(name: '`finna_resource_list`')]
#[ORM\UniqueConstraint(name: 'user_id', columns: ['user_id'])]
#[ORM\Entity]
class FinnaResourceList implements FinnaResourceListEntityInterface
{
    use DateTimeTrait;
    use ExchangeArrayTrait;

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
     * User.
     *
     * @var UserEntityInterface
     */
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    #[ORM\ManyToOne(targetEntity: UserEntityInterface::class)]
    protected UserEntityInterface $user;

    /**
     * Title.
     *
     * @var string
     */
    #[ORM\Column(name: 'title', type: 'string', length: 200, nullable: false)]
    protected string $title = '';

    /**
     * Description for the list
     *
     * @var ?string
     */
    #[ORM\Column(name: 'description', type: 'text', length: 65535, nullable: true)]
    protected ?string $description = null;

    /**
     * Creation date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'created', type: 'datetime', nullable: false)]
    protected DateTime $created;

    /**
     * Institution
     *
     * @var string
     */
    #[ORM\Column(name: 'institution', type: 'string', length: 200, nullable: false)]
    protected string $institution = '';

    /**
     * List config identifier
     *
     * @var string
     */
    #[ORM\Column(name: 'list_config_identifier', type: 'string', length: 200, nullable: false)]
    protected string $listConfigIdentifier = '';

    /**
     * List type
     *
     * @var string
     */
    #[ORM\Column(name: 'list_type', type: 'string', length: 200, nullable: false)]
    protected string $listType = '';

    /**
     * Ordered date.
     *
     * @var ?DateTime
     */
    #[ORM\Column(name: 'ordered', type: 'datetime', nullable: true)]
    protected ?DateTime $ordered = null;

    /**
     * Pickup date.
     *
     * @var ?DateTime
     */
    #[ORM\Column(name: 'pickup_date', type: 'datetime', nullable: true)]
    protected ?DateTime $pickupDate = null;

    /**
     * External id.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'external_id', type: 'string', length: 255, nullable: true)]
    protected ?string $externalId = null;

    /**
     * Connection.
     *
     * @var string
     */
    #[ORM\Column(name: 'connection', type: 'string', length: 40, nullable: false)]
    protected string $connection = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        // Set the default values as DateTime objects
        $this->created = $this->getUnassignedDefaultDateTime();
        $this->ordered = $this->getUnassignedDefaultDateTime();
        $this->pickupDate = $this->getUnassignedDefaultDateTime();
    }

    /**
     * Get the ID of the list.
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Get user
     *
     * @return UserEntityInterface
     */
    public function getUser(): UserEntityInterface
    {
        return $this->user;
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
        $this->user = $user;
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
    public function setCreated(DateTime $dateTime): static
    {
        $this->created = $this->getNullableDateTimeFromNonNullable($dateTime);
        return $this;
    }

    /**
     * Created getter
     *
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return $this->getNullableDateTimeFromNonNullable($this->created);
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
        $this->ordered = $this->getNullableDateTimeFromNonNullable(new \DateTime());
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
        $this->pickupDate = $this->getNullableDateTimeFromNonNullable($pickup_date);
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
        return $this->listType;
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
        $this->listType = $listType;
        return $this;
    }

    /**
     * Get list ordered date.
     *
     * @return ?DateTime
     */
    public function getOrdered(): ?DateTime
    {
        return $this->getNullableDateTimeFromNonNullable($this->ordered);
    }

    /**
     * Get list pickup date.
     *
     * @return ?DateTime
     */
    public function getPickupDate(): ?DateTime
    {
        return $this->getNullableDateTimeFromNonNullable($this->pickupDate);
    }

    /**
     * Get the list configuration identifier.
     *
     * @return string
     */
    public function getListConfigIdentifier(): string
    {
        return $this->listConfigIdentifier;
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
        $this->listConfigIdentifier = $listConfigIdentifier;
        return $this;
    }

    /**
     * Get the external id
     *
     * @return ?string
     */
    public function getExternalId(): ?string
    {
        return $this->externalId;
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
        $this->externalId = $id;
        return $this;
    }
}
