<?php

/**
 * Entity model for session table
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
use Doctrine\ORM\Mapping as ORM;
use VuFind\Db\Feature\DateTimeTrait;

/**
 * Session
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'session')]
#[ORM\Index(name: 'session_last_used_idx', columns: ['last_used'])]
#[ORM\UniqueConstraint(name: 'session_session_id_idx', columns: ['session_id'])]
#[ORM\Entity]
class Session implements SessionEntityInterface
{
    use DateTimeTrait;

    /**
     * Unique ID.
     *
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'bigint', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    protected int $id;

    /**
     * Session ID.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'session_id', type: 'string', length: 128, nullable: true)]
    protected ?string $sessionId = null;

    /**
     * Session data.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'data', type: 'text', length: 16777215, nullable: true)]
    protected ?string $data = null;

    /**
     * Time session last used.
     *
     * @var int
     */
    #[ORM\Column(name: 'last_used', type: 'integer', nullable: false, options: ['default' => 0])]
    protected int $lastUsed = 0;

    /**
     * Time session is created.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'created', type: 'datetime', nullable: false, options: ['default' => '2000-01-01 00:00:00'])]
    protected DateTime $created;

    /**
     * Constructor.
     */
    public function __construct()
    {
        // Set the default value as a DateTime object
        $this->created = $this->getUnassignedDefaultDateTime();
    }

    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Session Id setter
     *
     * @param ?string $sid Session Id.
     *
     * @return static
     */
    public function setSessionId(?string $sid): static
    {
        $this->sessionId = $sid;
        return $this;
    }

    /**
     * Created setter.
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static
    {
        $this->created = $dateTime;
        return $this;
    }

    /**
     * Set time the session is last used.
     *
     * @param int $lastUsed Time last used
     *
     * @return static
     */
    public function setLastUsed(int $lastUsed): static
    {
        $this->lastUsed = $lastUsed;
        return $this;
    }

    /**
     * Get time when the session was last used.
     *
     * @return int
     */
    public function getLastUsed(): int
    {
        return $this->lastUsed;
    }

    /**
     * Session data setter.
     *
     * @param ?string $data Session data.
     *
     * @return static
     */
    public function setData(?string $data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Get session data.
     *
     * @return ?string
     */
    public function getData(): ?string
    {
        return $this->data;
    }
}
