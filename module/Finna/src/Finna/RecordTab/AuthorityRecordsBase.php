<?php
/**
 * Base class for Authority records record tabs.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @package  RecordTabs
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
namespace Finna\RecordTab;

use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;

/**
 * Base class for Authority records record tabs.
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
class AuthorityRecordsBase extends \VuFind\RecordTab\AbstractBase
    implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Authority helper
     *
     * @var \Finna\Search\Solr\AuthorityHelper
     */
    protected $authorityHelper;

    /**
     * Records.
     *
     * @var \VuFind\Search\Results
     */
    protected $records = null;

    /**
     * Record driver.
     *
     * @var \Finna\RecordDriver\SolrDefault
     */
    protected $driver;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config                $config          Configuration
     * @param \Finna\Search\Solr\AuthorityHelper $authorityHelper Authority helper
     */
    public function __construct(
        \Zend\Config\Config $config,
        \Finna\Search\Solr\AuthorityHelper $authorityHelper
    ) {
        $this->authorityHelper = $authorityHelper;
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->getNumOfRecords() > 0;
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        $count = $this->getNumOfRecords();
        return $this->translate(
            'authority_records_' . $this->label . '_count', ['%%count%%' => $count]
        );
    }

    /**
     * Load records that are linked to this authority record.
     *
     * @param \VuFind\RecordDriver\DefaultRecord $driver Driver
     *
     * @return array
     */
    public function loadRecords($driver)
    {
        return $this->getRecords();
    }

    /**
     * Get results (records from biblio index).
     *
     * @return \VuFind\Search\Results
     */
    protected function getRecords()
    {
        if ($this->records) {
            return $this->records;
        }
        $this->records = $this->authorityHelper->getRecordsByAuthorityId(
            $this->driver->getUniqueID(), $this->getRelation()
        );
        return $this->records;
    }

    /**
     * Get num of results (records from biblio index).
     *
     * @return int
     */
    protected function getNumOfRecords()
    {
        $records = $this->getRecords();
        return $records->getResultTotal();
    }

    /**
     * Set the record driver to operate on
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return AbstractBase
     */
    public function setRecordDriver(\VuFind\RecordDriver\AbstractBase $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Get search query for returning biblio records by authority.
     *
     * @return string
     */
    public function getSearchQuery()
    {
        return $this->authorityHelper->getRecordsByAuthorityQuery(
            $this->driver->getUniqueID(), $this->getRelation()
        );
    }

    /**
     * Return index fields that is used when listing records.
     *
     * @return string
     */
    protected function getRelation()
    {
        return $this->relation;
    }
}
