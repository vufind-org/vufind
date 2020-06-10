<?php
/**
 * Additional functionality for Finna Solr and Finna SolrAuth records.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library 2019.
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
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Additional functionality for Finna Solr and Finna SolrAuth records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
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
     * Return URL to copyright information.
     *
     * @param string $copyright Copyright
     * @param string $language  Language
     *
     * @return mixed URL or false if no URL for the given copyright
     */
    public function getRightsLink($copyright, $language)
    {
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
            if (isset($images[$index]['urls'][$size])) {
                $params = $images[$index]['urls'][$size];
                if (!is_array($params)) {
                    $params = [
                        'url' => $params
                    ];
                }
                if ($size == 'large') {
                    $params['fullres'] = 1;
                }
                $params['id'] = $this->getUniqueId();
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
}
