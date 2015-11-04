<?php
/**
 * Model for Qualified Dublin Core records in Solr.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2013-2015.
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
 * @author   Anna Pienimäki <anna.pienimaki@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for Qualified Dublin Core records in Solr.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Anna Pienimäki <anna.pienimaki@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrQdc extends \VuFind\RecordDriver\SolrDefault
{
    use SolrFinna;

    /**
     * Record metadata
     *
     * @var \SimpleXMLElement
     */
    protected $simpleXML;

    /**
     * Return an associative array of abstracts associated with this record,
     * if available; false otherwise.
     *
     * @return array of abstracts using abstract languages as keys
     */
    public function getAbstracts()
    {
        $abstractValues = [];
        $abstracts = [];
        $abstract = '';
        $lang = '';
        foreach ($this->getSimpleXML()->xpath('/qualifieddc/abstract') as $node) {
            $abstract = (string)$node;
            $lang = (string)$node['lang'];
            if ($lang == 'en') {
                $lang = 'en-gb';
            }
            $abstracts[$lang] = $abstract;
        }

        return $abstracts;
    }

    /**
     * Return an associative array of image URLs associated with this record
     * (key = URL, value = description).
     *
     * @param string $size Size of requested images
     *
     * @return array
     */
    public function getAllThumbnails($size = 'large')
    {
        return !empty($this->fields['thumbnail'])
            ? [$this->fields['thumbnail'] => $this->fields['thumbnail']] : [];
    }

    /**
     * Return an external URL where a displayable description text
     * can be retrieved from, if available; false otherwise.
     *
     * @return mixed
     */
    public function getDescriptionURL()
    {
        if ($isbn = $this->getCleanISBN()) {
            return 'http://siilo-kk.lib.helsinki.fi/getText.php?query=' . $isbn;
        }
        return false;
    }

    /**
     * Return full record as filtered XML for public APIs.
     *
     * @return string
     */
    public function getFilteredXML()
    {
        $record = clone($this->getSimpleXML());
        while ($record->abstract) {
            unset($record->abstract[0]);
        }
        while ($record->description) {
            unset($record->description[0]);
        }
        return $record->asXML();
    }

    /**
     * Get the original record as a SimpleXML object
     *
     * @return SimpleXMLElement The record as SimpleXML
     */
    protected function getSimpleXML()
    {
        if ($this->simpleXML === null) {
            $this->simpleXML = new \SimpleXMLElement($this->fields['fullrecord']);
        }
        return $this->simpleXML;
    }

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
        parent::setRawData($data);
        $this->simpleXML = null;
    }
}
