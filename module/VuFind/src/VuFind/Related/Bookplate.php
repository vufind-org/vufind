<?php

/**
 * Related Records: Bookplates
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
 * @author   Brad Busenius <bbusenius@uchicago.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:related_records_modules Wiki
 */

namespace VuFind\Related;

/**
 * Related Records: Bookplates
 *
 * @category VuFind
 * @package  Related_Records
 * @author   Brad Busenius <bbusenius@uchicago.edu>
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
     * URL template for thumbnail
     */
    protected $thumbUrlTemplate;

    /**
     * Display bookplate titles?
     */
    protected $displayTitles;

    /**
     * Configuration loader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configLoader;

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader PluginManager
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        $this->configLoader = $configLoader;
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
        $config = array_map('trim', explode(':', $settings));
        $configFile = !empty($config[0]) ? $config[0] : 'config';
        $configSection = !empty($config[1]) ? $config[1] : 'Record';
        $this->config = $this->configLoader->get($configFile)->$configSection;
        $this->fields = $driver->getRawData();
        $this->bookplateStrs = $this->getBookplateData(
            $this->getBookplateTitlesField()
        );
        $this->bookplateImages = $this->getBookplateData(
            $this->getBookplateFullImagesField()
        );
        $this->bookplateThumbnails = $this->getBookplateData(
            $this->getBookplateThumbnailsField()
        );
        $this->fullUrlTemplate = $this->getBookplateFullUrlTemplate();
        $this->thumbUrlTemplate = $this->getBookplateThumbUrlTemplate();
        $this->displayTitles = $this->displayBookplateTitles();
    }

    /**
     * Get an array of data representing bookplates.
     *
     * @param $field string name of data to retrieve.
     *
     * @return array
     */
    protected function getBookplateData($field)
    {
        if (!empty($field) && isset($this->fields[$field])) {
            return (array)$this->fields[$field];
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
        return $this->config->bookplate_full ?? '';
    }

    /**
     * Get the bookplate URL thumbnail string template.
     *
     * @return string
     */
    protected function getBookplateThumbUrlTemplate()
    {
        return $this->config->bookplate_thumb ?? '';
    }

    /**
     * Display titles under bookplates.
     *
     * @return boolean
     */
    protected function displayBookplateTitles()
    {
        return $this->config->bookplate_display_title ?? true;
    }

    /**
     * Get a data field with an array of bookplate image titles.
     *
     * @return string
     */
    protected function getBookplateTitlesField()
    {
        return $this->config->bookplate_titles_field ?? '';
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
        return $this->config->bookplate_images_field ?? '';
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
        return $this->config->bookplate_thumbnails_field ?? '';
    }

    /**
     * Get bookplate details for display.
     *
     * @return array
     */
    public function getBookplateDetails()
    {
        $hasBookplates = !empty($this->bookplateStrs);
        if ($hasBookplates) {
            $data = [];
            foreach ($this->bookplateStrs as $i => $bookplate) {
                $tokens = [
                    '%%img%%',
                    '%%thumb%%',
                ];
                $tokenValues = [
                    $this->bookplateImages[$i] ?? '',
                    $this->bookplateThumbnails[$i] ?? '',
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
