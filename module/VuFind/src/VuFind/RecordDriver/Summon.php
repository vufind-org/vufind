<?php
/**
 * Model for Summon records.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace VuFind\RecordDriver;

/**
 * Model for Summon records.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class Summon extends SolrDefault
{
    /**
     * Date converter
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter = null;

    /**
     * Get all subject headings associated with this record.  Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific.
     *
     * @return array
     */
    public function getAllSubjectHeadings()
    {
        $retval = [];
        $topic = isset($this->fields['SubjectTerms']) ?
            $this->fields['SubjectTerms'] : [];
        $temporal = isset($this->fields['TemporalSubjectTerms']) ?
            $this->fields['TemporalSubjectTerms'] : [];
        $geo = isset($this->fields['GeographicLocations']) ?
            $this->fields['GeographicLocations'] : [];
        $key = isset($this->fields['Keywords']) ?
            $this->fields['Keywords'] : [];

        $retval = [];
        foreach ($topic as $t) {
            $retval[] = [trim($t)];
        }
        foreach ($temporal as $t) {
            $retval[] = [trim($t)];
        }
        foreach ($geo as $g) {
            $retval[] = [trim($g)];
        }
        foreach ($key as $k) {
            $retval[] = [trim($k)];
        }
        return $retval;
    }

    /**
     * Get notes on bibliography content.
     *
     * @return array
     */
    public function getBibliographyNotes()
    {
        return isset($this->fields['Notes']) ?
            $this->fields['Notes'] : [];
    }

    /**
     * Get the call numbers associated with the record (empty string if none).
     *
     * @return array
     */
    public function getCallNumbers()
    {
        // Summon calls this LCCNum even though it may be Dewey
        return isset($this->fields['LCCCallnum'])
            && !empty($this->fields['LCCCallnum'])
            ? [$this->fields['LCCCallnum']] : [];
    }

    /**
     * Return the first valid DOI found in the record (false if none).
     *
     * @return mixed
     */
    public function getCleanDOI()
    {
        return (isset($this->fields['DOI'][0]) && !empty($this->fields['DOI'][0]))
            ? $this->fields['DOI'][0] : false;

    }

    /**
     * Get the edition of the current record.
     *
     * @return string
     */
    public function getEdition()
    {
        return isset($this->fields['Edition']) ?
            $this->fields['Edition'][0] : '';
    }

    /**
     * Get an array of all the formats associated with the record.
     *
     * @return array
     */
    public function getFormats()
    {
        return isset($this->fields['ContentType'])
            ? $this->fields['ContentType'] : [];
    }

    /**
     * Get a highlighted author string, if available.
     *
     * @return string
     */
    public function getHighlightedAuthor()
    {
        // Don't check for highlighted values if highlighting is disabled;
        // also, don't try to highlight multi-author works, because it will
        // cause display problems -- it's currently impossible to properly
        // synchronize the 'Author' and 'Author_xml' lists.
        if (!$this->highlight || count($this->fields['Author']) > 1) {
            return '';
        }
        return isset($this->fields['Author']) ?
            $this->fields['Author'][0] : '';
    }

    /**
     * Pick one line from the highlighted text (if any) to use as a snippet.
     *
     * @return mixed False if no snippet found, otherwise associative array
     * with 'snippet' and 'caption' keys.
     */
    public function getHighlightedSnippet()
    {
        return isset($this->fields['Snippet'][0])
            ? [
                'snippet' => trim($this->fields['Snippet'][0], '.'),
                'caption' => ''
            ]
            : false;
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
        $title = $this->getShortTitle();
        $sub = $this->getSubtitle();
        return empty($sub) ? $title : "{$title}: {$sub}";
    }

    /**
     * Get an array of all ISBNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISBNs()
    {
        if (isset($this->fields['ISBN']) && is_array($this->fields['ISBN'])) {
            return $this->fields['ISBN'];
        }
        return [];
    }

    /**
     * Get an array of all ISSNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISSNs()
    {
        $issns = [];
        if (isset($this->fields['ISSN'])) {
            $issns = $this->fields['ISSN'];
        }
        if (isset($this->fields['EISSN'])) {
            $issns = array_merge($issns, $this->fields['EISSN']);
        }
        return $issns;
    }

    /**
     * Get an array of all the languages associated with the record.
     *
     * @return array
     */
    public function getLanguages()
    {
        return isset($this->fields['Language']) ?
            $this->fields['Language'] : [];
    }

    /**
     * Get the OCLC number of the record.
     *
     * @return array
     */
    public function getOCLC()
    {
        return isset($this->fields['OCLC']) ?
            $this->fields['OCLC'] : [];
    }

    /**
     * Get the OpenURL parameters to represent this record (useful for the
     * title attribute of a COinS span tag).
     *
     * @return string OpenURL parameters.
     */
    public function getOpenURL()
    {
        return isset($this->fields['openUrl'])
            ? $this->fields['openUrl'] : parent::getOpenURL();
    }

    /**
     * Get the item's place of publication.
     *
     * @return array
     */
    public function getPlacesOfPublication()
    {
        return isset($this->fields['PublicationPlace']) ?
            $this->fields['PublicationPlace'] : [];
    }

    /**
     * Get the main author of the record.
     *
     * @return string
     */
    public function getPrimaryAuthor()
    {
        return isset($this->fields['Author_xml'][0]['fullname']) ?
            $this->fields['Author_xml'][0]['fullname'] : '';
    }

    /**
     * Pass in a date converter
     *
     * @param \VuFind\Date\Converter $dc Date converter
     *
     * @return void
     */
    public function setDateConverter(\VuFind\Date\Converter $dc)
    {
        $this->dateConverter = $dc;
    }

    /**
     * Get a date converter
     *
     * @return \VuFind\Date\Converter
     */
    protected function getDateConverter()
    {
        // No object passed in yet?  Build one with default settings:
        if (null === $this->dateConverter) {
            $this->dateConverter = new \VuFind\Date\Converter();
        }
        return $this->dateConverter;
    }

    /**
     * Get the publication dates of the record.  See also getDateSpan().
     *
     * @return array
     */
    public function getPublicationDates()
    {
        if (isset($this->fields['PublicationDate_xml'])
            && is_array($this->fields['PublicationDate_xml'])
        ) {
            $dates = [];
            $converter = $this->getDateConverter();
            foreach ($this->fields['PublicationDate_xml'] as $current) {
                if (isset($current['month']) && isset($current['year'])) {
                    if (!isset($current['day'])) {
                        $current['day'] = 1;
                    }
                    $dates[] = $converter->convertToDisplayDate(
                        'm-d-Y',
                        "{$current['month']}-{$current['day']}-{$current['year']}"
                    );
                } else if (isset($current['year'])) {
                    $dates[] = $current['year'];
                }
            }
            if (!empty($dates)) {
                return $dates;
            }
        }
        return isset($this->fields['PublicationDate']) ?
            $this->fields['PublicationDate'] : [];
    }

    /**
     * Get the publishers of the record.
     *
     * @return array
     */
    public function getPublishers()
    {
        return isset($this->fields['Publisher']) ?
            $this->fields['Publisher'] : [];
    }

    /**
     * Get an array of all secondary authors (complementing getPrimaryAuthor()).
     *
     * @return array
     */
    public function getSecondaryAuthors()
    {
        $authors = [];
        if (isset($this->fields['Author_xml'])) {
            for ($i = 1; $i < count($this->fields['Author_xml']); $i++) {
                if (isset($this->fields['Author_xml'][$i]['fullname'])) {
                    $authors[] = $this->fields['Author_xml'][$i]['fullname'];
                }
            }
        }
        return $authors;
    }

    /**
     * Get an array of all series names containing the record.  Array entries may
     * be either the name string, or an associative array with 'name' and 'number'
     * keys.
     *
     * @return array
     */
    public function getSeries()
    {
        return isset($this->fields['PublicationSeriesTitle'])
            ? $this->fields['PublicationSeriesTitle'] : [];
    }

    /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        return isset($this->fields['Title']) ?
            $this->fields['Title'][0] : '';
    }

    /**
     * Get the subtitle of the record.
     *
     * @return string
     */
    public function getSubtitle()
    {
        return isset($this->fields['Subtitle']) ?
            $this->fields['Subtitle'][0] : '';
    }

    /**
     * Get an array of summary strings for the record.
     *
     * @return array
     */
    public function getSummary()
    {
        return isset($this->fields['Abstract']) ?
          $this->fields['Abstract'] : [];
    }

    /**
     * Returns one of three things: a full URL to a thumbnail preview of the record
     * if an image is available in an external system; an array of parameters to
     * send to VuFind's internal cover generator if no fixed URL exists; or false
     * if no thumbnail can be generated.
     *
     * @param string $size Size of thumbnail (small, medium or large -- small is
     * default).
     *
     * @return string|array|bool
     */
    public function getThumbnail($size = 'small')
    {
        $params = parent::getThumbnail($size);

        // Support thumbnails embedded in the Summon record when no unique identifier
        // is found... (We don't use them in cases where we have an identifier, since
        // we want to allow these to be passed to configured external services).
        if (!isset($params['oclc']) && !isset($params['issn'])
            && !isset($params['isbn']) && !isset($params['upc'])
        ) {
            if ($size === 'small' && isset($this->fields['thumbnail_s'][0])) {
                return ['proxy' => $this->fields['thumbnail_s'][0]];
            } else if (isset($this->fields['thumbnail_m'][0])) {
                return ['proxy' => $this->fields['thumbnail_m'][0]];
            }
        }

        $formats = $this->getFormats();
        if (!empty($formats)) {
            $params['contenttype'] = $formats[0];
        }
        return $params;
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        $title = $this->getShortTitle();
        $sub = $this->getSubtitle();
        $title = empty($sub) ? $title : "{$title}: {$sub}";
        return str_replace(
            ['{{{{START_HILITE}}}}', '{{{{END_HILITE}}}}'], '', $title
        );
    }

    /**
     * Get an array of lines from the table of contents.
     *
     * @return array
     */
    public function getTOC()
    {
        return isset($this->fields['TableOfContents'])
            ? $this->fields['TableOfContents'] : [];
    }

    /**
     * Return an array of associative URL arrays with one or more of the following
     * keys:
     *
     * <li>
     *   <ul>desc: URL description text to display (optional)</ul>
     *   <ul>url: fully-formed URL (required if 'route' is absent)</ul>
     *   <ul>route: VuFind route to build URL with (required if 'url' is absent)</ul>
     *   <ul>routeParams: Parameters for route (optional)</ul>
     *   <ul>queryString: Query params to append after building route (optional)</ul>
     * </li>
     *
     * @return array
     */
    public function getURLs()
    {
        if (isset($this->fields['link'])) {
            return [
                [
                    'url' => $this->fields['link'],
                    'desc' => $this->translate('Get full text')
                ]
            ];
        }
        $retVal = [];
        if (isset($this->fields['url']) && is_array($this->fields['url'])) {
            foreach ($this->fields['url'] as $desc => $url) {
                $retVal[] = ['url' => $url, 'desc' => $desc];
            }
        }
        return $retVal;
    }

    /**
     * Return the unique identifier of this record within the Solr index;
     * useful for retrieving additional information (like tags and user
     * comments) from the external MySQL database.
     *
     * @return string Unique identifier.
     */
    public function getUniqueID()
    {
        return $this->fields['ID'][0];
    }
    /**
     * Get the title of the item that contains this record (i.e. MARC 773s of a
     * journal).
     *
     * @return string
     */
    public function getContainerTitle()
    {
        return isset($this->fields['PublicationTitle'])
            ? $this->fields['PublicationTitle'][0] : '';
    }

    /**
     * Get the volume of the item that contains this record (i.e. MARC 773v of a
     * journal).
     *
     * @return string
     */
    public function getContainerVolume()
    {
        return (isset($this->fields['Volume'])) ? $this->fields['Volume'][0] : '';
    }

    /**
     * Get the issue of the item that contains this record (i.e. MARC 773l of a
     * journal).
     *
     * @return string
     */
    public function getContainerIssue()
    {
        return (isset($this->fields['Issue'])) ? $this->fields['Issue'][0] : '';
    }

    /**
     * Get the start page of the item that contains this record (i.e. MARC 773q of a
     * journal).
     *
     * @return string
     */
    public function getContainerStartPage()
    {
        return (isset($this->fields['StartPage']))
            ? $this->fields['StartPage'][0] : '';
    }

    /**
     * Get the end page of the item that contains this record.
     *
     * @return string
     */
    public function getContainerEndPage()
    {
        if (isset($this->fields['EndPage'])) {
            return $this->fields['EndPage'][0];
        } else if (isset($this->fields['PageCount'])
            && $this->fields['PageCount'] > 1
            && intval($this->fields['StartPage'][0]) > 0
        ) {
            return $this->fields['StartPage'][0] + $this->fields['PageCount'][0] - 1;
        }
        return $this->getContainerStartPage();
    }

    /**
     * Get a full, free-form reference to the context of the item that contains this
     * record (i.e. volume, year, issue, pages).
     *
     * @return string
     */
    public function getContainerReference()
    {
        $str = '';
        $vol = $this->getContainerVolume();
        if (!empty($vol)) {
            $str .= $this->translate('citation_volume_abbrev')
                . ' ' . $vol;
        }
        $no = $this->getContainerIssue();
        if (!empty($no)) {
            if (strlen($str) > 0) {
                $str .= '; ';
            }
            $str .= $this->translate('citation_issue_abbrev')
                . ' ' . $no;
        }
        $start = $this->getContainerStartPage();
        if (!empty($start)) {
            if (strlen($str) > 0) {
                $str .= '; ';
            }
            $end = $this->getContainerEndPage();
            if ($start == $end) {
                $str .= $this->translate('citation_singlepage_abbrev')
                    . ' ' . $start;
            } else {
                $str .= $this->translate('citation_multipage_abbrev')
                    . ' ' . $start . ' - ' . $end;
            }
        }
        return $str;
    }

    /**
     * Does the OpenURL configuration indicate that we should display OpenURLs in
     * the specified context?
     *
     * @param string $area 'results', 'record' or 'holdings'
     *
     * @return bool
     */
    public function openURLActive($area)
    {
        // Summon never uses OpenURLs for anything other than COinS:
        return false;
    }
}
