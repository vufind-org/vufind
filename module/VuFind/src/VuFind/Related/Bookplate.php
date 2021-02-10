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
     * Bookplate config
     */
    protected $config;

    /**
     * Solr fields
     */
    protected $fields;

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
     * Constructor
     *
     * @param VuFind\Config\PluginManager $configLoader PluginManager
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        $this->configLoader = $configLoader;
        $this->config = $this->configLoader->get('Related');
    }

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
        $this->fields = $driver->getRawData();
        $this->bookplateStrs = $this->getBookplateSolrData('titles');
        $this->bookplateImgNames = $this->getBookplateSolrData('imgNames');
        $this->fullUrlTemplate = $this->getBookplateFullUrlTemplate();
        $this->thumbUrlTemplate = $this->getBookplateThumbUrlTemplate();
        $this->displayTitles = $this->displayBookplateTitles();
    }

    /**
     * Get the bookplate names.
     *
     * @param $s string name of data to retrieve.
     *
     * @return array
     */
    protected function getBookplateSolrData($s)
    {
        $data = [
            'titles' => $this->getBookplateSolrTitlesField(),
            'imgNames' => $this->getBookplateSolrImgNamesField()
        ];
        $field = $data[$s];
        if (!empty($field) && isset($this->fields[$field])) {
            return $this->fields[$field];
        }
        return [];
    }

    /**
     * Get the full bookplate URL string template.
     *
     * @return string
     */
    protected function getBookplateFullUrlTemplate()
    {
        return isset($this->config->Bookplate->bookplate_full) ?
            $this->config->Bookplate->bookplate_full : '';
    }

    /**
     * Get the bookplate URL thumbnail string template.
     *
     * @return string
     */
    protected function getBookplateThumbUrlTemplate()
    {
        return isset($this->config->Bookplate->bookplate_thumb) ?
            $this->config->Bookplate->bookplate_thumb : '';
    }

    /**
     * Display titles under bookplates.
     *
     * @return boolean
     */
    protected function displayBookplateTitles()
    {
        return isset($this->config->Bookplate->bookplate_display_title) ?
            $this->config->Bookplate->bookplate_display_title : true;
    }

    /**
     * Get a Solr field with an array of bookplate image titles.
     *
     * @return string
     */
    protected function getBookplateSolrTitlesField()
    {
        return isset($this->config->Bookplate->bookplate_titles_field) ?
            $this->config->Bookplate->bookplate_titles_field : '';
    }

    /**
     * Get a Solr field with an array of strings that represent the unique
     * part of image names.
     *
     * @return string
     */
    protected function getBookplateSolrImgNamesField()
    {
        return isset($this->config->Bookplate->bookplate_img_names_field) ?
            $this->config->Bookplate->bookplate_img_names_field : '';
    }

    /**
     * Get bookplate details for display.
     *
     * @return array
     */
    public function getBookplateDetails()
    {
        $sameLen = count($this->bookplateStrs) == count($this->bookplateImgNames);
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
