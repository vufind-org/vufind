<?php

/**
 * Multi Page Selection
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2023.
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
 * @package  Controller_Plugins
 * @author   David Lahm <lahm@uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Feature;

use function in_array;

/**
 * Multi Page Selection
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   David Lahm <lahm@uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait MultiPageSelectionTrait
{
    /**
     * Get selected ids
     *
     * @return array
     */
    protected function getSelectedIds()
    {
        $checkedDefault = $this->params()->fromPost('checked_default') !== null;
        $nonDefaultIds = $this->params()->fromPost('non_default_ids');
        $mpsAllIds = $this->params()->fromPost('mps_all_ids', '[]');
        if ($nonDefaultIds !== null) {
            $nonDefaultIds = json_decode($nonDefaultIds);
            return array_values(array_filter(
                json_decode($mpsAllIds),
                function ($id) use ($checkedDefault, $nonDefaultIds) {
                    $nonDefaultId = in_array($id, $nonDefaultIds);
                    return $checkedDefault ^ $nonDefaultId;
                }
            ));
        }
        return null === $this->params()->fromPost('selectAll')
            ? $this->params()->fromPost('ids')
            : $this->params()->fromPost('idsAll');
    }
}
