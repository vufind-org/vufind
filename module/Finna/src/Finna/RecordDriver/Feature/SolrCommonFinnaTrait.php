<?php

/**
 * Additional functionality for Finna Solr and Finna SolrAuth records.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library 2019-2022.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace Finna\RecordDriver\Feature;

use VuFind\I18n\Locale\LocaleSettings;

use function is_array;

/**
 * Additional functionality for Finna Solr and Finna SolrAuth records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 *
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
trait SolrCommonFinnaTrait
{
    use FinnaRecordTrait;

    /**
     * Date Converter
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter = null;

    /**
     * Video Handler
     *
     * @var \Finna\Video\Video
     */
    protected $videoHandler = null;

    /**
     * Locale settings
     *
     * @var LocaleSettings
     */
    protected $localeSettings = null;

    /**
     * Attach date converter
     *
     * @param \VuFind\Date\Converter $dateConverter Date Converter
     *
     * @return void
     */
    public function attachDateConverter($dateConverter)
    {
        $this->dateConverter = $dateConverter;
    }

    /**
     * Attach video handler
     *
     * @param \Finna\Video\Video $videoHandler Video Handler
     *
     * @return void
     */
    public function attachVideoHandler(\Finna\Video\Video $videoHandler)
    {
        $this->videoHandler = $videoHandler;
    }

    /**
     * Attach locale settings to the driver.
     *
     * @param LocaleSettings $localeSettings Locale Settings
     *
     * @return void
     */
    public function attachLocaleSettings(LocaleSettings $localeSettings)
    {
        $this->localeSettings = $localeSettings;
    }

    /**
     * Sanitize HTML.
     * If validation is enabled and the stripped HTML is invalid,
     * all tags are stripped.
     *
     * @param string  $html      HTML
     * @param string  $allowTags Allowed tags
     * @param boolean $validate  Validate output?
     *
     * @return array
     */
    protected function sanitizeHTML(
        $html,
        $allowTags = '<h1><h2><h3><h4><h5><b><i>',
        $validate = true
    ) {
        $result = strip_tags($html, $allowTags);

        if ($validate) {
            libxml_use_internal_errors(true);
            $doc = new \DOMDocument();
            $doc->loadXML("<body>{$result}</body>");
            if (libxml_get_errors()) {
                // Invalid HTML, strip all tags
                $result = strip_tags($html);
            }
            libxml_clear_errors();
        }

        return $result;
    }

    /**
     * Returns the mapped copyright if the copyright has been mapped to another
     * copyright in configuration, otherwise returns the original copyright.
     *
     * @param string $copyright Copyright
     *
     * @return string
     */
    public function getMappedRights($copyright)
    {
        return $this->mainConfig['RightsMap'][mb_strtoupper($copyright, 'UTF-8')]
            ?? $copyright;
    }

    /**
     * Return URL to copyright information.
     *
     * @param string $copyright Copyright
     * @param string $language  Language
     *
     * @return mixed URL or false if no URL for the given copyright
     */
    public function getRightsLink($copyright, $language)
    {
        $copyright = mb_strtoupper($copyright, 'UTF-8');
        if (isset($this->mainConfig['ImageRights'][$language][$copyright])) {
            return $this->mainConfig['ImageRights'][$language][$copyright];
        }
        return false;
    }

    /**
     * Return an array of image URLs associated with this record with keys:
     * - urls        Image URLs
     *   - small     Small image (mandatory)
     *   - medium    Medium image (mandatory)
     *   - large     Large image (optional)
     * - description Description text
     * - rights      Rights
     *   - copyright   Copyright (e.g. 'CC BY 4.0') (optional)
     *   - description Human readable description (array)
     *   - link        Link to copyright info
     *
     * @param string $language   Language for copyright information
     * @param bool   $includePdf Whether to include first PDF file when no image
     * links are found
     *
     * @return array
     */
    public function getAllImages($language = 'fi', $includePdf = true)
    {
        return [];
    }

    /**
     * Returns an array of parameter to send to Finna's cover generator.
     * Falls back to VuFind's getThumbnail if no record image with the
     * given index was found.
     *
     * @param string $size  Size of thumbnail
     * @param int    $index Image index
     *
     * @return array|bool
     */
    public function getRecordImage($size = 'small', $index = 0)
    {
        if ($images = $this->getAllImages()) {
            $image = $images[$index]['urls'][$size] ?? null;
            $cacheSize = $images[$index]['cacheSizes'][$size] ?? $size;
            if ($image) {
                $params = $images[$index]['urls'][$size];
                if (!is_array($params)) {
                    $params = [
                        'url' => $params,
                    ];
                }
                if ($size == 'large') {
                    $params['fullres'] = 1;
                }
                $params['id'] = $this->getUniqueId();
                $params['pdf'] = !empty($images[$index]['pdf'][$size])
                    || true === ($images[$index]['pdf'] ?? false);
                $params['cacheSize'] = $cacheSize;
                return $params;
            }
        }
        $params = parent::getThumbnail($size);
        if ($params && !is_array($params)) {
            $params = ['url' => $params];
        }
        return $params;
    }

    /**
     * Get sector
     *
     * @return string
     */
    public function getSector()
    {
        return (string)($this->fields['sector_str_mv'][0] ?? '');
    }

    /**
     * Return local record IDs (only works with dedup records)
     *
     * @return array
     */
    public function getLocalIds()
    {
        return $this->fields['local_ids_str_mv'] ?? [];
    }

    /**
     * Return the unique identifier of this record within the index;
     * useful for retrieving additional information (like tags and user
     * comments) from the external MySQL database.
     *
     * @return string Unique identifier.
     */
    public function getUniqueID()
    {
        if ($this->getExtraDetail('preview_record')) {
            return '0';
        }
        return parent::getUniqueID();
    }

    /**
     * Get the VuFind configuration.
     *
     * @return \VuFind\Config\Config
     */
    protected function getConfig()
    {
        return $this->mainConfig;
    }

    /**
     * Returns the locale used by translator
     *
     * @return string
     */
    protected function getLocale()
    {
        [$locale] = explode('-', $this->getTranslatorLocale());
        return $locale;
    }

    /**
     * Get an array containing languages in a priority order.
     * First language is the translator locale, then languages from primary array,
     * then sites fallback_languages and last default non-language code
     *
     * @param array  $primary An array containing languages, which are to be checked after
     *                        translator locale.
     * @param string $default If a non-language term is required, then use $default to append
     *                        a non-language code like 'no_locale'
     *
     * @return array
     */
    protected function getPrioritizedLanguages(
        array $primary = [],
        string $default = '',
    ): array {
        $languages = [
            $this->getTranslatorLocale(),
            ...$primary,
            ...$this->localeSettings->getFallbackLocales(),
        ];
        $final = [];
        foreach ($languages as $lang) {
            $final[] = $lang;
            if (!str_contains($lang, '-')) {
                continue;
            }
            [$code] = explode('-', $lang, 2);
            if ($code) {
                $final[] = $code;
            }
        }
        if ($default) {
            $final[] = $default;
        }
        return array_unique($final);
    }
}
