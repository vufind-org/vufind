<?php
/**
 * Abstract class for representing XML sitemaps
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Sitemap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Sitemap;

/**
 * Abstract class for representing XML sitemaps
 *
 * @category VuFind2
 * @package  Sitemap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
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
     * @param string $url URL
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
     * @param string $url URL
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
        // Start XML:
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            '<' . $this->topTag . "\n" .
            '   xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n" .
            '   xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n" .
            "   xsi:schemaLocation=\"http://www.sitemaps.org/schemas/sitemap/0.9\n" .
            '   http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . "\n\n";

        // Fill in the guts:
        foreach ($this->urls as $url) {
            $xml .= $this->getEntry($url);
        }

        // Close XML:
        $xml .= '</' . $this->topTag . '>';

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
            mkdir($dirname, 0755, true);
        }
        return file_put_contents($file, $this->toString());
    }
}