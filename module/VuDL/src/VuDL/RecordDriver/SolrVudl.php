<?php
/**
 * Model for VuDL records in Solr.
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
namespace VuDL\RecordDriver;

/**
 * Model for VuDL records in Solr.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrVudl extends \VuFind\RecordDriver\SolrDefault
{
    /**
     * Full VuDL record
     *
     * @var \SimpleXML
     */
    protected $fullRecord = null;

    /**
     * VuDL config
     *
     * @var \Zend\Config\Config
     */
    protected $vuDLConfig = null;

    /**
     * Set module.config.php
     *
     * @param \Zend\Config\Config $vc Configuration
     *
     * @return void
     */
    public function setVuDLConfig(\Zend\Config\Config $vc)
    {
        $this->vuDLConfig = $vc;
    }

    /**
     * Parse the full record, if required, and return it.
     *
     * @return object
     */
    public function getFullRecord()
    {
        if (is_null($this->fullRecord)) {
            $xml = $this->fields['fullrecord'];
            $this->fullRecord = simplexml_load_string($xml);
        }
        return $this->fullRecord;
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
        return [
            'route' => 'files',
            'routeParams' => [
                'id' => $this->getUniqueID(),
                'type' => 'THUMBNAIL'
            ]
        ];
        // We are currently storing only one size of thumbnail; we'll use this for
        // small and medium sizes in the interface, flagging "large" as unavailable
        // for now.
        if ($size == 'large') {
            return false;
        }
        $result = $this->getFullRecord();
        $thumb = isset($result->thumbnail) ? trim($result->thumbnail) : false;
        return empty($thumb) ? false : $thumb;
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
        if ($this->isCollection()) {
            return [];
        } else {
            $retval = [
                [
                    'route' => 'vudl-record',
                    'routeParams' => [
                        'id' => $this->getUniqueID()
                    ]
                ]
            ];
            if ($this->isProtected()) {
                $retval[0]['prefix'] = $this->getProxyUrl();
            }
            return $retval;
        }
    }

    /**
     * Does this record need to be protected from public access?
     *
     * @return bool
     */
    public function isProtected()
    {
        // Is license_str set?
        if (!isset($this->fields['license_str'])) {
            return false;
        }
        // Check IP range
        $userIP = $_SERVER['REMOTE_ADDR'];
        $range = isset($this->vuDLConfig->Access->ip_range)
            ? $this->vuDLConfig->Access->ip_range->toArray() : [];
        foreach ($range as $ip) {
            if (strpos($userIP, $ip) === 0) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get the proxy prefix for protecting this record.
     *
     * @return string
     */
    protected function getProxyURL()
    {
        return isset($this->vuDLConfig->Access->proxy_url)
            ? $this->vuDLConfig->Access->proxy_url : '';
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
        $retVal = [];
        if (isset($this->fields['topic'])) {
            foreach ($this->fields['topic'] as $current) {
                $retVal[] = explode(' -- ', $current);
            }
        }
        return $retVal;
    }
}
