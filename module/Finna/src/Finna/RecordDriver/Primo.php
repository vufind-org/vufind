<?php
/**
 * Model for Primo Central records.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2012-2020.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for Primo Central records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class Primo extends \VuFind\RecordDriver\Primo
{
    use FinnaRecordTrait;

    /**
     * Record metadata
     *
     * @var \SimpleXMLElement
     */
    protected $simpleXML;

    /**
     * Indicate whether export is disabled for a particular format.
     *
     * @param string $format Export format
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function exportDisabled($format)
    {
        // Support export for EndNote and RefWorks
        return !in_array($format, ['EndNote', 'RefWorks', 'RIS']);
    }

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
     * Get a full, free-form reference to the context of the item that contains this
     * record (i.e. volume, year, issue, pages).
     *
     * @return string
     */
    public function getContainerReference()
    {
        $partOf = $this->getIsPartOf();
        $containerTitle = $this->getContainerTitle();
        // Try to take the part after the title. Account for any 'The' etc. in the
        // beginning.
        if ($containerTitle && ($p = strpos($partOf, $containerTitle)) !== false) {
            return trim(
                substr($partOf, $p + strlen($containerTitle) + 1),
                " \t\n\r,"
            );
        }
        return $partOf;
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
        return ['APA', 'Chicago', 'MLA', 'Harvard'];
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
            ? (string)$fullrecord->display->type : null;
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
        if (!$this->showOnlineURLs()) {
            return [];
        }

        $urls = [];

        $rec = $this->getSimpleXML();

        $links = ['linktorsrc' => false, 'backlink' => true];
        foreach ($links as $link => $citation) {
            $url = '';
            if (isset($rec->links->{$link})) {
                $url = (string)$rec->links->{$link};
                $parts = explode('$$', $url);
                $url = substr($parts[1], 1);
                $urlParts = parse_url($url);
                if (empty($urlParts['host'])) {
                    $url = '';
                }
            }
            if ('' === $url && !empty($this->fields['resource_urls'][$link])) {
                $url = (string)$this->fields['resource_urls'][$link];
                $urlParts = parse_url($url);
                if (empty($urlParts['host'])) {
                    $url = '';
                }
            }
            if (empty($url)) {
                continue;
            }
            $urls[] = [
                'url' => $url,
                'urlShort' => $urlParts['host'],
                'citation' => $citation
            ];
            break;
        }

        return $urls;
    }

    /**
     * Check if Primo online URLs (local links from record metadata) should be
     * displayed for this record.
     *
     * @return boolean
     */
    protected function showOnlineURLs()
    {
        if (!isset($this->recordConfig->OnlineURLs)) {
            return true;
        }

        $rec = $this->getSimpleXML();
        if (!isset($rec->search->sourceid)) {
            return true;
        }

        $fulltextAvailable = $this->getFulltextAvailable();

        $config = $this->recordConfig->OnlineURLs;
        $hideFromSource = isset($config->hideFromSource)
            ? $config->hideFromSource->toArray() : [];
        $showFromSource = isset($config->showFromSource)
            ? $config->showFromSource->toArray() : [];

        if ($fulltextAvailable) {
            if ($config->hideFromSourceWithFulltext) {
                $hideFromSourceWithFulltext
                    = $config->hideFromSourceWithFulltext->toArray();
                if (!is_array($hideFromSourceWithFulltext)) {
                    $hideFromSourceWithFulltext = [$hideFromSourceWithFulltext];
                }
                $hideFromSource = array_merge(
                    $hideFromSource, $hideFromSourceWithFulltext
                );
            }

            if ($config->showFromSourceWithFulltext) {
                $showFromSourceWithFulltext
                    = $config->showFromSourceWithFulltext->toArray();
                if (!is_array($showFromSourceWithFulltext)) {
                    $showFromSourceWithFulltext = [$showFromSourceWithFulltext];
                }
                $showFromSource = array_merge(
                    $showFromSource, $showFromSourceWithFulltext
                );
            }
        }

        if (!$hideFromSource && !$showFromSource) {
            return true;
        }

        $source = $rec->search->sourceid;

        if ($showFromSource) {
            if (!count(array_intersect($showFromSource, ['*', $source]))) {
                return false;
            }
        }

        if ($hideFromSource) {
            if (count(array_intersect($hideFromSource, ['*', $source]))) {
                return false;
            }
        }

        return true;
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
        if (isset($rec->facets->creationdate)) {
            return (array)($rec->facets->creationdate);
        }
    }

    /**
     * Return DOI (false if none)
     *
     * @return mixed
     */
    public function getCleanDOI()
    {
        $rec = $this->getSimpleXML();
        return isset($rec->addata->doi)
            ? (string)$rec->addata->doi : false;
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
     * Get primary author information with highlights applied (if applicable)
     *
     * @return array
     */
    public function getPrimaryAuthorsWithHighlighting()
    {
        $authors = $this->getCreators();
        // Don't check for highlighted values if highlighting is disabled or we
        // don't have highlighting data:
        if (!$this->highlight || !isset($this->fields['highlightDetails']['author'])
        ) {
            return $authors;
        }
        foreach ($this->fields['highlightDetails']['author'] as $highlightedAuthor) {
            $cleanAuthor = str_replace(
                '{{{{END_HILITE}}}}', '',
                str_replace('{{{{START_HILITE}}}}', '', $highlightedAuthor)
            );
            foreach ($authors as &$author) {
                if ($author == $cleanAuthor) {
                    $author = $highlightedAuthor;
                    break;
                }
            }
        }
        return $authors;
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
     * @return string
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
        $params = parent::getThumbnail($size);
        if ($params && !is_array($params)) {
            $params = ['url' => $params];
        }
        return $params;
    }

    /**
     * Return information whether this is a peer reviewed record.
     *
     * @return bool
     */
    public function getPeerReviewed()
    {
        $rec = $this->getSimpleXML();
        if (isset($rec->display->lds50)) {
            return ((string)$rec->display->lds50) === 'peer_reviewed';
        }
        return false;
    }

    /**
     * Return information whether this is an open access record.
     *
     * @return bool
     */
    public function getOpenAccess()
    {
        $rec = $this->getSimpleXML();
        if (isset($rec->display->oa)) {
            return ((string)$rec->display->oa) === 'free_for_read';
        }
        return false;
    }

    /**
     * Returns an array of 0 or more record label constants, or null if labels
     * are not enabled in configuration.
     *
     * @return array|null
     */
    public function getRecordLabels()
    {
        if (!$this->getRecordLabelsEnabled()) {
            return null;
        }
        $labels = [];
        if ($this->getFulltextAvailable()) {
            $labels[] = FinnaRecordLabelInterface::FULL_TEXT_AVAILABLE;
        }
        if ($this->getPeerReviewed()) {
            $labels[] = FinnaRecordLabelInterface::PEER_REVIEWED;
        }
        if ($this->getOpenAccess()) {
            $labels[] = FinnaRecordLabelInterface::OPEN_ACCESS;
        }
        return $labels;
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
            $params['rft.date'] = $params['rft_date']
                = implode('', $this->getPublicationDates());
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
