<?php
/**
 * Model for missing records -- used for saved favorites that have been deleted
 * from the index.
 *
 * PHP version 7
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
namespace VuFind\RecordDriver;

/**
 * Model for missing records -- used for saved favorites that have been deleted
 * from the index.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class Missing extends DefaultRecord
{
    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $mainConfig   VuFind main configuration (omit
     * for built-in defaults)
     * @param \Laminas\Config\Config $recordConfig Record-specific configuration file
     * (omit to use $mainConfig as $recordConfig)
     */
    public function __construct($mainConfig = null, $recordConfig = null)
    {
        $this->sourceIdentifier = 'missing';
        parent::__construct($mainConfig, $recordConfig);
    }

    /**
     * Format the missing title.
     *
     * @return string
     */
    public function determineMissingTitle()
    {
        // If available, use details from ILS:
        $ilsDetails = $this->getExtraDetail('ils_details');
        if (isset($ilsDetails['title'])) {
            return $ilsDetails['title'];
        }

        // If available, load title from database:
        $id = $this->getUniqueId();
        if ($id) {
            $table = $this->getDbTable('Resource');
            $resource = $table
                ->findResource($id, $this->getSourceIdentifier(), false);
            if (!empty($resource) && !empty($resource->title)) {
                return $resource->title;
            }
        }

        // Default -- message about missing title:
        return $this->translate('Title not available');
    }

    /**
     * Get the short title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        $title = parent::getShortTitle();
        return empty($title) ? $this->determineMissingTitle() : $title;
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        $title = parent::getShortTitle();
        return empty($title) ? $this->determineMissingTitle() : $title;
    }

    /**
     * Get an array of all the formats associated with the record.
     *
     * @return array
     */
    public function getFormats()
    {
        return ['other'];
    }
}
