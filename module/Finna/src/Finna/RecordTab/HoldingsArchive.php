<?php

/**
 * Holdings archive data tab.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2018-2020.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */

namespace Finna\RecordTab;

use VuFind\View\Helper\Root\OpenUrl;
use VuFind\View\Helper\Root\Record;

/**
 * Holdings archive data tab.
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class HoldingsArchive extends \VuFind\RecordTab\AbstractBase
{
    /**
     * OpenUrl helper
     *
     * @var OpenUrl
     */
    protected $openUrlHelper;

    /**
     * OpenUrl helper
     *
     * @var Record
     */
    protected $recordHelper;

    /**
     * Constructor
     *
     * @param Record  $recordHelper  Record helper
     * @param OpenUrl $openUrlHelper OpenUrl helper
     */
    public function __construct(Record $recordHelper, OpenUrl $openUrlHelper)
    {
        $this->openUrlHelper = $openUrlHelper;
        $this->recordHelper = $recordHelper;
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        $driver = $this->getRecordDriver();
        $openUrlActive = ($this->openUrlHelper)($driver, 'holdings');
        $hasLinks = ($this->recordHelper)($driver)->getLinkDetails($openUrlActive);
        return $this->displayManifestationSection()
            || $driver->tryMethod('archiveRequestAllowed')
            || $hasLinks;
    }

    /**
     * Display manifestation information?
     *
     * @return bool
     */
    public function displayManifestationSection()
    {
        $data = $this->driver->tryMethod('getManifestationData');
        return !empty($data['items']);
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'holdings_archive';
    }
}
