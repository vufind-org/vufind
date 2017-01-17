<?php
/**
 * Organisation display name view helper for Solr records.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

/**
 * Organisation display name view helper for Solr records.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class OrganisationDisplayName extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Get translated organisation display name.
     *
     * @param \VuFind\RecordDriver\AbstractBase $record   Record
     * @param boolean                           $fullName Return full
     * name with datasource
     *
     * @return string
     */
    public function __invoke($record, $fullName = false)
    {
        $translator = $this->getView()->plugin('TransEsc');

        $institutions = $record->tryMethod('getInstitutions');
        $institution = reset($institutions);

        // Case 1: only one building level
        $buildings = $record->getBuilding();
        $building = $buildings[0];
        $displayName = $translator->__invoke($building, null, $building);

        if (!$fullName && count($buildings) === 1) {
            return $displayName;
        }

        // Case 2: search for institution among building levels,
        // use the first one found
        foreach ($buildings as $building) {
            if (strpos($building, $institution) !== false) {
                $displayName = $translator->__invoke($building, null, $building);
                break;
            }
        }

        if (!$datasource = $record->getDataSource()) {
            return $displayName;
        }

        $datasource
            = $translator->__invoke("source_$datasource", null, $datasource);

        if ($datasource === $displayName) {
            return $displayName;
        }

        $pos = strpos($datasource, $displayName);
        if ($fullName && $pos === false) {
            // Datasource name differs from building name:
            // include both for a full display name.
            return "$displayName / $datasource";
        }

        // Building name is duplicated in datasource name.
        // Use datasource name if building name begins with it. 
        return $pos === 0 ? $datasource : $displayName;
    }
}
