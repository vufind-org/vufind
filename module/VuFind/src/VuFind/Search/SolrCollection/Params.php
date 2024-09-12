<?php

/**
 * Solr Collection aspect of the Search Multi-class (Params)
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
 * @package  Search_SolrAuthor
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\SolrCollection;

use function sprintf;

/**
 * Solr Collection Search Options
 *
 * @category VuFind
 * @package  Search_SolrAuthor
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Params extends \VuFind\Search\Solr\Params
{
    /**
     * The field which defines something as being a collection
     * this is usually either hierarchy_parent_id or
     * hierarchy_top_id
     *
     * @var string
     */
    protected $collectionField = null;

    /**
     * The ID of the collection being searched
     *
     * @var string
     */
    protected $collectionID = null;

    /**
     * Pull the search parameters from the query and set up additional options using
     * a record driver representing a collection.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver    Record driver
     * @param bool                              $hasSearch Is the user performing a search?
     *
     * @return void
     */
    public function initFromRecordDriver($driver, bool $hasSearch = false)
    {
        $this->collectionID = $driver->tryMethod('getCollectionSearchId') ?? $driver->getUniqueID();
        if ($hierarchyDriver = $driver->getHierarchyDriver()) {
            $this->collectionField = $hierarchyDriver->getCollectionField($hasSearch);
        }

        if (null === $this->collectionID) {
            throw new \Exception('Collection ID missing');
        }
        if (null === $this->collectionField) {
            throw new \Exception('Collection field missing');
        }

        // We don't spellcheck this screen; it's not for free user input anyway
        $this->getOptions()->spellcheckEnabled(false);

        // Prepare the search
        $safeId = addcslashes($this->collectionID, '"');
        $this->addHiddenFilter($this->collectionField . ':"' . $safeId . '"');
        $this->addHiddenFilter('!id:"' . $safeId . '"');

        // Because the [HiddenFilters] and [RawHiddenFilters] settings for the
        // Solr search backend come from searches.ini and are set up in the
        // AbstractSolrBackendFactory, we need to account for additional ones
        // from Collection.ini here.
        $collectionConfig = $this->configLoader->get('Collection');
        if (isset($collectionConfig->HiddenFilters)) {
            foreach ($collectionConfig->HiddenFilters as $field => $value) {
                $this->addHiddenFilter(sprintf('%s:"%s"', $field, $value));
            }
        }
        if (isset($collectionConfig->RawHiddenFilters)) {
            foreach ($collectionConfig->RawHiddenFilters as $current) {
                $this->addHiddenFilter($current);
            }
        }
    }

    /**
     * Get collection field
     *
     * @return string
     */
    public function getCollectionField()
    {
        return $this->collectionField;
    }

    /**
     * Get collection id
     *
     * @return string
     */
    public function getCollectionId()
    {
        return $this->collectionID;
    }
}
