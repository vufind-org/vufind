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
     * Get combined availability of multiple availability status.
     *
     * @param array $availabilities Array of Availability Statuses
     *
     * @return AvailabilityStatusInterface
     */
    public function combine(array $availabilities): AvailabilityStatusInterface
    {
        if (empty($availabilities)) {
            return new AvailabilityStatus(false);
        }
        usort($availabilities, function ($a, $b) {
            return $a->compareTo($b);
        });
        return $availabilities[0];
    }
}
