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

/**
 * Session
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 *
 * @ORM\Table(name="session",
 *          uniqueConstraints={@ORM\UniqueConstraint(name="session_id",
 *                          columns={"session_id"})},
 * indexes={@ORM\Index(name="last_used", columns={"last_used"})})
 * @ORM\Entity
 */
class Session implements EntityInterface
{
    /**
     * Unique ID.
     *
     * @var int
     *
     * @ORM\Column(name="id",
     *          type="bigint",
     *          nullable=false,
     *          options={"unsigned"=true}
     * )
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * Session ID.
     *
     * @var ?string
     *
     * @ORM\Column(name="session_id", type="string", length=128, nullable=true)
     */
    protected $sessionId;

    /**
     * Session data.
     *
     * @var ?string
     *
     * @ORM\Column(name="data", type="text", length=16777215, nullable=true)
     */
    protected $data;

    /**
     * Time session last used.
     *
     * @var int
     *
     * @ORM\Column(name="last_used", type="integer", nullable=false)
     */
    protected $lastUsed = '0';

    /**
     * Time session is created.
     *
     * @var \DateTime
     *
     * @ORM\Column(name="created",
     *          type="datetime",
     *          nullable=false,
     *          options={"default"="2000-01-01 00:00:00"}
     * )
     */
    protected $created = '2000-01-01 00:00:00';

    /**
     * Id getter
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Session Id setter
     *
     * @param ?string $sid Session Id.
     *
     * @return Session
     */
    public function setSessionId(?string $sid): Session
    {
        $this->sessionId = $sid;
        return $this;
    }

    /**
     * Created setter.
     *
     * @param Datetime $dateTime Created date
     *
     * @return Session
     */
    public function setCreated(DateTime $dateTime): Session
    {
        $this->created = $dateTime;
        return $this;
    }

    /**
     * Set time the session is last used.
     *
     * @param int $lastused Time last used
     *
     * @return Session
     */
    public function setLastUsed(int $lastused): Session
    {
        $this->lastUsed = $lastused;
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
     * @return Session
     */
    public function setData(?string $data): Session
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
