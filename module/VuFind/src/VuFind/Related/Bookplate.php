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
    protected $bookplateImgNames;

    /**
     * URL template for full bookplate
     */
    protected $fullUrlTemplate;

    /**
     * URL temlate for thumbnail
     */
    protected $thumbUrlTemplate;

    /**
     * Display bookplate titles?
     */
    protected $displayTitles;

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
        $this->bookplateStrs = $driver->getBookplateSolrData('titles');
        $this->bookplateImgNames = $driver->getBookplateSolrData('imgNames');
        $this->fullUrlTemplate = $driver->getBookplateFullUrlTemplate();
        $this->thumbUrlTemplate = $driver->getBookplateThumbUrlTemplate();
        $this->displayTitles = $driver->displayBookplateTitles();
    }

    /**
     * Get bookplate details for display.
     *
     * @return array
     */
    public function getBookplateDetails()
    {
        $sameLen = count($this->bookplateStrs) == count($this->bookplateImgNames)
            ?? false;
        $hasBookplates = !empty($this->bookplateStrs)
            && !empty($this->bookplateImgNames) && $sameLen;
        if ($hasBookplates) {
            $data = [];
            foreach ($this->bookplateStrs as $i => $bookplate) {
                $imgUrl = sprintf(
                    $this->fullUrlTemplate,
                    $this->bookplateImgNames[$i]
                );
                $imgThumb = sprintf(
                    $this->thumbUrlTemplate,
                    $this->bookplateImgNames[$i]
                );
                $data[$i] = ['title' => $bookplate,
                             'fullUrl' => $imgUrl,
                             'thumbUrl' => $imgThumb,
                             'displayTitle' => $this->displayTitles];
            }
            return $data;
        }
        return [];
    }
}
