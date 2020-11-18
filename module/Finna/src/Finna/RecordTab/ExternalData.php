<?php
/**
 * SolrEad3 External data tab.
 *
 * PHP version 7
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

/**
 * SolrEad3 External data tab.
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
class ExternalData extends \VuFind\RecordTab\AbstractBase
{
    /**
     * Is this tab enabled?
     *
     * @var bool
     */
    protected $enabled;

    /**
     * Constructor
     *
     * @param bool $enabled is this tab enabled?
     */
    public function __construct($enabled = true)
    {
        $this->enabled = $enabled;
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        if (empty($this->driver->tryMethod('getExternalData'))) {
            $this->enabled = false;
        }
        return $this->enabled;
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'external_data';
    }
}
