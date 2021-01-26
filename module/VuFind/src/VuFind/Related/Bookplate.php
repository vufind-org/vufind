<?php
/**
 * Related Records: Solr-based similarity
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2009.
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
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:related_records_modules Wiki
 */
namespace VuFind\Related;

/**
 * Related Records: Solr-based similarity
 *
 * @category VuFind
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:related_records_modules Wiki
 */
class Bookplate implements RelatedInterface
{
    /**
     * Bookplate strings
     */
    protected $bookplateStrs;

    /**
     * Bookplate URLs
     */
    protected $bookplateUrls;

    /**
     * TEMPORARY
     */
    protected $baseUrl;

    /**
     * Establishes base settings for bookplates.
     *
     * @param string                            $settings Settings from config.ini
     * @param \VuFind\RecordDriver\AbstractBase $driver   Record driver object
     *
     * @return void
     */
    public function init($settings, $driver)
    {
        $this->bookplateUrls = $driver->getBookplateUrls();
        $this->bookplateStrs = $driver->getBookplateStrings();

        // TEMPORARY
        $this->baseUrl = 'http://www.lib.uchicago.edu/bookplates/';
    }

    /**
     * Get the bookplate URL.
     *
     * @param $i int
     * 
     * @return string
     */
    protected function getBookplateUrl($i)
    {
        return $this->baseUrl . strtolower($this->bookplateUrls[$i]) . '-full.jpg'; 
    }

    /**
     * Combine data into a single array.
     *
     * @return array
     */
    protected function getData()
    {
        $sameLen = count($this->bookplateStrs) == count($this->bookplateUrls)
            ?? false;
        $hasBookplates = !empty($this->bookplateStrs)
            && !empty($this->bookplateUrls) && $sameLen;
        if ($hasBookplates) {
            $data = [];
            foreach ($this->bookplateStrs as $i => $bookplate) {
                $imgUrl = $this->getBookplateUrl($i);
                $data[$i] = [$bookplate, $imgUrl];
            }
            return $data;
        }
        return [];
    }

    /**
     * Get array of bookplate string, url pairs
     * to display in the template.
     *
     * @return array
     */
    public function getBookplates()
    {
        return $this->getData();
    }
}
