<?php

/**
 * Class for representing sitemap files
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

use function is_array;

/**
 * Class for representing sitemap files
 *
 * @category VuFind
 * @package  Sitemap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Sitemap extends AbstractFile
{
    public const XHTML_NAMESPACE = 'xmlns:xhtml="http://www.w3.org/1999/xhtml"';

    /**
     * Frequency of URL updates (always, daily, weekly, monthly, yearly, never)
     *
     * @var string
     */
    protected $frequency;

    /**
     * Alternative languages
     *
     * @var array
     */
    protected $alternativeLanguages = [];

    /**
     * Whether the XHTML namespace is needed
     *
     * @var bool
     */
    protected $xhtmlNamespaceNeeded = false;

    /**
     * Constructor
     *
     * @param string $frequency Frequency of URL updates
     */
    public function __construct($frequency = 'weekly')
    {
        $this->topTag = 'urlset';
        $this->frequency = $frequency;
    }

    /**
     * Translate a URL into an appropriate entry for this sitemap file.
     *
     * @param string|array $url URL as a string or as an associative array
     *
     * @return string XML fragment
     */
    protected function getEntry($url)
    {
        if (is_array($url)) {
            $link = $url['url'];
            $languages = $url['languages'] ?? [];
            $frequency = $url['frequency'] ?? '';
            $lastmod = $url['lastmod'] ?? '';
        } else {
            $link = $url;
            $languages = [];
            $frequency = '';
            $lastmod = '';
        }
        $alternativeLinks = '';
        if ($languages) {
            $lngParam = !str_contains($link, '?') ? '?lng=' : '&lng=';
            $links = [];
            foreach ($languages as $sitemapLng => $vufindLng) {
                $lngLink = $vufindLng
                    ? $link . $lngParam . urlencode($vufindLng) : $link;
                $links[] = '<xhtml:link rel="alternate" hreflang="'
                    . htmlspecialchars($sitemapLng) . '">'
                    . htmlspecialchars($lngLink)
                    . '</xhtml:link>';
            }

            $alternativeLinks = '  ' . implode("\n  ", $links) . "\n";
            $this->xhtmlNamespaceNeeded = true;
        } else {
            $locs[] = '<loc>' . htmlspecialchars($link) . '</loc>';
        }
        $link = htmlspecialchars($link);
        $freq = htmlspecialchars($frequency ?: $this->frequency);
        $lastmod = htmlspecialchars($lastmod);
        return "<url>\n"
            . "  <loc>$link</loc>\n"
            . "  <changefreq>$freq</changefreq>\n"
            . ($lastmod ? "  <lastmod>$lastmod</lastmod>\n" : '')
            . $alternativeLinks
            . "</url>\n";
    }

    /**
     * Get any extra namespace declarations needed for the sitemap
     *
     * @return array
     */
    protected function getExtraNamespaces()
    {
        $result = parent::getExtraNamespaces();
        if ($this->xhtmlNamespaceNeeded) {
            $result[] = Sitemap::XHTML_NAMESPACE;
        }
        return $result;
    }
}
