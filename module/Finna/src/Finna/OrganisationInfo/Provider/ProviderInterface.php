<?php

/**
 * Interface for querying organisation info databases.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @package  Content
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\OrganisationInfo\Provider;

/**
 * Interface for querying organisation info databases.
 *
 * @category VuFind
 * @package  Content
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
interface ProviderInterface
{
    /**
     * Check if a consortium is found in organisation info and return basic information
     *
     * @param string $language Language
     * @param string $id       Parent organisation ID
     *
     * @return array Associative array with 'id', 'logo' and 'name'
     */
    public function lookup(string $language, string $id): array;

    /**
     * Get consortium information (includes list of locations)
     *
     * @param string $language       Language
     * @param string $id             Parent organisation ID
     * @param array  $locationFilter Optional list of locations to include
     *
     * @return array
     */
    public function getConsortiumInfo(string $language, string $id, array $locationFilter = []): array;

    /**
     * Get location details
     *
     * @param string  $language   Language
     * @param string  $id         Parent organisation ID
     * @param string  $locationId Location ID
     * @param ?string $startDate  Start date (YYYY-MM-DD) of opening times (default is Monday of current week)
     * @param ?string $endDate    End date (YYYY-MM-DD) of opening times (default is Sunday of start date week)
     *
     * @return array
     */
    public function getDetails(
        string $language,
        string $id,
        string $locationId,
        ?string $startDate = null,
        ?string $endDate = null
    ): array;
}
