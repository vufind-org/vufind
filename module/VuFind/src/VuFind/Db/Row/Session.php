<?php

/**
 * Row Definition for session
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Row;

use DateTime;
use VuFind\Db\Entity\SessionEntityInterface;

/**
 * Row Definition for session
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int     $id
 * @property ?string $session_id
 * @property string  $data
 * @property int     $last_used
 * @property string  $created
 */
class Session extends RowGateway implements SessionEntityInterface
{
    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'session', $adapter);
    }

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
     * @return static
     */
    public function setSessionId(?string $sid): static
    {
        $this->session_id = $sid;
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
        $this->created = $dateTime->format('Y-m-d H:i:s');
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
        $this->last_used = $lastUsed;
        return $this;
    }

    /**
     * Get time when the session was last used.
     *
     * @return int
     */
    public function getLastUsed(): int
    {
        return $this->last_used;
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
