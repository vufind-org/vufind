<?php

/**
 * Row Definition for external_session
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2016.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Row;

use DateTime;
use VuFind\Db\Entity\ExternalSessionEntityInterface;

/**
 * Row Definition for external_session
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int    $id
 * @property string $session_id
 * @property string $external_session_id
 * @property string $created
 */
class ExternalSession extends RowGateway implements \VuFind\Db\Entity\ExternalSessionEntityInterface
{
    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'external_session', $adapter);
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
     * Get PHP session id string.
     *
     * @return string
     */
    public function getSessionId(): string
    {
        return $this->session_id ?? '';
    }

    /**
     * Set PHP session id string.
     *
     * @param string $sessionId PHP session id string
     *
     * @return ExternalSessionEntityInterface
     */
    public function setSessionId(string $sessionId): ExternalSessionEntityInterface
    {
        $this->session_id = $sessionId;
        return $this;
    }

    /**
     * Get PHP external session id string.
     *
     * @return string
     */
    public function getExternalSessionId(): string
    {
        return $this->external_session_id ?? '';
    }

    /**
     * Set external session id string.
     *
     * @param string $externalSessionId External session id string
     *
     * @return ExternalSessionEntityInterface
     */
    public function setExternalSessionId(string $externalSessionId): ExternalSessionEntityInterface
    {
        $this->external_session_id = $externalSessionId;
        return $this;
    }

    /**
     * Get created date.
     *
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->created);
    }

    /**
     * Set created date.
     *
     * @param DateTime $dateTime Created date
     *
     * @return ExternalSessionEntityInterface
     */
    public function setCreated(DateTime $dateTime): ExternalSessionEntityInterface
    {
        $this->created = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }
}
