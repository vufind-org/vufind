<?php
/**
 * Model for MARC records in Solr.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Cornelius Amzar <cornelius.amzar@bsz-bw.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Hannah Born <hannah.born@ub.uni-freiburg.de>
 * @author   Jochen Lienhard <jochen.lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
namespace VuFind\RecordDriver;
use VuFind\Exception\ILS as ILSException,
    VuFind\View\Helper\Root\RecordLink,
    VuFind\XSLT\Processor as XSLTProcessor;

/**
 * Model for MARC records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Cornelius Amzar <cornelius.amzar@bsz-bw.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Hannah Born <hannah.born@ub.uni-freiburg.de>
 * @author   Jochen Lienhard <jochen.lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
trait FullMarcTrait
{

    /**
     * Return the list of "source records" for this consortial record.
     *
     * @return array
     */
    public function getConsortialIDs()
    {
        return $this->getFieldArray('035', 'a', true);
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
        return $this->getFieldArray('362', ['a']);
    }

    /**
     * Get the edition of the current record.
     *
     * @return string
     */
    public function getEdition()
    {
        return $this->getFirstFieldValue('250', ['a']);
    }

    /**
     * Get an array of all ISBNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISBNs()
    {
        // ToDo: remove duplex entries; with or without slash
        $isbn = array_merge(
           $this->getFieldArray('020', ['a', 'z', '9'], false), $this->getFieldArray('773', ['z'])
        );
        return $isbn;
    }

    /**
     * Get an array of all ISSNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISSNs()
    {
        $issn = array_merge(
                $this->getFieldArray('022', ['a']), $this->getFieldArray('029', ['a']), 
                $this->getFieldArray('440', ['x']), $this->getFieldArray('490', ['x']), 
                $this->getFieldArray('730', ['x']), $this->getFieldArray('773', ['x']), 
                $this->getFieldArray('776', ['x']), $this->getFieldArray('780', ['x']), 
                $this->getFieldArray('785', ['x'])
        );
        return $issn;
    }

    /**
     * Get an array of all the languages associated with the record.
     *
     * @return array
     */
    public function getLanguages()
    {
        $languages = [];
        $fields = $this->getMarcRecord()->getFields('041');
        foreach ($fields as $field) {
            if (strcmp($field->getIndicator(1), '0') == 0 &&
                    strcmp($field->getIndicator(2), '7') !== 0) {
                foreach ($field->getSubFields('a') as $sf) {
                    $languages[] = $this->translate($sf->getData());
                }
            }
        }
        return $languages;
    }

    /**
     * Get a LCCN, normalised according to info:lccn
     *
     * @return string
     */
    public function getLCCN()
    {
        //lccn = 010a, first
        return $this->getFirstFieldValue('010', ['a']);
    }

    /**
     * Get the OCLC number of the record.
     *
     * @return array
     */
    public function getOCLC()
    {
        $numbers = [];
        $pattern = '(OCoLC)';
        foreach ($this->getFieldArray('016') as $f) {
            if (!strncasecmp($pattern, $f, strlen($pattern))) {
                $numbers[] = substr($f, strlen($pattern));
            }
        }
        return $numbers;
    }

    /**
     * Get an array of physical descriptions of the item.
     *
     * @return array
     */
    public function getPhysicalDescriptions()
    {
        return $this->getFieldArray('300', ['a', 'b', 'c', 'e', 'f', 'g'], true);
    }

    /**
     * Get the publication dates of the record.  See also getDateSpan().
     *
     * @return array
     */
    public function getPublicationDates()
    {
        return $this->getPublicationInfo('c');
    }

    /**
     * Get the main author of the record.
     *
     * @return string
     */
    public function getPrimaryAuthor()
    {
        return trim($this->getFirstFieldValue('100', ['a']));
    }

    /**
     * Get the main authors of the record.
     *
     * @return string
     */
    public function getPrimaryAuthors()
    {
        return $this->getFieldArray('100', 'a', true);
    }

    /**
     * Get the publishers of the record.
     *
     * @return array
     */
    public function getPublishers()
    {
        $fields = [
            260 => 'b',
            264 => 'b',
        ];
        return $this->getFieldArray($fields);
    }

    /**
     * Get an array of all secondary authors (complementing getPrimaryAuthor()).
     *
     * @return array
     */
    public function getSecondaryAuthors()
    {
        $other_author = array_merge(
            $this->getFieldArray('110', ['a', 'b']),
            $this->getFieldArray('111', ['a', 'b']),
            $this->getFieldArray('700', ['a', 'b', 'c', 'd']),
            $this->getFieldArray('710', ['a', 'b']),
            $this->getFieldArray('711', ['a', 'b'])
        );
        return $other_author;
    }

   /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        $shortTitle = $this->getFirstFieldValue('245', array('a'), false);

        // remove sorting char 
        if (strpos($shortTitle, '@') !== false) {
            $occurrence = strpos($shortTitle, '@');
            $shortTitle = substr_replace($shortTitle, '', $occurrence, 1);
        }

        return trim($shortTitle);
    }

    /**
     * Get the subtitle of the record.
     *
     * @return string
     */
    public function getSubtitle()
    {
        $subTitle = $this->getFirstFieldValue('245', array('b'), false);

        // remove sorting character 
        if (strpos($subTitle, '@') !== false) {
            $occurrence = strpos($subTitle, '@');
            $subTitle = substr_replace($subTitle, '', $occurrence, 1);
        }

        return trim($subTitle);
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        $title = "";
        
        $stit = false;
        $subt = false;
        if (strlen($this->getShortTitle()) > 0) {
            $title .= $this->getShortTitle();
            $stit = true;
        }
        if (strlen($this->getSubtitle()) > 0) {
            if ($stit) { 
               $title .= ": ";
            }
            $title .= $this->getSubtitle();
            $subt = true;
        }
        if (strlen($this->getEdition()) > 0) {
            if ($stit || $subt) {
               $title .= " - ";
            }
            $title .= $this->getEdition(); 
        }
        return trim($title);
    }


// maybe in a DefaultTrait

    /**
     * Get highlighted author data, if available.
     *
     * @return array
     */
    public function getRawAuthorHighlights()
    {
        // Don't check for highlighted values if highlighting is disabled:
        return ($this->highlight && isset($this->highlightDetails['author']))
            ? $this->highlightDetails['author'] : [];
    }

    /**
     * Get primary author information with highlights applied (if applicable)
     *
     * @return array
     */
    public function getPrimaryAuthorsWithHighlighting()
    {
        $highlights = [];
        // Create a map of de-highlighted valeus => highlighted values.
        foreach ($this->getRawAuthorHighlights() as $current) {
            $dehighlighted = str_replace(
                ['{{{{START_HILITE}}}}', '{{{{END_HILITE}}}}'], '', $current
            );
            $highlights[$dehighlighted] = $current;
        }

        // replace unhighlighted authors with highlighted versions where
        // applicable:
        $authors = [];
        foreach ($this->getPrimaryAuthors() as $author) {
            $authors[] = isset($highlights[$author])
                ? $highlights[$author] : $author;
        }
        return $authors;
    }

}
