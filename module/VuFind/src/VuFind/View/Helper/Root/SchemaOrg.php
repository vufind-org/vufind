<?php

/**
 * View helper for injecting schema.org metadata
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\HtmlAttributes;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

/**
 * View helper for injecting schema.org metadata
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class SchemaOrg extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Constructor
     *
     * @param HtmlAttributes $htmlAttributes HtmlAttributes view helper
     * @param bool           $enabled        Is schema.org metadata enabled?
     */
    public function __construct(
        protected HtmlAttributes $htmlAttributes,
        protected bool $enabled = true
    ) {
    }

    /**
     * Format schema.org attributes (if enabled).
     *
     * @param array $attributes HTML attributes (in key/value format)
     *
     * @return string
     */
    public function getAttributes(array $attributes): string
    {
        return $this->enabled ? ($this->htmlAttributes)($attributes) : '';
    }

    /**
     * Get a schema.org tag (if enabled). Note that this only generates an open (or void)
     * tag; if a close tag is required, you will need to add it yourself separately.
     *
     * @param string $tag        Tag name
     * @param array  $attributes Tag attributes
     *
     * @return string
     */
    public function getTag(string $tag, array $attributes): string
    {
        $attributes = $this->getAttributes($attributes);
        return $attributes ? "<$tag$attributes>" : '';
    }

    /**
     * Create a schema.org link tag (if enabled).
     *
     * @param string $href       Link target
     * @param string $property   Property attribute
     * @param array  $attributes Additional attributes (optional)
     *
     * @return string
     */
    public function getLink(string $href, string $property, array $attributes = []): string
    {
        return $this->getTag('link', compact('href', 'property') + $attributes);
    }

    /**
     * Create a schema.org meta tag (if enabled).
     *
     * @param string $property   Property name
     * @param string $content    Property value
     * @param array  $attributes Additional attributes (optional)
     *
     * @return string
     */
    public function getMeta(string $property, string $content, array $attributes = []): string
    {
        return $this->getTag('meta', compact('property', 'content') + $attributes);
    }

    /**
     * Get all record types for the given record.
     *
     * @param RecordDriver $driver Record Driver
     *
     * @return array
     */
    public function getRecordTypesArray(RecordDriver $driver): array
    {
        return $driver->tryMethod('getSchemaOrgFormatsArray') ?? [];
    }

    /**
     * Get all record types for the given record.
     *
     * @param RecordDriver $driver Record Driver
     *
     * @return string
     */
    public function getRecordTypes(RecordDriver $driver): string
    {
        return implode(' ', $this->getRecordTypesArray($driver));
    }
}
