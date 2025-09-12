<?php

/**
 * Model for Primo Central records.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2012-2023.
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

use function in_array;
use function is_array;
use function strlen;

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
    use Feature\FinnaRecordTrait;

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
        if (
            !isset($this->mainConfig->Record->citation_formats)
            || $this->mainConfig->Record->citation_formats === true
            || $this->mainConfig->Record->citation_formats === 'true'
        ) {
            return $this->getSupportedCitationFormats();
        }

        // Citations disabled:
        if (
            $this->mainConfig->Record->citation_formats === false
            || $this->mainConfig->Record->citation_formats === 'false'
        ) {
            return [];
        }

        // Allowed formats:
        $allowed = array_map(
            'trim',
            explode(',', $this->mainConfig->Record->citation_formats)
        );
        return array_intersect($allowed, $this->getSupportedCitationFormats());
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
            $arrRef = explode(
                ',',
                trim(substr($partOf, $p + strlen($containerTitle) + 1), " \t\n\r,")
            );
            // Remove month & day from date
            $arrRef[0] = preg_replace('/\b(\d{4})(?:-\d{2}){0,2}\b/', '$1', $arrRef[0]);
            return implode(',', $arrRef);
        }
        return $partOf;
    }

    /**
     * Get an array of strings representing citation formats supported
     * by this record's data (empty if none). For possible legal values,
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
        return $this->fields['format'][0] ?? '';
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

        $result = [];
        $links = ['linktorsrc' => false, 'backlink' => true];

        foreach ($links as $link => $citation) {
            if (!($urls = $this->fields['resource_urls'][$link] ?? [])) {
                continue;
            }
            foreach ((array)$urls as $current) {
                if (is_array($current)) {
                    $desc = $current['label'];
                    $url = $current['url'];
                } else {
                    // Old style, could be cached:
                    $desc = '';
                    $url = $current;
                }
                $result[] = [
                    'url' => $url,
                    'urlShort' => parse_url($url, PHP_URL_HOST),
                    'citation' => $citation,
                    'desc' => $desc,
                ];
            }
        }

        return $result;
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

        if (empty($this->fields['sourceid'])) {
            return true;
        }

        $fulltextAvailable = $this->getFulltextAvailable();

        $config = $this->recordConfig->OnlineURLs;
        $hideFromSource = $config?->hideFromSource?->toArray() ?? [];
        $showFromSource = $config?->showFromSource?->toArray() ?? [];

        if ($fulltextAvailable) {
            if ($config->hideFromSourceWithFulltext) {
                $hideFromSourceWithFulltext = $config->hideFromSourceWithFulltext->toArray();
                if (!is_array($hideFromSourceWithFulltext)) {
                    $hideFromSourceWithFulltext = [$hideFromSourceWithFulltext];
                }
                $hideFromSource = array_merge(
                    $hideFromSource,
                    $hideFromSourceWithFulltext
                );
            }

            if ($config->showFromSourceWithFulltext) {
                $showFromSourceWithFulltext = $config->showFromSourceWithFulltext->toArray();
                if (!is_array($showFromSourceWithFulltext)) {
                    $showFromSourceWithFulltext = [$showFromSourceWithFulltext];
                }
                $showFromSource = array_merge(
                    $showFromSource,
                    $showFromSourceWithFulltext
                );
            }
        }

        if (!$hideFromSource && !$showFromSource) {
            return true;
        }

        foreach ($this->fields['sourceid'] as $sourceid) {
            if ($showFromSource && !array_intersect($showFromSource, ['*', $sourceid])) {
                return false;
            }
            if ($hideFromSource && array_intersect($hideFromSource, ['*', $sourceid])) {
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
     * Get the publication dates of the record. See also getDateSpan().
     *
     * @return array
     */
    public function getPublicationDates()
    {
        $result = [];
        $dates = (array)($this->fields['date'] ?? []);
        foreach ($dates as $date) {
            $result[] = preg_replace('/\b(\d{4})(?:-\d{2}){0,2}\b/', '$1', $date);
        }
        return $result;
    }

    /**
     * Return DOI (false if none)
     *
     * @return mixed
     */
    public function getCleanDOI()
    {
        return $this->fields['doi_str_mv'][0] ?? '';
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
        if (
            !$this->highlight || !isset($this->fields['highlightDetails']['author'])
        ) {
            return $authors;
        }
        foreach ($this->fields['highlightDetails']['author'] as $highlightedAuthor) {
            $cleanAuthor = str_replace(
                '{{{{END_HILITE}}}}',
                '',
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
     * @deprecated Use getRecordFormat()
     *
     * @return string
     */
    public function getRecordType()
    {
        return $this->getRecordFormat();
    }

    /**
     * Return record format.
     *
     * @return string
     */
    public function getRecordFormat()
    {
        return 'primo';
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
        return 'fulltext' === $this->fields['fulltext'];
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
        return $this->fields['peer_reviewed'] ?? false;
    }

    /**
     * Return information whether this is an open access record.
     *
     * @return bool
     */
    public function getOpenAccess()
    {
        return $this->fields['open_access'] ?? false;
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
        $link = $this->fields['url'] ?? '';

        $params = [];
        // Take params from the OpenURL returned from Primo, if available
        if ($link && str_contains($link, 'url_ver=Z39.88-2004')) {
            parse_str(substr($link, strpos($link, '?') + 1), $params);
            $params = $this->processOpenUrlParams($params);
        }
        $params['rfr_id'] = !empty($this->mainConfig->OpenURL->rfr_id)
            ? $this->mainConfig->OpenURL->rfr_id
            : '';
        if ($dates = $this->getPublicationDates()) {
            $params['rft.date'] = $params['rft_date']
                = implode('', $dates);
        }
        if (!isset($params['rft.title'])) {
            $params['rft.title'] = $this->getTitle();
        }

        return $params;
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
            if (str_starts_with($key, 'rft_')) {
                $params['rft.' . substr($key, 4)] = $val;
            }
        }
        return $params;
    }
}
