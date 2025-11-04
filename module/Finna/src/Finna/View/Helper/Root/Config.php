<?php

/**
 * Config view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015-2019.
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
 * @package  View_Helpers
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

/**
 * Config view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Config extends \VuFind\View\Helper\Root\Config
{
    /**
     * Is video embedding on record page enabled
     *
     * @return boolean
     */
    public function inlineVideoEnabled()
    {
        return !empty($this->get('config')->Record->embedVideo);
    }

    /**
     * Get default facet fields
     *
     * @return array
     */
    public function getFacetFields(): array
    {
        $config = $this->get('facets')->Results ?? null;
        return $config ? $config->toArray() : [];
    }

    /**
     * Get default checkbox facets
     *
     * @return array
     */
    public function getCheckboxFacets(): array
    {
        $config = $this->get('facets')->CheckboxFacets ?? null;
        return $config ? $config->toArray() : [];
    }

    /**
     * Is map selection shown
     *
     * @return bool
     */
    public function isGeographicMapVisible(): bool
    {
        return !empty($this->get('facets')->Geographical->map_selection);
    }

    /**
     * Display similar records at the bottom of record view
     * as a carousel
     *
     * @return string
     */
    public function getSimilarRecordsCarouselLocation(): string
    {
        //return $this->get('config')->Record->similar_carousel_display ?? '';
        // Disabled 12.1.2024 due to performance issues
        return '';
    }
}
