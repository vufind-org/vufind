<?php

/**
 * Related Record: record driver based
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2019-2020.
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
 * @package  Related_Records
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:related_records_modules Wiki
 */

namespace Finna\Related;

/**
 * Related Record: record driver based
 *
 * @category VuFind
 * @package  Related_Records
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:related_records_modules Wiki
 */
class RecordDriverRelated implements \VuFind\Related\RelatedInterface
{
    /**
     * Record driver
     *
     * @var \VuFind\RecordDriver\AbstractBase
     */
    protected $driver = null;

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
        $this->driver = $driver;
    }

    /**
     * Check if the current record has related records.
     *
     * @return bool
     */
    public function hasRelatedRecords()
    {
        if (!$this->driver) {
            return false;
        }
        return $this->driver->tryMethod('hasRelatedRecords', [], false);
    }

    /**
     * Get the current record ID
     *
     * @return string
     */
    public function getRecordId()
    {
        if (!$this->driver) {
            return '';
        }
        return $this->driver->getUniqueID();
    }

    /**
     * Get the current record source
     *
     * @return string
     */
    public function getRecordSource()
    {
        if (!$this->driver) {
            return '';
        }
        return $this->driver->getSourceIdentifier();
    }
}
