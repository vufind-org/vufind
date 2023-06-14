<?php

/**
 * Functionality to implement "previous unique ID" behavior.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver\Feature;

/**
 * Functionality to implement "previous unique ID" behavior.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
trait PreviousUniqueIdTrait
{
    /**
     * Previous unique ID (if applicable).
     *
     * @var string
     */
    protected $previousUniqueId = null;

    /**
     * Get previous unique ID (or null if not applicable).
     *
     * @return string
     */
    public function getPreviousUniqueId()
    {
        return $this->previousUniqueId;
    }

    /**
     * Set previous unique ID
     *
     * @param string $id ID to set
     *
     * @return void
     */
    public function setPreviousUniqueId($id)
    {
        $this->previousUniqueId = $id;
    }
}
