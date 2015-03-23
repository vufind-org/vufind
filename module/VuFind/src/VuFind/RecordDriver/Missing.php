<?php
/**
 * Model for missing records -- used for saved favorites that have been deleted
 * from the index.
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace VuFind\RecordDriver;

/**
 * Model for missing records -- used for saved favorites that have been deleted
 * from the index.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class Missing extends SolrDefault
{
    /**
     * Constructor
     *
     * @param \Zend\Config\Config $mainConfig   VuFind main configuration (omit for
     * built-in defaults)
     * @param \Zend\Config\Config $recordConfig Record-specific configuration file
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
        // If available, load title from database:
        $id = $this->getUniqueId();
        if ($id) {
            $table = $this->getDbTable('Resource');
            $resource = $table
                ->findResource($id, $this->getResourceSource(), false);
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
}
