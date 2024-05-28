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
use VuFind\Db\Entity\SessionEntityInterface;
use VuFind\Db\Service\SessionServiceInterface;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;

/**
 * Row Definition for external_session
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ExternalSession extends RowGateway implements \VuFind\Db\Entity\ExternalSessionEntityInterface,DbServiceAwareInterface
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
     * Get session.
     *
     * @return ?SessionEntityInterface
     */
    public function getSession(): ?SessionEntityInterface
    {
        return $this->session_id
            ? $this->getDbServiceManager()->get(SessionServiceInterface::class)->getSessionById($this->session_id)
            : null;
    }

    /**
     * Set session.
     *
     * @param ?SessionEntityInterface $session Session
     *
     * @return ExternalSessionEntityInterface
     */
    public function setSession(?SessionEntityInterface $session): ExternalSessionEntityInterface
    {
        $this->session_id = $session->getId();
        return $this;
    }

    /**
     * Get external session.
     *
     * @return ?SessionEntityInterface
     */
    public function getExternalSession(): ?SessionEntityInterface
    {
        return $this->session_id
            ? $this->$this->getDbServiceManager()->get(SessionServiceInterface::class)->getSessionById($this->session_id)
            : null;
    }

    /**
     * Set external session.
     *
     * @param ?SessionEntityInterface $session Session
     *
     * @return ExternalSessionEntityInterface
     */
    public function setExternalSession(?SessionEntityInterface $session): ExternalSessionEntityInterface
    {
        $this->session_id = $session->getId();
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
