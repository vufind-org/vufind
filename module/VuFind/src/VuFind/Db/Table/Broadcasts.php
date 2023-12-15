<?php

/**
 * Table Definition for broadcasts
 *
 * PHP version 8
 *
 * Copyright (C) effective WEBWORK GmbH 2023.
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
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Table;

use Exception;
use Laminas\Db\Adapter\Adapter;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\PluginManager as PluginManager;

use function in_array;

/**
 * Table Definition for broadcasts
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Broadcasts extends Gateway
{
    /**
     * Notifications config
     *
     * @var mixed
     */
    protected $config;

    /**
     * Constructor
     *
     * @param Adapter       $adapter       Database adapter
     * @param PluginManager $tm            Table manager
     * @param array         $cfg           Laminas configuration
     * @param mixed         $config        Notifications config
     * @param RowGateway    $rowObj        Row prototype object (null for default)
     * @param string        $table         Name of database table to interface with
     */
    public function __construct(
        Adapter $adapter,
        PluginManager $tm,
        $cfg,
        $config,
        ?RowGateway $rowObj = null,
        $table = 'notifications_broadcasts'
    ) {
        $this->config = $config;
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Insert a new broadcast into the database or update an existing one..
     *
     * @param array $data Data to be written to the database
     * @param array $broadcastData Data of an existing broadcast
     * @param array $broadcast_id Id of the broadcast to be edited
     *
     * @throws Exception
     */
    public function insertOrUpdateBroadcast($data, $broadcastData = null, $broadcast_id = null)
    {
        foreach ($this->config['Notifications']['languages'] as $language) {
            if ($broadcastData['id_' . $language] == null) {
                $broadcast = $this->createRow();
            } else {
                $broadcast = $this->getBroadcastById($broadcastData['id_' . $language]);
            }

            $broadcast->visibility = $data['visibility'];
            $broadcast->visibility_global = $data['visibility_global'];
            $broadcast->priority = $data['priority'];
            $broadcast->author_id = $data['author_id'];
            $broadcast->content = $data['content_' . $language];
            $broadcast->color = $data['color'];
            $broadcast->startdate = $data['startdate'];
            $broadcast->enddate = $data['enddate'];
            $broadcast->change_date = $data['change_date'];
            $broadcast->create_date = $data['create_date'];
            $broadcast->language = $language;

            if ($broadcast_id == 'NEW') {
                $broadcast->save();
                $broadcast_id = $broadcast->getPrimaryKeyId();
            }
            if ($broadcast_id != 'NEW') {
                $broadcast->broadcast_id = $broadcast_id;
            }

            $broadcast->save();
        }
    }

    /**
     * Get a list of broadcasts from the database
     *
     * @param array $where Filter setting for the request
     * @param array $order Order settings for the request
     */
    public function getBroadcastsList($where = null, $order = null, $use_dates = true)
    {
        $callback = function ($select) use ($where, $order, $use_dates) {
            $select->columns(
                [
                    'id' => 'id',
                    'broadcast_id' => 'broadcast_id',
                    'visibility' => 'visibility',
                    'visibility_global' => 'visibility_global',
                    'priority' => 'priority',
                    'author_id' => 'author_id',
                    'content' => 'content',
                    'color' => 'color',
                    'startdate' => 'startdate',
                    'enddate' => 'enddate',
                    'change_date' => 'change_date',
                    'create_date' => 'create_date',
                    'language' => 'language',
                ]
            );

            if (!$order) {
                $select->order(
                    ['priority ASC']
                );
            } else {
                $select->order($order);
            }

            if ($where) {
                $select->where($where);
            }

            if ($use_dates) {
                $today = new \DateTime();
                $select->where->lessThanOrEqualTo('startdate', $today->format('Y-m-d'));
                $select->where->greaterThanOrEqualTo('enddate', $today->format('Y-m-d'));
            }
        };

        $broadcastsList = [];
        foreach ($this->select($callback) as $i) {
            $broadcastsList[] = [
                'id' => $i->id,
                'broadcast_id' => $i->broadcast_id,
                'visibility' => $i->visibility,
                'visibility_global' => $i->visibility_global,
                'priority' => $i->priority,
                'author_id' => $i->author_id,
                'content' => $i->content,
                'color' => $i->color,
                'startdate' => $i->startdate,
                'enddate' => $i->enddate,
                'change_date' => $i->change_date,
                'create_date' => $i->create_date,
            ];
        }
        return $broadcastsList;
    }

    /**
     * Get all data for a broadcast
     *
     * @param array $broadcast_id Id of the broadcast
     */
    public function getBroadcastsDataByBroadcastId($broadcast_id)
    {
        $broadcast_data = [];
        if ($broadcast_id) {
            $broadcasts = $this->select(['broadcast_id' => $broadcast_id]);
            foreach ($broadcasts as $broadcast) {
                foreach ($this->config['Notifications']['languages'] as $language) {
                    if ($broadcast->language == $language) {
                        foreach ($broadcast->toArray() as $key => $value) {
                            if (in_array($key, ['content', 'id'])) {
                                $key = $key . '_' . $language;
                            }
                            if (!isset($broadcast_data[$key])) {
                                $broadcast_data[$key] = $value;
                            }
                        }
                    }
                }
            }
        }
        return $broadcast_data;
    }

    /**
     * Get a broadcast object by id
     *
     * @param int $id Id of the broadcast
     *
     * @return mixed broadcast object
     */
    public function getBroadcastById($id)
    {
        if ($id) {
            return $this->select(['id' => $id])->current();
        }
    }

    /**
     * Get all broadcast objects with the same broadcast_id
     *
     * @param int $broadcast_id Id of the broadcast
     *
     * @return mixed Array of broadcast objects
     */
    public function getBroadcastsByBroadcastId($broadcast_id)
    {
        if ($broadcast_id) {
            return $this->select(['broadcast_id' => $broadcast_id]);
        }
    }

    /**
     * Get a broadcast object by broadcast_id and language
     *
     * @param int $broadcast_id Id of the broadcast
     * @param string $language Language of the broadcast
     *
     * @return mixed broadcast object
     */
    public function getBroadcastByBroadcastIdAndLanguage($broadcast_id, $language)
    {
        if ($broadcast_id && $language) {
            return $this->select(['broadcast_id' => $broadcast_id, 'language' => $language])->current();
        }
    }

    /**
     * Set the priority of a broadcast
     *
     * @param int $index New position of the broadcast
     * @param int $broadcast_id Id of the broadcast
     */
    public function setPriorityForBroadcastId($index, $broadcast_id)
    {
        $broadcasts = $this->getBroadcastsByBroadcastId($broadcast_id);
        foreach ($broadcasts as $broadcast) {
            $broadcast->priority = $index;
            $broadcast->save();
        }
    }

    /**
     * Set the visibility of a broadcast
     *
     * @param int $visibility New visibility of the broadcast
     * @param int $broadcast_id Id of the broadcast
     */
    public function setVisibilityForBroadcastId($visibility, $broadcast_id)
    {
        $broadcasts = $this->getBroadcastsByBroadcastId($broadcast_id);
        foreach ($broadcasts as $broadcast) {
            $broadcast->visibility = $visibility;
            $broadcast->save();
        }
    }

    /**
     * Set the global visibility of a broadcast
     *
     * @param int $visibility_global New visibility of the broadcast
     * @param int $broadcast_id Id of the broadcast
     */
    public function setVisibilityGlobalForBroadcastId($visibility_global, $broadcast_id)
    {
        $broadcasts = $this->getBroadcastsByBroadcastId($broadcast_id);
        foreach ($broadcasts as $broadcast) {
            $broadcast->visibility_global = $visibility_global;
            $broadcast->save();
        }
    }
}
