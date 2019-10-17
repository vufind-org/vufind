<?php
/**
 * Findind Aid (ArchivesSpace) tab
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2019.
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
 * @author   Michelle Suranofsky <michelle.suranofsky@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
namespace VuFind\RecordTab;

use VuFind\Connection\ArchivesSpaceConnection;

/**
 * Findind Aid (ArchivesSpace) tab
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Michelle Suranofsky <michelle.suranofsky@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class ArchivesSpace extends AbstractBase
{
    /**
     * ArchivesSpace connection
     *
     * @var Connector
     */
    protected $connector;

    /**
     * Constructor
     *
     * @param Connector $wc WorldCat connection
     */
    public function __construct(ArchivesSpaceConnection $archivesSpaceConnection)
    {
        $this->connector = $archivesSpaceConnection;
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Finding Aid';
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->connector->isActive($this->getRecordDriver()->tryMethod('getFindingAids'));
    }

    public function getSummaryInfo()
    {
        return $this->connector->getSummaryInfo($this->getRecordDriver()->tryMethod('getFindingAidUrl')[0]);
    }

    public function makeRequestFor($url)
    {
        return $this->connector->makeRequestFor($url);
    }
}
