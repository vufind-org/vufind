<?php

/**
 * Functionality related to IIIF manifests in records
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025-2026.
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
 * @package  RecordDrivers
 * @author   Ronja Koistinen <ronja.koistinen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace Finna\RecordDriver\Feature;

/**
 * Additional functionality for IIIF in Finna
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ronja Koistinen <ronja.koistinen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
trait IiifAwareTrait
{
    /**
     * Get all IIIF manifests.
     *
     * @return array
     */
    public function getIiifManifests(): array
    {
        return [];
    }

    /**
     * Checks that the content type corresponds to a IIIF Presentation manifest
     *
     * @param string $contentType Content type to check
     *
     * @return bool
     */
    public function isIiifPresentationManifest(string $contentType): bool
    {
        $iiifManifestContentTypeRegex =
            '/application\/ld(\+json)?;profile="http:\/\/iiif\.io\/api\/presentation\/.+\.json"/';
        return preg_match($iiifManifestContentTypeRegex, $contentType) === 1;
    }
}
