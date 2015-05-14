<?php
/**
 * Solr Collection aspect of the Search Multi-class (Params)
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
 * @package  Search_SolrAuthor
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search\SolrCollection;

/**
 * Solr Collection Search Options
 *
 * @category VuFind2
 * @package  Search_SolrAuthor
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
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
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return void
     */
    public function initFromRecordDriver($driver)
    {
        $this->collectionID = $driver->getUniqueID();
        if ($hierarchyDriver = $driver->getHierarchyDriver()) {
            switch ($hierarchyDriver->getCollectionLinkType()) {
            case 'All':
                $this->collectionField = 'hierarchy_parent_id';
                break;
            case 'Top':
                $this->collectionField = 'hierarchy_top_id';
                break;
            }
        }

        if (null === $this->collectionID) {
            throw new \Exception('Collection ID missing');
        }
        if (null === $this->collectionField) {
            throw new \Exception('Collection field missing');
        }

        // We don't spellcheck this screen; it's not for free user input anyway
        $options = $this->getOptions();
        $options->spellcheckEnabled(false);

        // Prepare the search
        $safeId = addcslashes($this->collectionID, '"');
        $options->addHiddenFilter($this->collectionField . ':"' . $safeId . '"');
        $options->addHiddenFilter('!id:"' . $safeId . '"');
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
}