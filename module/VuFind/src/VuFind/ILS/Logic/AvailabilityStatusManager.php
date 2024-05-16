<?php

/**
 * Availability Status Manager
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2024.
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
 * @package  ILS_Logic
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ILS\Logic;

/**
 * Availability Status Manager
 *
 * @category VuFind
 * @package  ILS_Logic
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class AvailabilityStatusManager
{
    /**
     * Create a new Availability Status
     *
     * @param int|bool $availability Availability
     *
     * @return AvailabilityStatusInterface
     */
    public function createAvailabilityStatus(int|bool $availability): AvailabilityStatusInterface
    {
        return new AvailabilityStatus($availability);
    }

    /**
     * Get combined item info of multiple item info arrays.
     *
     * @param array $items Array of items
     *
     * @return array
     */
    public function combine(array $items): array
    {
        if (empty($items)) {
            return ['availability' => new AvailabilityStatus(false)];
        }
        usort($items, function ($a, $b) {
            $availabilityA = $a['availability'] ?? null;
            $availabilityB = $b['availability'] ?? null;
            if ($availabilityA === null && $availabilityB === null) {
                return 0;
            }
            if ($availabilityA === null) {
                return 1;
            }
            if ($availabilityB === null) {
                return -1;
            }
            return $availabilityA->compareTo($availabilityB);
        });
        return $items[0];
    }
}
