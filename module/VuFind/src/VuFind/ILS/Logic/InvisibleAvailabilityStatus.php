<?php

/**
 * Invisible Availability Status Logic Class
 *
 * PHP version 8
 *
 * Copyright (C) Open Library Foundation 2024.
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
 * @author   Peter Murray <peter@indexdata.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers?s[]=availabilitystatus Wiki
 */

namespace VuFind\ILS\Logic;

/**
 * Invisible Availability Status
 *
 * Items with this availability status will not be visible in the list of holdings.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Peter Murray <peter@indexdata.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers?s[]=availabilitystatus Wiki
 */
class InvisibleAvailabilityStatus extends AvailabilityStatus
{
    /**
     * Check if status should be visible in the holdings tab.
     *
     * @return bool
     */
    public function isVisibleInHoldings(): bool
    {
        return false;
    }
}
