<?php

/**
 * List Item Selection.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2024.
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  Action
 * @author   David Lahm <lahm@uni-frankfurt.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Action;

use function in_array;

/**
 * List Item Selection.
 *
 * @category VuFind
 * @package  Action
 * @author   David Lahm <lahm@uni-frankfurt.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait ListItemSelectionTrait
{
    /**
     * Get selected ids.
     *
     * @return array
     */
    protected function getSelectedIds()
    {
        // Values may be stored as a default state (checked_default), a list of IDs that do not
        // match the default state (non_default_ids), and a list of all IDs (all_ids_global). If these
        // values are found, we need to calculate the selected list from them.
        $checkedDefault = $this->getPostParam('checked_default') !== null;
        $nonDefaultIds = $this->getPostParam('non_default_ids');
        $allIdsGlobal = $this->getPostParam('all_ids_global', '[]');
        if ($nonDefaultIds !== null) {
            $nonDefaultIds = json_decode($nonDefaultIds);
            return array_values(array_filter(
                json_decode($allIdsGlobal),
                function ($id) use ($checkedDefault, $nonDefaultIds) {
                    $nonDefaultId = in_array($id, $nonDefaultIds);
                    return $checkedDefault xor $nonDefaultId;
                }
            ));
        }
        // If we got this far, values were passed in a simpler format: a list of checked IDs (ids),
        // a list of all IDs on the current page (idsAll), and whether the whole page is
        // selected (selectAll):
        return null === $this->getPostParam('selectAll')
            ? $this->getPostParam('ids', [])
            : $this->getPostParam('idsAll', []);
    }
}
