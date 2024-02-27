<?php

/**
 * Model for Solr web records.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver;

/**
 * Model for Solr web records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class SolrWeb extends SolrDefault
{
    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $mainConfig     VuFind main configuration (omit
     * for built-in defaults)
     * @param \Laminas\Config\Config $recordConfig   Record-specific configuration
     * file (omit to use $mainConfig as $recordConfig)
     * @param \Laminas\Config\Config $searchSettings Search-specific configuration
     * file
     */
    public function __construct(
        $mainConfig = null,
        $recordConfig = null,
        $searchSettings = null
    ) {
        $this->preferredSnippetFields = ['description', 'fulltext'];
        parent::__construct($mainConfig, $recordConfig, $searchSettings);
    }

    /**
     * Get text that can be displayed to represent this record in
     * breadcrumbs.
     *
     * @return string Breadcrumb text to represent this record.
     */
    public function getBreadcrumb()
    {
        return $this->getTitle();
    }

    /**
     * Get the URL for the current record.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->fields['url'];
    }

    /**
     * Get the last modified date for the current record (or empty string if unset).
     *
     * @return string
     */
    public function getLastModified()
    {
        return $this->fields['last_modified'] ?? '';
    }
}
