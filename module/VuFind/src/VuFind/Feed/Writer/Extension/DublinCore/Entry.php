<?php

/**
 * Laminas\Feed\Entry extension for Dublin Core
 *
 * PHP version 8
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
 * @package  Feed_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Feed\Writer\Extension\DublinCore;

use Laminas\Feed\Writer\Extension\ITunes\Entry as ParentEntry;

/**
 * Laminas\Feed\Entry extension for Dublin Core
 *
 * Note: There doesn't seem to be a generic base class for this functionality,
 * and creating a class with no parent blows up due to unexpected calls to
 * Itunes-related functionality. To work around this, we are extending the
 * equivalent Itunes plugin. This works fine, but perhaps in future there will
 * be a more elegant way to achieve the same effect.
 *
 * @category VuFind
 * @package  Feed_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Entry extends ParentEntry
{
    /**
     * Formats
     *
     * @var array
     */
    protected $dcFormats = [];

    /**
     * Date
     *
     * @var string
     */
    protected $dcDate = null;

    /**
     * Add a Dublin Core format.
     *
     * @param string $format Format to add.
     *
     * @return void
     */
    public function addDCFormat($format)
    {
        $this->dcFormats[] = $format;
    }

    /**
     * Set the Dublin Core date.
     *
     * @param string $date Date to set.
     *
     * @return void
     */
    public function setDCDate($date)
    {
        $this->dcDate = $date;
    }

    /**
     * Get the Dublin Core date.
     *
     * @return string
     */
    public function getDCDate()
    {
        return $this->dcDate;
    }

    /**
     * Get the Dublin Core formats.
     *
     * @return array
     */
    public function getDCFormats()
    {
        return $this->dcFormats;
    }
}
