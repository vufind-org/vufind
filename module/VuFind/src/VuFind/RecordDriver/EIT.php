<?php

/**
 * Model for records retrieved via EBSCO's EIT API.
 *
 * PHP version 8
 *
 * Copyright (C) Julia Bauder 2013.
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
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver;

use function in_array;
use function is_array;
use function strlen;

/**
 * Model for records retrieved via EBSCO's EIT API.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class EIT extends DefaultRecord
{
    use \VuFind\Log\VarDumperTrait;

    /**
     * Used for identifying search backends
     *
     * @var string
     */
    protected $sourceIdentifier = 'EIT';

    /**
     * Reference to controlInfo section of fields, for readability
     *
     * @var array
     */
    protected $controlInfo;

    /**
     * Set raw data to initialize the object.
     *
     * @param mixed $data Raw data representing the record; Record Model
     * objects are normally constructed by Record Driver objects using data
     * passed in from a Search Results object. The exact nature of the data may
     * vary depending on the data source -- the important thing is that the
     * Record Driver + Search Results objects work together correctly.
     *
     * @return void
     */
    public function setRawData($data)
    {
        // Easy way to recursively convert a SimpleXML Object to an array
        $data = json_decode(json_encode((array)$data), 1);
        if (isset($data['fields'])) {
            $this->fields = $data['fields'];
        } else {
            // The following works for EITRecord pages
            $this->fields['fields'] = $data;
        }
        if (isset($this->fields['fields']['header']['controlInfo'])) {
            $this->controlInfo = & $this->fields['fields']['header']['controlInfo'];
        }
    }

    /**
     * Get all subject headings associated with this record. Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific.
     *
     * @param bool $extended Whether to return a keyed array with the following
     * keys:
     * - heading: the actual subject heading chunks
     * - type: heading type
     * - source: source vocabulary
     *
     * @return array
     */
    public function getAllSubjectHeadings($extended = false)
    {
        $su = $this->controlInfo['artinfo']['su'] ?? [];

        // The EIT index doesn't currently subject headings in a broken-down
        // format, so we'll just send each value as a single chunk.
        $retval = [];
        foreach ($su as $s) {
            $retval[] = $extended
                ? ['heading' => [$s], 'type' => '', 'source' => '']
                : [$s];
        }
        return $retval;
    }

    /**
     * Get text that can be displayed to represent this record in
     * breadcrumbs.
     *
     * @return string Breadcrumb text to represent this record.
     */
    public function getBreadcrumb()
    {
        return $this->controlInfo['artinfo']['tig']['atl'] ?? '';
    }

    /**
     * Get the call numbers associated with the record (empty string if none).
     *
     * @return array
     */
    public function getCallNumbers()
    {
        return [];
    }

    /**
     * Get just the first listed OCLC Number (or false if none available).
     *
     * @return mixed
     */
    public function getCleanOCLCNum()
    {
        return false;
    }

    /**
     * Get just the first ISSN (or false if none available).
     *
     * @return mixed
     */
    public function getCleanISSN()
    {
        return $this->controlInfo['jinfo']['issn'] ?? false;
    }

    /**
     * Get the date coverage for a record which spans a period of time (i.e. a
     * journal). Use getPublicationDates for publication dates of particular
     * monographic items.
     *
     * @return array
     */
    public function getDateSpan()
    {
        return [];
    }

    /**
     * Get the edition of the current record.
     *
     * @return string
     */
    public function getEdition()
    {
        return null;
    }

    /**
     * Get an array of all the formats associated with the record.
     *
     * @return array
     */
    public function getFormats()
    {
        if (
            isset($this->controlInfo['artinfo']['doctype'])
            && is_array($this->controlInfo['artinfo']['doctype'])
        ) {
            return $this->controlInfo['artinfo']['doctype'];
        }
        return isset($this->controlInfo['artinfo']['doctype'])
            ? [$this->controlInfo['artinfo']['doctype']] : [];
    }

    /**
     * Get the main author of the record.
     *
     * @return string
     */
    public function getPrimaryAuthors()
    {
        if (
            isset($this->controlInfo['artinfo']['aug']['au'])
            && is_array($this->controlInfo['artinfo']['aug']['au'])
        ) {
            return $this->controlInfo['artinfo']['aug']['au'];
        } else {
            return isset($this->controlInfo['artinfo']['aug']['au'])
                ? [$this->controlInfo['artinfo']['aug']['au']] : [];
        }
    }

    /**
     * Get the publication dates of the record.  See also getDateSpan().
     *
     * @return array
     */
    public function getPublicationDates()
    {
        if (isset($this->controlInfo['pubinfo']['dt']['@attributes']['year'])) {
            return [
                $this->controlInfo['pubinfo']['dt']['@attributes']['year'],
            ];
        } elseif (isset($this->controlInfo['pubinfo']['dt'])) {
            return [$this->controlInfo['pubinfo']['dt']];
        } else {
            return [];
        }
    }

    /**
     * Get the publishers of the record.
     *
     * @return array
     */
    public function getPublishers()
    {
        return isset($this->controlInfo['pubinfo']['pub'])
            ? [$this->controlInfo['pubinfo']['pub']] : [];
    }

    /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        return $this->controlInfo['artinfo']['tig']['atl'] ?? '';
    }

    /**
     * Get an array of summary strings for the record.
     *
     * @return array
     */
    public function getSummary()
    {
        // We need to return an array, so if we have a description, turn it into an
        // array as needed (it should be a flat string according to the default
        // schema, but we might as well support the array case just to be on the safe
        // side:
        if (
            isset($this->controlInfo['artinfo']['ab'])
            && !empty($this->controlInfo['artinfo']['ab'])
        ) {
            return is_array($this->controlInfo['artinfo']['ab'])
                ? $this->controlInfo['artinfo']['ab']
                : [$this->controlInfo['artinfo']['ab']];
        }

        // If we got this far, no description was found:
        return [];
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->controlInfo['artinfo']['tig']['atl'] ?? '';
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
        // If non-empty, map internal URL array to expected return format;
        // otherwise, return empty array:
        if (isset($this->fields['fields']['plink'])) {
            $links = [$this->fields['fields']['plink']];
            $desc = $this->translate('View this record in EBSCOhost');
            $filter = function ($url) use ($desc) {
                return compact('url', 'desc');
            };
            return array_map($filter, $links);
        } else {
            return [];
        }
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
        if (!isset($this->fields['fields']['header']['@attributes']['uiTerm'])) {
            throw new \Exception(
                'ID not set!' . $this->varDump($this->fields['fields'])
            );
        }
        return $this->fields['fields']['header']['@attributes']['uiTerm'];
    }

    /**
     * Get the title of the item that contains this record (i.e. MARC 773s of a
     * journal).
     *
     * @return string
     */
    public function getContainerTitle()
    {
        return $this->controlInfo['jinfo']['jtl'] ?? '';
    }

    /**
     * Get the volume of the item that contains this record (i.e. MARC 773v of a
     * journal).
     *
     * @return string
     */
    public function getContainerVolume()
    {
        return $this->controlInfo['pubinfo']['vid'] ?? null;
    }

    /**
     * Get the issue of the item that contains this record (i.e. MARC 773l of a
     * journal).
     *
     * @return string
     */
    public function getContainerIssue()
    {
        return $this->controlInfo['pubinfo']['iid'] ?? null;
    }

    /**
     * Get the start page of the item that contains this record (i.e. MARC 773q of a
     * journal).
     *
     * @return string
     */
    public function getContainerStartPage()
    {
        return $this->controlInfo['artinfo']['ppf'] ?? null;
    }

    /**
     * Support method for getContainerEndPage()
     *
     * @return string
     */
    protected function getContainerPageCount()
    {
        return $this->controlInfo['artinfo']['ppct'] ?? null;
    }

    /**
     * Get the end page of the item that contains this record.
     *
     * @return string
     */
    public function getContainerEndPage()
    {
        $startpage = $this->getContainerStartPage();
        $pagecount = $this->getContainerPageCount();
        $endpage = $startpage + $pagecount;
        if ($endpage != 0) {
            return $endpage;
        } else {
            return null;
        }
    }

    /**
     * Get a sortable title for the record (i.e. no leading articles).
     *
     * @return string
     */
    public function getSortTitle()
    {
        return $this->controlInfo['artinfo']['tig']['atl'] ?? '';
    }

    /**
     * Support method for getOpenUrl() -- pick the OpenURL format.
     *
     * @return string
     */
    protected function getOpenUrlFormat()
    {
        // If we have multiple formats, Book, Journal and Article are most
        // important...
        $formats = $this->getFormats();
        if (in_array('Book', $formats)) {
            return 'Book';
        } elseif (in_array('Article', $formats)) {
            return 'Article';
        } elseif (in_array('Journal', $formats)) {
            return 'Journal';
        }
        // Defaulting to "Article" because many EBSCO databases have things like
        // "Film Criticism" instead of "Article" -- the other defaults from the
        // SolrDefault driver don't work well in this context.
        return 'Article';
    }

    /**
     * Get the COinS identifier.
     *
     * @return string
     */
    protected function getCoinsID()
    {
        // Added at Richard and Leslie's request, to facilitate ILL
        return parent::getCoinsID() . '.ebsco';
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
}
