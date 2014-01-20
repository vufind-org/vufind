<?php

/**
 * Model for records retrieved via EBSCO's EIT API.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace VuFind\RecordDriver;

use Zend\Log\LoggerInterface;

class EBSCO extends SolrDefault
{

	public $fields;
    /**
     * Used for identifying search backends
     *
     * @var string
     */
    protected $sourceIdentifier = 'EBSCO';

    /**
     * Logger, if any.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Set the Logger.
     *
     * @param LoggerInterface $logger Logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }


    /**
     * Log a debug message.
     *
     * @param string $msg Message to log.
     *
     * @return void
     */
    protected function debug($msg)
    {
        if ($this->logger) {
            $this->logger->debug($msg);
        }
    }


     /**
     * These fields should be used for snippets if available (listed in order
     * of preference).
     *
     * @var array
     */
//    protected $preferredSnippetFields = array(
//        'ab', 'keyword'
//);

        /**
     * These fields should NEVER be used for snippets.  (We exclude author
     * and title because they are already covered by displayed fields; we exclude
     * spelling because it contains lots of fields jammed together and may cause
     * glitchy output; we exclude ID because random numbers are not helpful).
     *
     * @var array
     */
//    protected $forbiddenSnippetFields = array(
//        'au', 'plink', 'jid', 'issn', 'maglogo', 'dt', 'vid', 'iid', 'pub', 'ui', 'ppf', 'ppct', 'pubtype', 'doctype', 'src', 'language'
//    );

    /**
     * Set raw data to initialize the object.
     *
     * @param mixed $data Raw data representing the record; Record Model
     * objects are normally constructed by Record Driver objects using data
     * passed in from a Search Results object.  The exact nature of the data may
     * vary depending on the data source -- the important thing is that the
     * Record Driver + Search Results objects work together correctly.
     *
     * @return void
     */
    public function setRawData($data)
    {
	    //Easy way to recursively convert a SimpleXML Object to an array

	$data = json_decode(json_encode((array) $data), 1);

	//	$data = json_decode(json_encode((array) $data->rec['0']), 1);
	if (isset($data['fields'])) {
		$this->fields = $data['fields'];
	} else {
	// The following works for EBSCORecord pages
		$this->fields['fields'] = $data;
	}
    }




   /**
     * Get all subject headings associated with this record.  Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific.
     *
     * @return array
     */

    public function getAllSubjectHeadings()
    {
        $su = isset($this->fields['fields']['header']['controlInfo']['artinfo']['su']) ? $this->fields['fields']['header']['controlInfo']['artinfo']['su'] : array();

        // The EBSCO index doesn't currently subject headings in a broken-down
        // format, so we'll just send each value as a single chunk.
        $retval = array();
        foreach ($su as $s) {
            $retval[] = array($s);
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
        return isset($this->fields['fields']['header']['controlInfo']['artinfo']['tig']['atl']) ?
            $this->fields['fields']['header']['controlInfo']['artinfo']['tig']['atl'] : '';
    
    }

   /**
     * Get the call number associated with the record (empty string if none).
     *
     * @return string
     */
    public function getCallNumber()
    {
	return "";
    }

    /**
     * Get just the first listed OCLC Number (or false if none available).
     *
     * @return mixed
     */
    public function getCleanOCLCNum()
    {
	    return FALSE;
    }

    /**
     * Get just the first ISSN (or false if none available).
     *
     * @return mixed
     */
    public function getCleanISSN()
    {
        return isset($this->fields['fields']['header']['controlInfo']['jinfo']['issn']) ?
            $this->fields['fields']['header']['controlInfo']['jinfo']['issn'] : FALSE;
    }

        /**
     * Get the date coverage for a record which spans a period of time (i.e. a
     * journal).  Use getPublicationDates for publication dates of particular
     * monographic items.
     *
     * @return array
     */
    public function getDateSpan()
    {
       return array();
    }

    /**
     * Deduplicate author information into associative array with main/corporate/
     * secondary keys.
     *
     * @return array
     */
    public function getDeduplicatedAuthors()
    {
        $authors = array(
            'main' => $this->getPrimaryAuthor(),
            'secondary' => $this->getSecondaryAuthors()
        );

        // The secondary author array may contain a corporate or primary author;
        // let's be sure we filter out duplicate values.
        $duplicates = array();
        if (!empty($authors['main'])) {
            $duplicates[] = $authors['main'];
        }
        if (!empty($duplicates)) {
            $authors['secondary'] = array_diff($authors['secondary'], $duplicates);
	}
        return $authors;
    }

    /**
     * Get the edition of the current record.
     *
     * @return string
     */
    public function getEdition()
    {
        return NULL;
    }

    /**
     * Get an array of all the formats associated with the record.
     *
     * @return array
     */
    public function getFormats()
    {
        return isset($this->fields['fields']['header']['controlInfo']['artinfo']['doctype']) ? array($this->fields['fields']['header']['controlInfo']['artinfo']['doctype']) : array();
    }

    /**
     * Get the main author of the record.
     *
     * @return string
     */
    public function getPrimaryAuthor()
    {
	    if (is_array($this->fields['fields']['header']['controlInfo']['artinfo']['aug']['au'])) {
		    return $this->fields['fields']['header']['controlInfo']['artinfo']['aug']['au']['0'];
	    } else {
		    return isset($this->fields['fields']['header']['controlInfo']['artinfo']['aug']['au']) ? $this->fields['fields']['header']['controlInfo']['artinfo']['aug']['au'] : '';
	    }
	
    }

    /**
     * Get the publication dates of the record.  See also getDateSpan().
     *
     * @return array
     */
    public function getPublicationDates()
    {
        if (isset($this->fields['fields']['header']['controlInfo']['pubinfo']['dt']['@attributes']['year'])) {
		return array($this->fields['fields']['header']['controlInfo']['pubinfo']['dt']['@attributes']['year']);
	} else if (isset($this->fields['fields']['header']['controlInfo']['pubinfo']['dt'])) {
		return array($this->fields['fields']['header']['controlInfo']['pubinfo']['dt']);
	} else {
		return array();
	}
    }

    /**
     * Get the publishers of the record.
     *
     * @return array
     */
    public function getPublishers()
    {
        return isset($this->fields['fields']['header']['controlInfo']['pubinfo']['pub']) ?
            array($this->fields['fields']['header']['controlInfo']['pubinfo']['pub']) : array();
    }

    /**
     * Get an array of all secondary authors (complementing getPrimaryAuthor()).
     *
     * @return array
     */
    public function getSecondaryAuthors()
    {
        return is_array($this->fields['fields']['header']['controlInfo']['artinfo']['aug']['au']) ?
            $this->fields['fields']['header']['controlInfo']['artinfo']['aug']['au'] : array();
    }

    /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        return isset($this->fields['fields']['header']['controlInfo']['artinfo']['tig']['atl']) ?
            $this->fields['fields']['header']['controlInfo']['artinfo']['tig']['atl'] : '';
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
        if (isset($this->fields['fields']['header']['controlInfo']['artinfo']['ab'])
            && !empty($this->fields['fields']['header']['controlInfo']['artinfo']['ab'])
        ) {
            return is_array($this->fields['fields']['header']['controlInfo']['artinfo']['ab'])
                ? $this->fields['fields']['header']['controlInfo']['artinfo']['ab'] : array($this->fields['fields']['header']['controlInfo']['artinfo']['ab']);
        }

        // If we got this far, no description was found:
        return array();
    }

        /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        return isset($this->fields['fields']['header']['controlInfo']['artinfo']['tig']['atl']) ?
            $this->fields['fields']['header']['controlInfo']['artinfo']['tig']['atl'] : '';
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
		$links = array($this->fields['fields']['plink']);    
		$filter = function ($url) {
                	return array('url' => $url, 'desc' => 'View this record in EBSCOhost');
	            };
            return array_map($filter, $links);
        } else {
		return array();
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
//	    $this->debug(print_r($this->fields['fields'], true));
	if (!isset($this->fields['fields']['header']['@attributes']['uiTerm'])) {
            throw new \Exception('ID not set!' . print_r($this->fields['fields'], true));
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
        return isset($this->fields['fields']['header']['controlInfo']['jinfo']['jtl'])
            ? $this->fields['fields']['header']['controlInfo']['jinfo']['jtl'] : '';
    }

    /**
     * Get the volume of the item that contains this record (i.e. MARC 773v of a
     * journal).
     *
     * @return string
     */
    public function getContainerVolume()
    {
        return isset($this->fields['fields']['header']['controlInfo']['pubinfo']['vid'])
            ? $this->fields['fields']['header']['controlInfo']['pubinfo']['vid'] : NULL;
    }

    /**
     * Get the issue of the item that contains this record (i.e. MARC 773l of a
     * journal).
     *
     * @return string
     */
    public function getContainerIssue()
    {
        return isset($this->fields['fields']['header']['controlInfo']['pubinfo']['iid'])
            ? $this->fields['fields']['header']['controlInfo']['pubinfo']['iid'] : NULL;
    }

    /**
     * Get the start page of the item that contains this record (i.e. MARC 773q of a
     * journal).
     *
     * @return string
     */
    public function getContainerStartPage()
    {
        return isset($this->fields['fields']['header']['controlInfo']['artinfo']['ppf'])
            ? $this->fields['fields']['header']['controlInfo']['artinfo']['ppf'] : NULL;
    }

    private function getContainerPageCount()
    {
        return isset($this->fields['fields']['header']['controlInfo']['artinfo']['ppct'])
            ? $this->fields['fields']['header']['controlInfo']['artinfo']['ppct'] : NULL;
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
		return NULL;
	}
    }

        /**
     * Get a sortable title for the record (i.e. no leading articles).
     *
     * @return string
     */
    public function getSortTitle()
    {
        return isset($this->fields['fields']['header']['controlInfo']['artinfo']['tig']['atl'])
            ? $this->fields['fields']['header']['controlInfo']['artinfo']['tig']['atl'] : '';
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
        return true;
    }

    /**
     * Support method for getOpenURL() -- pick the OpenURL format.
     *
     * @return string
     */
    protected function getOpenURLFormat()
    {
        // If we have multiple formats, Book, Journal and Article are most
        // important...
        $formats = $this->getFormats();
        if (in_array('Book', $formats)) {
            return 'Book';
        } else if (in_array('Article', $formats)) {
            return 'Article';
        } else if (in_array('Journal', $formats)) {
            return 'Journal';
//        } else if (isset($formats[0])) {
//            return $formats[0]; // Problematic because EBSCO has way too many strange options that are really articles to list here.
//        } else if (strlen($this->getCleanISSN()) > 0) {
//            return 'Journal'; // Problematic because we often get to this point with things that are really articles.
        }
	// Defaulting to "Article" because many EBSCO databases have things like "Film Criticism" instead of "Article"
        return 'Article';
    }

    /**
     * Get the OpenURL parameters to represent this record (useful for the
     * title attribute of a COinS span tag).
     *
     * @return string OpenURL parameters.
     */
    public function getOpenURL()
    {
        // Get the COinS ID -- it should be in the OpenURL section of config.ini,
        // but we'll also check the COinS section for compatibility with legacy
        // configurations (this moved between the RC2 and 1.0 releases).
        $coinsID = isset($this->mainConfig->OpenURL->rfr_id)
            ? $this->mainConfig->OpenURL->rfr_id
            : $this->mainConfig->COinS->identifier;
        if (empty($coinsID)) {
            $coinsID = 'vufind.svn.sourceforge.net';
        }

	//Added at Richard and Leslie's request, to facilitate ILL

	$coinsID = $coinsID . ".ebsco";

        // Get a representative publication date:
        $pubDate = $this->getPublicationDates();
        $pubDate = empty($pubDate) ? '' : $pubDate[0];

        // Start an array of OpenURL parameters:
        $params = array(
            'ctx_ver' => 'Z39.88-2004',
            'ctx_enc' => 'info:ofi/enc:UTF-8',
            'rfr_id' => "info:sid/{$coinsID}:generator",
            'rft.title' => $this->getTitle(),
            'rft.date' => $pubDate
        );

        // Add additional parameters based on the format of the record:
        $format = $this->getOpenURLFormat();
        switch ($format) {
        case 'Book':
            $params['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:book';
            $params['rft.genre'] = 'book';
            $params['rft.btitle'] = $params['rft.title'];
            $series = $this->getSeries();
            if (count($series) > 0) {
                // Handle both possible return formats of getSeries:
                $params['rft.series'] = is_array($series[0]) ?
                    $series[0]['name'] : $series[0];
            }
            $params['rft.au'] = $this->getPrimaryAuthor();
            $publishers = $this->getPublishers();
            if (count($publishers) > 0) {
                $params['rft.pub'] = $publishers[0];
            }
            $params['rft.edition'] = $this->getEdition();
            $params['rft.isbn'] = $this->getCleanISBN();
            break;
        case 'Article':
            $params['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:journal';
            $params['rft.genre'] = 'article';
            $params['rft.issn'] = $this->getCleanISSN();
            // an article may have also an ISBN:
            $params['rft.isbn'] = $this->getCleanISBN();
            $params['rft.volume'] = $this->getContainerVolume();
            $params['rft.issue'] = $this->getContainerIssue();
            $params['rft.spage'] = $this->getContainerStartPage();
            // unset default title -- we only want jtitle/atitle here:
            unset($params['rft.title']);
            $params['rft.jtitle'] = $this->getContainerTitle();
            $params['rft.atitle'] = $this->getTitle();
            $params['rft.au'] = $this->getPrimaryAuthor();

            $params['rft.format'] = $format;
            $langs = $this->getLanguages();
            if (count($langs) > 0) {
                $params['rft.language'] = $langs[0];
            }
            break;
        case 'Journal':
            /* This is probably the most technically correct way to represent
             * a journal run as an OpenURL; however, it doesn't work well with
             * Zotero, so it is currently commented out -- instead, we just add
             * some extra fields and then drop through to the default case.
            $params['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:journal';
            $params['rft.genre'] = 'journal';
            $params['rft.jtitle'] = $params['rft.title'];
            $params['rft.issn'] = $this->getCleanISSN();
            $params['rft.au'] = $this->getPrimaryAuthor();
            break;
             */
            $params['rft.issn'] = $this->getCleanISSN();

            // Including a date in a title-level Journal OpenURL may be too
            // limiting -- in some link resolvers, it may cause the exclusion
            // of databases if they do not cover the exact date provided!
            unset($params['rft.date']);

            // If we're working with the SFX resolver, we should add a
            // special parameter to ensure that electronic holdings links
            // are shown even though no specific date or issue is specified:
            if (isset($this->mainConfig->OpenURL->resolver)
                && strtolower($this->mainConfig->OpenURL->resolver) == 'sfx'
            ) {
                $params['sfx.ignore_date_threshold'] = 1;
            }
        default:
            $params['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:dc';
            $params['rft.creator'] = $this->getPrimaryAuthor();
            $publishers = $this->getPublishers();
            if (count($publishers) > 0) {
                $params['rft.pub'] = $publishers[0];
            }
            $params['rft.format'] = $format;
            $langs = $this->getLanguages();
            if (count($langs) > 0) {
                $params['rft.language'] = $langs[0];
            }
            break;
        }

        // Assemble the URL:
        $parts = array();
        foreach ($params as $key => $value) {
            $parts[] = $key . '=' . urlencode($value);
        }
        return implode('&', $parts);
    }

    /**
     * Get an array of publication detail lines combining information from
     * getPublicationDates(), getPublishers() and getPlacesOfPublication().
     *
     * @return array
     */
    public function getPublicationDetails()
    {
//        $places = $this->getPlacesOfPublication(); // place of publication doesn't make sense for a journal
//        $names = $this->getPublishers();
        $dates = $this->getPublicationDates();
	$volume = array($this->getContainerVolume());
	$issue = array($this->getContainerIssue());
	$start = array($this->getContainerStartPage());
	$end = array($this->getContainerEndPage());

        $i = 0;
        $retval = array();
        while (isset($dates[$i]) || isset($volume[$i]) || isset($issue[$i])) {
            // Put all the pieces together, and do a little processing to clean up
            // unwanted whitespace.
            $retval[] = trim(
                str_replace(
                    '  ', ' ',
                    ((isset($volume[$i]) ? "Vol. " . $volume[$i] . ' ' : '') .
                    (isset($issue[$i]) ? "No. " . $issue[$i] . ' ' : '') .
                    (isset($start[$i]) ? "p. " . $start[$i] . ' ' : '') .
                    (isset($end[$i]) ? "- " . $end[$i] . '. ' : '') .
                    (isset($dates[$i]) ? $dates[$i] : ''))
                )
            );
            $i++;
        }

        return $retval;
    }

}
