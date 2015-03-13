<?php
/**
 * Class for representing sitemap files
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
 * Class for representing sitemap files
 *
 * @category VuFind2
 * @package  Sitemap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
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
     * @param string $url URL
     *
     * @return string XML fragment
     */
    protected function getEntry($url)
    {
        $loc = htmlspecialchars($url);
        $freq = htmlspecialchars($this->frequency);
        return '<url>' . "\n"
            . '  <loc>' . $loc . '</loc>' . "\n"
            . '  <changefreq>' . $freq . '</changefreq>' . "\n"
            . '</url>' . "\n";
    }
}