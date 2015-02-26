<?php
/**
 * Model for Pazpar2 records.
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/other_than_marc Wiki
 */
namespace VuFind\RecordDriver;

/**
 * Model for Pazpar2 records.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/other_than_marc Wiki
 */
class Pazpar2 extends SolrDefault
{
    /**
     * Pazpar2 fields
     *
     * @var array
     */
    protected $pz2fields = [];

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
        $this->pz2fields = $this->xmlToArray($data);
    }

    /**
     * Converts a SimpleXMLElement to an array
     *
     * @param \SimpleXMLElement $xml to be converted
     *
     * @return associative array of converted XML
     */
    protected function xmlToArray($xml)
    {
        $array = [];
        foreach ($xml as $key => $data) {
            $children = [];
            // Attributes
            if (count($data->attributes()) > 0) {
                $children['_attr_'] = [];
                foreach ($data->attributes() as $name => $attr) {
                    $children['_attr_'][$name] = (string) $attr;
                }
            }
            // If there's no children, we're at data
            if ($data->count() == 0) {
                if (!isset($children['_attr_'])) {
                    $children = (string) $data; // Flatten
                } else {
                    $children[$key] = (string) $data;
                }
            } else {
                // If there's children, recurse on this XML
                $children = $this->xmlToArray($data);
            }
            // If first child with this name
            if (!isset($array[$key])) {
                $array[$key] = $children;
            } else {
                if (is_array($array[$key])
                    && is_numeric(current(array_keys($array[$key])))
                ) {
                    $array[$key][] = $children;
                } else {
                    // Convert for multiple children
                    $array[$key] = [
                        $array[$key],
                        $children
                    ];
                }
            }
        }
        // Top-level Attributes
        if (count($xml->attributes()) > 0) {
            $array['_attr_'] = [];
            foreach ($xml->attributes() as $key => $attr) {
                $array['_attr_'][$key] = (string) $attr;
            }
        }
        return $array;
    }

    /**
     * Return the unique identifier of this record within the Solr index;
     * useful for retrieving additional information (like tags and user
     * comments) from the external MySQL database.
     *
     * @return string Unique identifier.
     */
    public function getUniqueId()
    {
        return isset($this->pz2fields['location']['md-id'])
            ? $this->pz2fields['location']['md-id']
            : $this->pz2fields['recid'];
    }

    /**
     * Get the main author of the record.
     *
     * @return string
     */
    public function getPrimaryAuthor()
    {
        $authors = isset($this->pz2fields['md-author']) ?
            $this->pz2fields['md-author'] : '';

        return is_array($authors) ? $authors[0] : $authors;
    }

    /**
     * Get the providers of the record.
     *
     * @return array
     */
    public function getProviders()
    {
        if (!$this->pz2fields['location']) {
            return [];
        }
        if (isset($this->pz2fields['location']['_attr_'])) {
            return [$this->pz2fields['location']['_attr_']['name']];
        }
        $providers = [];
        foreach ($this->pz2fields['location'] as $location) {
            if (isset($location['_attr_']['name'])
                && !in_array($location['_attr_']['name'], $providers)
            ) {
                $providers[] = $location['_attr_']['name'];
            }
        }
        return $providers;
    }

    /**
     * Get the publication dates of the record.  See also getDateSpan().
     *
     * @return array
     */
    public function getPublicationDates()
    {
        return isset($this->pz2fields['md-date']) ?
            [$this->pz2fields['md-date']] : [];
    }

    /**
     * Get an array of all secondary authors (complementing getPrimaryAuthor()).
     *
     * @return array
     */
    public function getSecondaryAuthors()
    {
        $authors = isset($this->pz2fields['md-author']) ?
            $this->pz2fields['md-author'] : '';

        return is_array($authors) ? array_slice($authors, 1) : [];
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getShortTitle();
    }

    /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        return isset($this->pz2fields['md-title']) ?
            $this->pz2fields['md-title'] : '';
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
        if (isset($this->pz2fields['location']['md-electronic-url'])) {
            return array_map(
                function ($url) {
                    return ['url' => $url];
                },
                (array) $this->pz2fields['location']['md-electronic-url']
            );
        }
        return [];
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
        return AbstractBase::openURLActive($area);
    }

    /**
     * Support method for getOpenURL() -- pick the OpenURL format.
     *
     * @return string
     */
    protected function getOpenURLFormat()
    {
        return 'Book';
    }
}
