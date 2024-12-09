<?php

/**
 * Class for representing sitemap index files
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

/**
 * Class for representing sitemap index files
 *
 * @category VuFind
 * @package  Sitemap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SitemapIndex extends AbstractFile
{
    /**
     * Last modification date of sitemaps.
     *
     * @var string
     */
    protected $lastmod;

    /**
     * Constructor
     *
     * @param string $lastmod Last modification date of sitemaps.
     */
    public function __construct($lastmod = null)
    {
        $this->lastmod = empty($lastmod) ? date('Y-m-d') : $lastmod;
        $this->topTag = 'sitemapindex';
    }

    /**
     * Translate a URL into an appropriate entry for this sitemap file.
     *
     * @param string $url URL
     *
     * @return string XML fragment
     */
    protected function getEntry($url)
    {
        $loc = htmlspecialchars($url);
        $lastmod = htmlspecialchars($this->lastmod);
        return '  <sitemap>' . "\n"
            . '    <loc>' . $loc . '</loc>' . "\n"
            . '    <lastmod>' . $lastmod . '</lastmod>' . "\n"
            . '  </sitemap>' . "\n";
    }
}
