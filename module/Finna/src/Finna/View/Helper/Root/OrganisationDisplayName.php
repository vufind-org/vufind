<?php
/**
 * Organisation display name view helper for Solr records.
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
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
class OrganisationDisplayName extends \Laminas\View\Helper\AbstractHelper
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
        $translate = $this->getView()->plugin('translate');

        $locale = $translate->getTranslatorLocale();
        $institutions = (array)$record->tryMethod('getInstitutions', [$locale]);
        $institution = reset($institutions);

        // Case 1: only one building level
        $buildings = $record->tryMethod('getBuilding', [$locale]);
        $building = $buildings[0] ?? '';
        $displayName = $translate($building);

        if (!$fullName && count((array)$buildings) === 1) {
            return $displayName;
        }

        // Case 2: search for institution among building levels,
        // use the first one found
        if ($buildings) {
            foreach ($buildings as $building) {
                if (strpos($building, $institution) !== false) {
                    $displayName = (string)$translate($building);
                    break;
                }
            }
        }

        if (!$datasource = $record->tryMethod('getDataSource')) {
            return $displayName;
        }

        $datasource = $translate("source_$datasource", null, $datasource);

        if ($datasource === $displayName) {
            return $displayName;
        }

        if (empty($displayName)) {
            return $datasource;
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
