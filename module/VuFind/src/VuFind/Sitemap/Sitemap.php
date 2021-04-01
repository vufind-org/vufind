<?php
/**
 * Class for representing sitemap files
 *
 * PHP version 7
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
     * Set languages to use for the entries
     *
     * @param array $allLanguages All available languages
     *
     * @return void
     */
    public function setLanguages(array $allLanguages): void
    {
        $this->alternativeLanguages = $allLanguages;
        $this->extraNamespaces[] = 'xmlns:xhtml="http://www.w3.org/1999/xhtml"';
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
        $alternativeLinks = '';
        if ($this->alternativeLanguages) {
            $lngParam = strpos($url, '?') === false ? '?lng=' : '&lng=';
            $links = [
                '<xhtml:link rel="alternate" hreflang="x-default">'
                . htmlspecialchars($url)
                . '</xhtml:link>'
            ];
            foreach ($this->alternativeLanguages as $lng) {
                $links[] = '<xhtml:link rel="alternate" hreflang="'
                    . $this->getLanguageAttr($lng) . '">'
                    . htmlspecialchars($url . $lngParam . urlencode($lng))
                    . '</xhtml:link>';
                $parts = explode('-', $lng, 2);
                if (!empty($parts[1])
                    && !in_array($parts[0], $this->alternativeLanguages)
                ) {
                    // Add fallback for non-locale specific language:
                    $links[] = '<xhtml:link rel="alternate" hreflang="'
                        . $this->getLanguageAttr($parts[0]) . '">'
                        . htmlspecialchars($url . $lngParam . urlencode($lng))
                        . '</xhtml:link>';
                }
            }

            $alternativeLinks = '  ' . implode("\n  ", $links) . "\n";
        } else {
            $locs[] = '<loc>' . htmlspecialchars($url) . '</loc>';
        }
        $url = htmlspecialchars($url);
        $freq = htmlspecialchars($this->frequency);
        return "<url>\n"
            . "  <loc>$url</loc>\n"
            . "  <changefreq>$freq</changefreq>\n"
            . $alternativeLinks
            . "</url>\n";
    }

    /**
     * Get a language attribute for alternative language links
     *
     * @param string $language VuFind language code
     *
     * @return string
     */
    protected function getLanguageAttr(string $language): string
    {
        $parts = explode('-', $language, 2);
        if (!empty($parts[1])) {
            return $parts[0] . '-' . strtoupper($parts[1]);
        }
        return $language;
    }
}
