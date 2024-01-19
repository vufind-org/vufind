<?php

/**
 * Abstract class for representing XML sitemaps
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Sitemap;

use function count;
use function dirname;

/**
 * Abstract class for representing XML sitemaps
 *
 * @category VuFind
 * @package  Sitemap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
abstract class AbstractFile
{
    /**
     * Top-level tag.
     *
     * @var string
     */
    protected $topTag;

    /**
     * URLs in map.
     *
     * @var array
     */
    protected $urls = [];

    /**
     * Add a URL to the map.
     *
     * @param string|array $url URL as a string or as an associative array
     *
     * @return void
     */
    public function addUrl($url)
    {
        $this->urls[] = $url;
    }

    /**
     * Translate a URL into an appropriate entry for this sitemap file.
     *
     * @param string|array $url URL as a string or as an associative array
     *
     * @return string XML fragment
     */
    abstract protected function getEntry($url);

    /**
     * Get the map as a string.
     *
     * @return string
     */
    public function toString()
    {
        // Create entries first as they may affect the required namespaces:
        $entries = '';
        foreach ($this->urls as $url) {
            $entries .= $this->getEntry($url);
        }

        // Construct XML:
        $extraNs = $this->getExtraNamespaces();
        $extraNamespaces = $extraNs
            ? ('   ' . implode("\n   ", array_unique($extraNs)) . "\n")
            : '';
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            '<' . $this->topTag . "\n" .
            '   xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n" .
            $extraNamespaces .
            '   xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n" .
            "   xsi:schemaLocation=\"http://www.sitemaps.org/schemas/sitemap/0.9\n" .
            '   http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . "\n\n" .
            $entries .
            '</' . $this->topTag . '>';

        // Send it all back
        return $xml;
    }

    /**
     * Write the map to a file on disk.
     *
     * @param string $file Target filename
     *
     * @return bool
     */
    public function write($file)
    {
        // if a subfolder was specified that does not exist, make one
        $dirname = dirname($file);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0o755, true);
        }
        return file_put_contents($file, $this->toString());
    }

    /**
     * Check if the sitemap is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return !$this->urls;
    }

    /**
     * Get the count of items
     *
     * @return int
     */
    public function getCount(): int
    {
        return count($this->urls);
    }

    /**
     * Remove all entries
     *
     * @return void
     */
    public function clear(): void
    {
        $this->urls = [];
    }

    /**
     * Get any extra namespace declarations needed for the sitemap
     *
     * @return array
     */
    protected function getExtraNamespaces()
    {
        return [];
    }
}
