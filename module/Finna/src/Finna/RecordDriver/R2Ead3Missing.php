<?php
/**
 * Model for missing R2 records
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
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for missing R2 records
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class R2Ead3Missing extends R2Ead3
{
    /**
     * Does this record contain restricted metadata?
     *
     * @return bool
     */
    public function hasRestrictedMetadata()
    {
        return true;
    }

    /**
     * Is restricted metadata included with the record, i.e. does the user
     * have permissions to access restricted metadata.
     *
     * @return bool
     */
    public function isRestrictedMetadataIncluded()
    {
        return false;
    }

    /**
     * Show organisation menu on record page?
     *
     * @return boolean
     */
    public function showOrganisationMenu()
    {
        return false;
    }
}
