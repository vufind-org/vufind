<?php
/**
 * Model for MARC records in WorldCat.
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
 * Model for MARC records in WorldCat.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class WorldCat extends SolrMarc
{
    /**
     * Set raw data to initialize the object.
     *
     * @param mixed $data Raw data representing the record; Record Model
     * objects are normally constructed by Record Driver objects using data
     * passed in from a Search Results object.  In this case, $data is a MARCXML
     * document.
     *
     * @return void
     */
    public function setRawData($data)
    {
        // Ensure that $driver->setRawData($driver->getRawData()) doesn't blow up:
        if (isset($data['fullrecord'])) {
            $data = $data['fullrecord'];
        }

        // Make sure the XML has an appropriate header:
        if (strlen($data) > 2 && substr($data, 0, 2) != '<?') {
            $data = '<?xml version="1.0"?>' . $data;
        }

        // Map the WorldCat response into a format that the parent Solr-based
        // record driver can understand.
        parent::setRawData(['fullrecord' => $data]);
    }

    /**
     * Get an array of information about record holdings, obtained in real-time
     * from the ILS.
     *
     * @return array
     */
    public function getRealTimeHoldings()
    {
        // Not supported here:
        return [];
    }

    /**
     * Get an array of information about record history, obtained in real-time
     * from the ILS.
     *
     * @return array
     */
    public function getRealTimeHistory()
    {
        // Not supported here:
        return [];
    }

    /**
     * Returns true if the record supports real-time AJAX status lookups.
     *
     * @return bool
     */
    public function supportsAjaxStatus()
    {
        return false;
    }

    /**
     * Get an array of all ISBNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISBNs()
    {
        return $this->getFieldArray('020');
    }

    /**
     * Get an array of all ISSNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISSNs()
    {
        return $this->getFieldArray('022');
    }

    /**
     * Get an array of all the formats associated with the record.
     *
     * @return array
     */
    public function getFormats()
    {
        return $this->getFieldArray('245', ['h']);
    }

    /**
     * Get the OCLC number of the record.
     *
     * @return array
     */
    public function getOCLC()
    {
        return [$this->getUniqueID()];
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
        return (string)$this->getMarcRecord()->getField('001')->getData();
    }

    /**
     * Get the call numbers associated with the record (empty string if none).
     *
     * @return array
     */
    public function getCallNumbers()
    {
        $retVal = [];
        foreach (['090', '050'] as $field) {
            $callNo = $this->getFirstFieldValue($field, ['a', 'b']);
            if (!empty($callNo)) {
                $retVal[] = $callNo;
            }
        }
        $dewey = $this->getDeweyCallNumber();
        if (!empty($dewey)) {
            $retVal[] = $dewey;
        }
        return $retVal;
    }

    /**
     * Get the Dewey call number associated with this record (empty string if none).
     *
     * @return string
     */
    public function getDeweyCallNumber()
    {
        return $this->getFirstFieldValue('082', ['a']);
    }

    /**
     * Get the main author of the record.
     *
     * @return string
     */
    public function getPrimaryAuthor()
    {
        return $this->getFirstFieldValue('100', ['a']);
    }

    /**
     * Get an array of all the languages associated with the record.
     *
     * @return array
     */
    public function getLanguages()
    {
        $retVal = [];
        $field = $this->getMarcRecord()->getField('008');
        if ($field) {
            $content = $field->getData();
            if (strlen($content) >= 38) {
                $retVal[] = substr($content, 35, 3);
            }
        }
        return $retVal;
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getFirstFieldValue('245', ['a', 'b']);
    }

    /**
     * Get a sortable title for the record (i.e. no leading articles).
     *
     * @return string
     */
    public function getSortTitle()
    {
        $field = $this->getMarcRecord()->getField('245');
        if ($field) {
            $title = $field->getSubfield('a');
            if ($title) {
                $skip = $field->getIndicator(2);
                return substr($title->getData(), $skip);
            }
        }
        return parent::getSortTitle();
    }

    /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        return $this->getFirstFieldValue('245', ['a']);
    }

    /**
     * Get the subtitle of the record.
     *
     * @return string
     */
    public function getSubtitle()
    {
        return $this->getFirstFieldValue('245', ['b']);
    }

    /**
     * Get the publishers of the record.
     *
     * @return array
     */
    public function getPublishers()
    {
        return $this->getPublicationInfo('b');
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
     * Get an array of all secondary authors (complementing getPrimaryAuthor()).
     *
     * @return array
     */
    public function getSecondaryAuthors()
    {
        return $this->getFieldArray('700', ['a', 'b', 'c', 'd']);
    }

    /**
     * Get an array of newer titles for the record.
     *
     * @return array
     */
    public function getNewerTitles()
    {
        return $this->getFieldArray('785', ['a', 's', 't']);
    }

    /**
     * Get an array of previous titles for the record.
     *
     * @return array
     */
    public function getPreviousTitles()
    {
        return $this->getFieldArray('780', ['a', 's', 't']);
    }
}
