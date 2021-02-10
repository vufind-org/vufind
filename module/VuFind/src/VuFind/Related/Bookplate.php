<?php
/**
 * Related Records: Bookplates
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
 * Related Records: Bookplates
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
     * Data fields (usually Solr)
     */
    protected $fields;

    /**
     * Bookplate strings
     */
    protected $bookplateStrs;

    /**
     * Bookplate image names or full URLs
     */
    protected $bookplateImages;

    /**
     * Bookplate thumbnail image names or thumbnail URLs
     */
    protected $bookplateThumbnails;

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
        $this->bookplateStrs = $this->getBookplateData('titles');
        $this->bookplateImages = $this->getBookplateData('imgFull');
        $this->bookplateThumbnails = $this->getBookplateData('imgThumb');
        $this->fullUrlTemplate = $this->getBookplateFullUrlTemplate();
        $this->thumbUrlTemplate = $this->getBookplateThumbUrlTemplate();
        $this->displayTitles = $this->displayBookplateTitles();
    }

    /**
     * Get an array of data representing bookplates.
     *
     * @param $s string name of data to retrieve.
     *
     * @return array
     */
    protected function getBookplateData($s)
    {
        $data = [
            'titles' => $this->getBookplateTitlesField(),
            'imgThumb' => $this->getBookplateThumbnailsField(),
            'imgFull' => $this->getBookplateFullImagesField(),
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
     * Get a data field with an array of bookplate image titles.
     *
     * @return string
     */
    protected function getBookplateTitlesField()
    {
        return isset($this->config->Bookplate->bookplate_titles_field) ?
            $this->config->Bookplate->bookplate_titles_field : '';
    }

    /**
     * Get a data field with an array of strings that represent full images.
     * These could be the unique parts of image names (e.g. donor code) or
     * full paths to image files.
     *
     * @return string
     */
    protected function getBookplateFullImagesField()
    {
        return isset($this->config->Bookplate->bookplate_images_field) ?
            $this->config->Bookplate->bookplate_images_field : '';
    }

    /**
     * Get a data field with an array of strings that represent thumbnails.
     * These could be the unique parts of thumbnail names (e.g. donor code)
     * or full paths to thumbnail image files.
     *
     * @return string
     */
    protected function getBookplateThumbnailsField()
    {
        return isset($this->config->Bookplate->bookplate_thumbnails_field) ?
            $this->config->Bookplate->bookplate_thumbnails_field : '';
    }

    /**
     * Get bookplate details for display.
     *
     * @return array
     */
    public function getBookplateDetails()
    {
        $sameLen = count($this->bookplateStrs) == count($this->bookplateImages);
        $hasBookplates = !empty($this->bookplateStrs)
            && !empty($this->bookplateImages) && $sameLen;
        if ($hasBookplates) {
            $data = [];
            foreach ($this->bookplateStrs as $i => $bookplate) {
                $tokens = [
                    '%%img%%',
                    '%%thumb%%',
                ];
                $tokenValues = [
                    $this->bookplateImages[$i],
                    $this->bookplateThumbnails[$i],
                ];
                $imgUrl = str_replace(
                    $tokens,
                    $tokenValues,
                    $this->fullUrlTemplate
                );
                $imgThumb = str_replace(
                    $tokens,
                    $tokenValues,
                    $this->thumbUrlTemplate
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
