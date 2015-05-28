<?php
/**
 * World Cat Knowledge Base URL Service
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  WorldCat
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Connection;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

/**
 * World Cat Utilities
 *
 * Class for accessing helpful WorldCat APIs.
 *
 * @category VuFind2
 * @package  WorldCat
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class WorldCatKnowledgeBaseUrlService
{
    /**
     * Array of record drivers to look up (keyed by ID).
     *
     * @var array
     */
    protected $queue = [];

    /**
     * URLs looked up from record drivers (keyed by ID).
     *
     * @var array
     */
    protected $cache = [];

    /**
     * Add a record driver to the queue of records we should look up (this allows
     * us to save HTTP requests by looking up many URLs at once on a "just in case"
     * basis).
     *
     * @param RecordDriver $record Record driver
     *
     * @return array
     */
    public function addToQueue(RecordDriver $record)
    {
        $id = $record->getUniqueId();
        if (!isset($this->cache[$id])) {
            $this->queue[$id] = $record;
        }
    }

    /**
     * Retrieve an array of URLs for the provided record driver.
     *
     * @param RecordDriver $record Record driver
     *
     * @return array
     */
    public function getUrls(RecordDriver $record)
    {
        $id = $record->getUniqueId();
        if (!isset($this->cache[$id])) {
            $this->addToQueue($record);
            $this->processQueue();
        }
        return $this->cache[$id];
    }

    /**
     * Support method: process the queue of records waiting to be looked up.
     *
     * @return void
     */
    protected function processQueue()
    {
        // Load URLs for queue (TODO: retrieve real data!)
        $ids = array_keys($this->queue);
        foreach ($ids as $id) {
            $this->cache[$id] = [
                [
                    'url' => 'http://example.com/' . rand(1000, 9999),
                    'desc' => 'Random fake link'
                ]
            ];
        }

        // Clear queue
        $this->queue = [];
    }
}
