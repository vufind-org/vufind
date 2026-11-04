<?php

/**
 * Entity model for session_data table.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Entity model for session_data table.
 *
 * @category VuFind
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'session_data')]
#[ORM\Index(name: 'session_id_idx', columns: ['session_id'])]
#[ORM\Index(name: 'session_data_session_id_type_index', columns: ['session_id', 'type'])]
#[ORM\Entity]
class SessionData implements SessionDataEntityInterface
{
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
     * @var string
     */
    #[ORM\Column(name: 'session_id', type: 'string', length: 128, nullable: false)]
    protected string $sessionId;

    /**
     * Type.
     *
     * @var string
     */
    #[ORM\Column(name: 'type', type: 'string', length: 50, nullable: false)]
    protected string $type;

    /**
     * Data.
     *
     * @var array
     */
    #[ORM\Column(name: 'data', type: 'json', nullable: false)]
    protected array $data = [];

    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get session identifier.
     *
     * @return string
     */
    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * Set session identifier.
     *
     * @param string $sessionId Session id
     *
     * @return static
     */
    public function setSessionId(string $sessionId): static
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    /**
     * Get type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set type.
     *
     * @param string $type Type
     *
     * @return static
     */
    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Data setter.
     *
     * @param array $data Data.
     *
     * @return static
     */
    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Get data.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}
