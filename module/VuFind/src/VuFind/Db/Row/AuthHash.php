<?php

/**
 * Row Definition for auth_hash
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2019.
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
use VuFind\Db\Entity\AuthHashEntityInterface;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;

/**
 * Row Definition for auth_hash
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
 * @property string $hash
 * @property string $type
 * @property string $data
 * @property string $created
 */
class AuthHash extends RowGateway implements AuthHashEntityInterface, DbServiceAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;
    use DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'auth_hash', $adapter);
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
     * @return ?string
     */
    public function getSessionId(): ?string
    {
        return $this->session_id ?? null;
    }

    /**
     * Set PHP session id string.
     *
     * @param ?string $sessionId PHP Session id string
     *
     * @return AuthHashEntityInterface
     */
    public function setSessionId(?string $sessionId): AuthHashEntityInterface
    {
        $this->session_id = $sessionId;
        return $this;
    }

    /**
     * Get hash value.
     *
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash ?? '';
    }

    /**
     * Set hash value.
     *
     * @param string $hash Hash Value
     *
     * @return AuthHashEntityInterface
     */
    public function setHash(string $hash): AuthHashEntityInterface
    {
        $this->hash = $hash;
        return $this;
    }

    /**
     * Get type of hash.
     *
     * @return ?string
     */
    public function getHashType(): ?string
    {
        return $this->type ?? null;
    }

    /**
     * Set type of hash.
     *
     * @param ?string $type Hash Type
     *
     * @return AuthHashEntityInterface
     */
    public function setHashType(?string $type): AuthHashEntityInterface
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get data.
     *
     * @return ?string
     */
    public function getData(): ?string
    {
        return $this->__get('data');
    }

    /**
     * Set data.
     *
     * @param ?string $data Data
     *
     * @return AuthHashEntityInterface
     */
    public function setData(?string $data): AuthHashEntityInterface
    {
        $this->__set('data', $data);
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
     * @return AuthHashEntityInterface
     */
    public function setCreated(DateTime $dateTime): AuthHashEntityInterface
    {
        $this->created = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }
}
