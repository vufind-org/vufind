<?php

/**
 * Start page generator plugin
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021.
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
 * @package  Sitemap
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\Sitemap\Plugin;

/**
 * Start page generator plugin
 *
 * @category VuFind
 * @package  Sitemap
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class StartPage extends AbstractGeneratorPlugin
{
    /**
     * Base URL for site
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Set plugin options.
     *
     * @param array $options Options
     *
     * @return void
     */
    public function setOptions(array $options): void
    {
        parent::setOptions($options);
        $this->baseUrl = $options['baseUrl'] ?? '';
    }

    /**
     * Get the name of the sitemap used to create the sitemap file. This will be
     * appended to the configured base name, and may be blank to use the base
     * name without a suffix.
     *
     * @return string
     */
    public function getSitemapName(): string
    {
        return 'pages';
    }

    /**
     * Generate urls for the sitemap.
     *
     * @return \Generator
     */
    public function getUrls(): \Generator
    {
        if ($this->baseUrl) {
            $this->verboseMsg('Adding start page ' . $this->baseUrl);
            yield $this->baseUrl;
        }
    }
}
