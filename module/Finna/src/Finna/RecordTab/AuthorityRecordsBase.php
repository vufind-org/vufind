<?php

/**
 * Base class for Authority records record tabs.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
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
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
abstract class AuthorityRecordsBase extends \VuFind\RecordTab\AbstractBase implements TranslatorAwareInterface
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
     * Record count
     *
     * @var int
     */
    protected $recordCount = null;

    /**
     * Record driver.
     *
     * @var \Finna\RecordDriver\SolrDefault
     */
    protected $driver;

    /**
     * Constructor
     *
     * @param \Finna\Search\Solr\AuthorityHelper $authorityHelper Authority helper
     */
    public function __construct(
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
            'authority_records_' . $this->getLabel() . '_count',
            ['%%count%%' => $count]
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
            $this->driver->getUniqueID(),
            $this->getRelation()
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
        if (null === $this->recordCount) {
            $this->recordCount = $this->records
                ? $this->records->getResultTotal()
                : $this->authorityHelper->getRecordsByAuthorityId(
                    $this->driver->getUniqueID(),
                    $this->getRelation(),
                    true
                );
        }
        return $this->recordCount;
    }

    /**
     * Get search query for returning biblio records by authority.
     *
     * @return string
     */
    public function getSearchQuery()
    {
        return $this->authorityHelper->getRecordsByAuthorityQuery(
            $this->driver->getUniqueID(),
            $this->getRelation()
        );
    }

    /**
     * Get record tab label
     *
     * @return string
     */
    abstract protected function getLabel();

    /**
     * Return index field used when listing records
     *
     * @return string
     */
    abstract protected function getRelation();
}
