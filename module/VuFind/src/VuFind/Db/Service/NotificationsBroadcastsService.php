<?php

/**
 * Database service for notifications broadcasts.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Exception;
use \Laminas\Stdlib\ArrayObject;
use VuFind\Db\Entity\NotificationsBroadcastsEntityInterface;
use VuFind\Db\Table\NotificationsBroadcasts;


/**
 * Database service for notifications broadcasts.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class NotificationsBroadcastsService extends AbstractDbService implements NotificationsBroadcastsServiceInterface
{
    /**
     * Constructor
     *
     * @param NotificationsBroadcasts $broadcasts Notifications broadcasts table object
     */
    public function __construct(protected NotificationsBroadcasts $broadcasts)
    {
    }

    /**
     * Insert a new broadcast into the database or update an existing one..
     *
     * @param ArrayObject $data Data to be written to the database
     * @param array|null $broadcastData Data of an existing broadcast
     * @param string|null $broadcast_id Id of the broadcast to be edited
     *
     * @throws Exception
     */
    public function insertOrUpdateBroadcast(ArrayObject $data, array $broadcastData = null, string $broadcast_id = null): void
    {
        $this->broadcasts->insertOrUpdateBroadcast($data, $broadcastData, $broadcast_id);
    }

    /**
     * Get a list of broadcasts from the database
     *
     * @param array|null $where Filter setting for the request
     * @param string|null $order Order settings for the request
     * @param bool $use_dates
     */
    public function getBroadcastsList(array $where = null, string $order = null, bool $use_dates = true): array
    {
        return $this->broadcasts->getBroadcastsList($where, $order, $use_dates);
    }

    /**
     * Get all data for a broadcast
     *
     * @param mixed $broadcast_id Id of the broadcast
     */
    public function getBroadcastsDataByBroadcastId(mixed $broadcast_id): array
    {
        return $this->broadcasts->getBroadcastsDataByBroadcastId($broadcast_id);
    }

    /**
     * Get a broadcast object by id
     *
     * @param int $id Id of the broadcast
     *
     * @return mixed broadcast object
     */
    public function getBroadcastById(int $id): ?BroadcastsEntityInterface
    {
        return $broadcast = $this->broadcasts->getBroadcastById($id);
    }

    /**
     * Get all broadcast objects with the same broadcast_id
     *
     * @param int $broadcast_id Id of the broadcast
     *
     * @return mixed Array of broadcast objects
     */
    public function getBroadcastsByBroadcastId(int $broadcast_id): ?BroadcastsEntityInterface
    {
        return $this->broadcasts->getBroadcastsByBroadcastId($broadcast_id);
    }

    /**
     * Get a broadcast object by broadcast_id and language
     *
     * @param int    $broadcast_id Id of the broadcast
     * @param string $language     Language of the broadcast
     *
     * @return mixed broadcast object
     */
    public function getBroadcastByBroadcastIdAndLanguage(int $broadcast_id, string $language): ?BroadcastsEntityInterface
    {
        return $this->broadcasts->getBroadcastByBroadcastIdAndLanguage($broadcast_id, $language);
    }

    /**
     * Set the priority of a broadcast
     *
     * @param int $index        New position of the broadcast
     * @param int $broadcast_id Id of the broadcast
     */
    public function setPriorityForBroadcastId(int $index, int $broadcast_id): void
    {
        $this->broadcasts->setPriorityForBroadcastId($index, $broadcast_id);
    }

    /**
     * Set the visibility of a broadcast
     *
     * @param int $visibility   New visibility of the broadcast
     * @param int $broadcast_id Id of the broadcast
     */
    public function setVisibilityForBroadcastId(int $visibility, int $broadcast_id): void
    {
        $this->broadcasts->setVisibilityForBroadcastId($visibility, $broadcast_id);
    }

    /**
     * Set the global visibility of a broadcast
     *
     * @param int $visibility_global New visibility of the broadcast
     * @param int $broadcast_id      Id of the broadcast
     */
    public function setVisibilityGlobalForBroadcastId(int $visibility_global, int $broadcast_id): void
    {
        $this->broadcasts->setVisibilityGlobalForBroadcastId($visibility_global, $broadcast_id);
    }
}
