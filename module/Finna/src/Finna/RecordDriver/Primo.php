<?php
/**
 * Model for Primo Central records.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2012-2015.
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
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for Primo Central records.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class Primo extends \VuFind\RecordDriver\Primo
{
    use FinnaRecord;

    /**
     * Record metadata
     *
     * @var \SimpleXMLElement
     */
    protected $simpleXML;

    /**
     * Get an array of supported, user-activated citation formats.
     *
     * @return array Strings representing citation formats.
     */
    public function getCitationFormats()
    {
        // Default behavior: use all supported options.
        if (!isset($this->mainConfig->Record->citation_formats)
            || $this->mainConfig->Record->citation_formats === true
            || $this->mainConfig->Record->citation_formats === 'true'
        ) {
            return $this->getSupportedCitationFormats();
        }

        // Citations disabled:
        if ($this->mainConfig->Record->citation_formats === false
            || $this->mainConfig->Record->citation_formats === 'false'
        ) {
            return [];
        }

        // Whitelist:
        $whitelist = array_map(
            'trim', explode(',', $this->mainConfig->Record->citation_formats)
        );
        return array_intersect($whitelist, $this->getSupportedCitationFormats());
    }

    /**
     * Get an array of strings representing citation formats supported
     * by this record's data (empty if none).  For possible legal values,
     * see /application/themes/root/helpers/Citation.php, getCitation()
     * method.
     *
     * @return array Strings representing citation formats.
     */
    protected function getSupportedCitationFormats()
    {
        return ['APA', 'Chicago', 'MLA'];
    }

    /**
     * Get unprocessed record format from fullrecord.
     *
     * @return array string
     */
    public function getType()
    {
        $fullrecord = simplexml_load_string($this->fields['fullrecord']);
        return isset($fullrecord->display->type)
            ? $fullrecord->display->type : null;
    }

    /**
     * Return an array of associative URL arrays with one or more of the following
     * keys:
     *
     * <li>
     *   <ul>desc: URL description text to display (optional)</ul>
     *   <ul>url: fully-formed URL (required if 'route' is absent)</ul>
     * </li>
     *
     * @return array
     */
    public function getURLs()
    {
        $urls = [];

        $rec = $this->getSimpleXML();

        $links = ['linktorsrc' => false, 'backlink' => true];
        foreach ($links as $link => $citation) {
            if (isset($rec->links->{$link})) {
                $url = (string)$rec->links->{$link};
                $parts = explode('$$', $url);
                $url = substr($parts[1], 1);

                $urlParts = parse_url($url);
                $urls[] = [
                   'url' => $url,
                   'urlShort' => $urlParts['host'],
                   'citation' => $citation
                ];
                break;
            }
        }
        return $urls;
    }

    /**
     * Get the language associated with the record.
     *
     * @return String
     */
    public function getLanguages()
    {
        $languages = parent::getLanguages();
        foreach ($languages as $ind => $lan) {
            if ($lan == '') {
                unset($languages[$ind]);
            }
        }
        return $languages;
    }

    /**
     * Get the publication dates of the record.  See also getDateSpan().
     *
     * @return array
     */
    public function getPublicationDates()
    {
        $rec = $this->getSimpleXML();
        if (isset($rec->search->creationdate)) {
            return (array)($rec->search->creationdate);
        }
    }

    /**
     * Get a highlighted title string, if available.
     *
     * @return string
     */
    public function getHighlightedTitle()
    {
        // Don't check for highlighted values if highlighting is disabled:
        if (!$this->highlight) {
            return '';
        }

        return (isset($this->fields['highlightDetails']['title'][0]))
            ? $this->fields['highlightDetails']['title'][0] : '';
    }

    /**
     * Get a highlighted author string, if available.
     *
     * @return string
     */
    public function getHighlightedAuthor()
    {
        // Don't check for highlighted values if highlighting is disabled:
        if (!$this->highlight) {
            return '';
        }
        return (isset($this->fields['highlightDetails']['author'][0]))
            ? $this->fields['highlightDetails']['author'][0] : '';
    }

    /**
     * Pick highlighted description string, if available.
     *
     * @return string
     */
    public function getHighlightedSummary()
    {
        // Don't check for highlighted values if highlighting is disabled:
        if (!$this->highlight) {
            return '';
        }
        return (isset($this->fields['highlightDetails']['description'][0]))
            ? [$this->fields['highlightDetails']['description'][0]] : [];
    }

    /**
     * Return record format.
     *
     * @return string.
     */
    public function getRecordType()
    {
        return $this->fields['format'];
    }

    /**
     * Return building from index.
     *
     * @return string
     */
    public function getBuilding()
    {
        return null;
    }

    /**
     * Return information whether fulltext is available
     *
     * @return bool
     */
    public function getFulltextAvailable()
    {
        $rec = $this->getSimpleXML();
        if (isset($rec->delivery->fulltext)) {
            return $rec->delivery->fulltext == 'fulltext';
        }
        return false;
    }

    /**
     * Return image rights.
     *
     * @param string $language Language
     *
     * @return mixed array with keys:
     *   'copyright'  Copyright (e.g. 'CC BY 4.0') (optional)
     *   'description Human readable description (array)
     *   'link'       Link to copyright info
     *   or false if the record contains no images
     */
    public function getImageRights($language)
    {
        return false;
    }

    /**
     * Returns an array of parameter to send to Finna's cover generator.
     * Fallbacks to VuFind's getThumbnail if no record image with the
     * given index was found.
     *
     * @param string $size  Size of thumbnail
     * @param int    $index Image index
     *
     * @return array|bool
     */
    public function getRecordImage($size = 'small', $index = 0)
    {
        $params = parent::getThumbnail($size);
        if ($params && !is_array($params)) {
            $params = ['url' => $params];
        }
        return $params;
    }

    /**
     * Get default OpenURL parameters.
     *
     * @return array
     */
    protected function getDefaultOpenUrlParams()
    {
        $link = isset($this->fields['url']) ? $this->fields['url'] : '';

        $params = [];
        // Take params from the OpenURL returned from Primo, if available
        if ($link && strpos($link, 'url_ver=Z39.88-2004') !== false) {
            parse_str(substr($link, strpos($link, '?') + 1), $params);
            $params = $this->processOpenUrlParams($params);
        }
        $params['rfr_id'] = !empty($this->mainConfig->OpenURL->rfr_id)
            ? $this->mainConfig->OpenURL->rfr_id
            : '';
        if ($dates = $this->getPublicationDates()) {
            $params['rft.date'] = implode('', $this->getPublicationDates());
        }
        if (!isset($params['rft.title'])) {
            $params['rft.title'] = $this->getTitle();
        }

        return $params;
    }

    /**
     * Get the original record as a SimpleXML object
     *
     * @return SimpleXMLElement The record as SimpleXML
     */
    protected function getSimpleXML()
    {
        if ($this->simpleXML !== null) {
            return $this->simpleXML;
        }
        $this->simpleXML = new \SimpleXmlElement($this->fields['fullrecord']);

        return $this->simpleXML;
    }

    /**
     * Utility function for processing OpenURL parameters.
     * This duplicates 'rft_<param>' prefixed parameters as 'rft.<param>'
     *
     * @param array $params OpenURL parameters as key-value pairs
     *
     * @return array
     */
    protected function processOpenUrlParams($params)
    {
        foreach ($params as $key => $val) {
            if (strpos($key, 'rft_') === 0) {
                $params['rft.' . substr($key, 4)] = $val;
            }
        }
        return $params;
    }
}
