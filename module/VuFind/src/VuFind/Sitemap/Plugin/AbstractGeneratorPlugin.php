<?php
/**
 * Base class for sitemap generator plugins
 *
 * PHP version 7
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

use VuFind\Sitemap\Sitemap;

/**
 * Base class for sitemap generator plugins
 *
 * @category VuFind
 * @package  Sitemap
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
abstract class AbstractGeneratorPlugin implements GeneratorPluginInterface
{
    /**
     * Verbose message callback
     *
     * @var callable
     */
    protected $verboseMessageCallback = null;

    /**
     * Set plugin options.
     *
     * @param array $options Options
     *
     * @return void
     */
    public function setOptions(array $options): void
    {
        $this->verboseMessageCallback = $options['verboseMessageCallback'] ?? null;
    }

    /**
     * Get the name of the sitemap used to create the sitemap file.
     *
     * @return string
     */
    abstract public function getSitemapName(): string;

    /**
     * Add urls to the sitemap.
     *
     * @param Sitemap $sitemap Sitemap to add to
     *
     * @return void
     */
    abstract public function addUrls(Sitemap $sitemap): void;

    /**
     * Write a verbose message (if callback is available and configured to do so)
     *
     * @param string $msg Message to display
     *
     * @return void
     */
    protected function verboseMsg(string $msg): void
    {
        if ($this->verboseMessageCallback) {
            ($this->verboseMessageCallback)($msg);
        }
    }
}
