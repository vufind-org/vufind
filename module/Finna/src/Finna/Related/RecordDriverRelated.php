<?php
/**
 * Related Record: record driver based
 *
 * PHP version 7
 *
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
 * @package  Related_Records
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_related_record_module Wiki
 */
namespace Finna\Related;

/**
 * Related Record: record driver based
 *
 * @category VuFind
 * @package  Related_Records
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_related_record_module Wiki
 */
class RecordDriverRelated implements \VuFind\Related\RelatedInterface
{
    /**
     * Records
     *
     * @var array
     */
    protected $results;

    /**
     * Search service
     *
     * @var \VuFind\Record\Loader
     */
    protected $recordLoader;

    /**
     * Constructor
     *
     * @param \VuFind\Search\RecordLoader $recordLoader Record loader
     */
    public function __construct(\VuFind\Record\Loader $recordLoader)
    {
        $this->recordLoader = $recordLoader;
    }

    /**
     * Establishes base settings for retrieving results..
     *
     * @param string                            $settings Settings from config.ini
     * @param \VuFind\RecordDriver\AbstractBase $driver   Record driver object
     *
     * @return void
     */
    public function init($settings, $driver)
    {
        foreach ($driver->getRelatedItems() as $type => $ids) {
            $this->results[$type] = $this->recordLoader->loadBatchForSource(
                $ids, 'Solr', true
            );
        }
    }

    /**
     * Get an array of result records.
     *
     * @return array
     */
    public function getResults()
    {
        return $this->results;
    }
}
