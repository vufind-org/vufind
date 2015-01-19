<?php
/**
 * Model for EAD records in Solr.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2012-2015.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for EAD records in Solr.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Eoghan O'Carragain <Eoghan.OCarragan@gmail.com>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @author   Lutz Biedinger <lutz.Biedinger@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrEad extends \VuFind\RecordDriver\SolrDefault
{
    /**
     * Record metadata
     *
     * @var \SimpleXMLElement
     */
    protected $simpleXML;

    /**
     * Translator
     *
     * @var \VuFind\Translator
     */
    protected $translator;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config   $mainConfig     VuFind main configuration (omit
     * for built-in defaults)
     * @param \Zend\Config\Config   $recordConfig   Record-specific configuration
     * file (omit to use $mainConfig as $recordConfig)
     * @param \Zend\Config\Config   $searchSettings Search-specific configuration
     * file
     * @param \Zend\I18n\Translator $translator     Translator
     */
    public function __construct($mainConfig = null, $recordConfig = null,
        $searchSettings = null, $translator = null
    ) {
        parent::__construct($mainConfig, $recordConfig, $searchSettings);
        $this->translator = $translator;
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
        $xmlRecord = null;
    }

    /**
     * Check if record is part of an archive series.
     *
     * A record is assumed to be part of a series if it has a parent that's not
     * the top-level record in the hierarchy.
     *
     * @return bool Whether the record is part of an archive series
     */
    public function isPartOfArchiveSeries()
    {
        return isset($this->fields['hierarchy_parent_id'][0])
            && isset($this->fields['hierarchy_top_id'][0])
            && $this->fields['hierarchy_parent_id'][0]
                != $this->fields['hierarchy_top_id'][0]
            && $this->fields['hierarchy_top_id'] != $this->fields['id'];
    }

    /**
     * Get origination
     *
     * @return string
     */
    public function getOrigination()
    {
        $record = $this->getSimpleXML();
        return isset($record->did->origination)
            ? (string)$record->did->origination->corpname : '';
    }

    /**
     * Get origination Id
     *
     * @return string
     */
    public function getOriginationId()
    {
        $record = $this->getSimpleXML();
        return isset($record->did->origination->corpname)
            ? (string)$record->did->origination->corpname
                ->attributes()->authfilenumber
            : '';
    }

    /**
     * Return image rights.
     *
     * @return mixed array with keys:
     *   'copyright'   Copyright (e.g. 'CC BY 4.0') (optional)
     *   'description' Human readable description (array)
     *   'link'        Link to copyright info
     *   or false if the record contains no images
     */
    public function getImageRights()
    {
        if (!count($this->getAllImages())) {
            return false;
        }

        $rights = array();

        if ($type = $this->getAccessRestrictionsType()) {
            $rights['copyright'] = $type['copyright'];
            if (isset($type['link'])) {
                $rights['link'] = $type['link'];
            }
        }

        $desc = $this->getAccessRestrictions();
        if ($desc && count($desc)) {
            $description = array();
            foreach ($desc as $p) {
                $description[] = (string)$p;
            }
            $rights['description'] = $description;
        }

        return isset($rights['copyright']) || isset($rights['description'])
            ? $rights : false;
    }

    /**
     * Get access restriction notes for the record.
     *
     * @return string[] Notes
     */
    public function getAccessRestrictions()
    {
        $record = $this->getSimpleXML();
        return isset($record->accessrestrict->p)
            ? $record->accessrestrict->p : array();
    }

    /**
     * Get type of access restriction for the record.
     *
     * @return mixed array with keys:
     *   'copyright'   Copyright (e.g. 'CC BY 4.0')
     *   'link'        Link to copyright info, see IndexRecord::getRightsLink
     *   or false if no access restriction type is defined.
     */
    public function getAccessRestrictionsType()
    {
        $record = $this->getSimpleXML();
        if (!isset($record->accessrestrict)) {
            return false;
        }
        $attributes = $record->accessrestrict->attributes();
        if (isset($attributes['type'])) {
            $copyright = (string)$attributes['type'];
            $data = array();
            $data['copyright'] = $copyright;
            if ($link = $this->getRightsLink(strtoupper($copyright))) {
                $data['link'] = $link;
            }
            return $data;
        }
        return false;
    }

    /**
     * Get notes on bibliography content.
     *
     * @return string[] Notes
     */
    public function getBibliographyNotes()
    {
        $record = $this->getSimpleXML();
        $bibliography = array();
        foreach ($record->xpath('//bibliography') as $node) {
            // Filter out Portti links since they're displayed in links
            if (!preg_match(
                '/(.+) (http:\/\/wiki\.narc\.fi\/portti.*)/', (string)$node->p
            )) {
                $bibliography[] = (string)$node->p;
            }
        }
        return $bibliography;
    }

    /**
     * Get physical locations.
     *
     * @return string[] Physical Locations
     */
    public function getPhysicalLocations()
    {
        $record = $this->getSimpleXML();
        $locations = array();
        if (isset($record->did->physloc)) {
            foreach ($record->did->physloc as $physloc) {
                $locations[] = (string)$physloc;
            }
        }
        return $locations;
    }

    /**
     * Get notes on finding aids related to the record.
     *
     * @return array
     */
    public function getFindingAids()
    {
        $record = $this->getSimpleXML();
        $findingAids = array();
        if (isset($this->record->otherfindaid->p)) {
            foreach ($this->record->otherfindaid->p as $p) {
                $findingAids[] = (string)$p;
            }
        }
        return $findingAids;
    }

    /**
     * Get identifier
     *
     * @return array
     */
    public function getIdentifier()
    {
        $record = $this->getSimpleXML();
        return isset($record->did->unitid->attributes()->{'identifier'})
            ? (string)$record->did->unitid->attributes()->{'identifier'}
            : (string)$record->did->unitid;
    }

    /**
     * Get an array of physical descriptions of the item.
     *
     * @return array
     */
    public function getPhysicalDescriptions()
    {
        $physDesc = parent::getPhysicalDescriptions();
        if (isset($this->fields['material'])) {
            $physDesc = array_merge($physDesc, $this->fields['material']);
        }
        return $physDesc;
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
        // side. If needed, handle a special case where the indexed description
        // consists of several joined paragraphs:
        if (isset($this->fields['description'])
            && !empty($this->fields['description'])
        ) {
            return is_array($this->fields['description'])
                ? $this->fields['description']
                : explode('   /   ', $this->fields['description']);
        }

        // If we got this far, no description was found:
        return array();
    }

    /**
     * Check if record is digitized.
     *
     * @return boolean True if the record is digitized
     */
    public function isDigitized()
    {
        $record = $this->getSimpleXML();
        return $record->did->daogrp ? true : false;
    }

    /**
    * Return an associative array of URLs associated with this record (key = URL,
    * value = description).
    *
    * @return array
    */
    public function getURLs()
    {
        $urls = array();
        $url = '';
        $record = $this->getSimpleXML();
        foreach ($record->xpath('//daoloc') as $node) {
            $url = (string)$node->attributes()->href;
            if (isset($node->attributes()->role)
                && $node->attributes()->role == 'image_thumbnail'
            ) {
                continue;
            }

            $desc = $url;
            if ($node->daodesc) {
                if ($node->daodesc->p) {
                    $desc = (string)$node->daodesc->p;
                } else {
                    $desc = (string)$node->daodesc;
                }
            } else {
                if ($p = $node->xpath('parent::*/daodesc/p')) {
                    $desc = $p[0];
                }
            }
            if (!$this->urlBlacklisted($url, $desc)) {
                $urls[] = array(
                    'url' => $url,
                    'desc' => $desc
                );
            }
        }

        // Portti links parsed from bibliography
        foreach ($record->xpath('//bibliography') as $node) {
            if (preg_match(
                '/(.+) (http:\/\/wiki\.narc\.fi\/portti.*)/',
                (string)$node->p,
                $matches
            )) {
                $urls[] = array(
                    'url' => $matches[2],
                    'desc' => $matches[1]
                );
            }
        }
        return $urls;
    }

    /**
     * Get an array of external service URLs
     *
     * @return array Array of urls with 'url' and 'desc' keys
     */
    public function getServiceURLs()
    {
        $urls = array();
        $source = $this->getDataSource();
        $config = $this->recordConfig->Record;
        if (isset($config->ead_document_order_link_template[$source])
            && !$this->isDigitized()
            && in_array(
                $this->translator->translate('1/Document/ArchiveItem/'),
                $this->getFormats()
            )
        ) {
            $urls[] = array(
                'url' => $this->replaceURLPlaceholders(
                    $config->ead_document_order_link_template[$source]
                ),
                'desc' => 'ead_document_order'
            );
        }
        if (isset($config->ead_usage_permission_request_link_template[$source])
            && $this->getAccessRestrictions()
        ) {
            $urls[] = array(
                'url' => $this->replaceURLPlaceholders(
                    $config->ead_usage_permission_request_link_template[$source]
                ),
                'desc' => 'ead_usage_permission_request'
            );
        }
        if (isset($config->ead_external_link_template[$source])) {
            $urls[] = array(
                'url' => $this->replaceURLPlaceholders(
                    $config->ead_external_link_template[$source]
                ),
                'desc' => 'ead_external_link_description'
            );
        }
        return $urls;
    }

    /**
     * Get data source id
     *
     * @return string
     */
    public function getDataSource()
    {
        return isset($this->fields['datasource_str_mv'])
            ? $this->fields['datasource_str_mv'][0]
            : '';
    }

    /**
     * Get unit ID (for reference)
     *
     * @return string Unit ID
     */
    public function getUnitID()
    {
        $unitId = $this->getSimpleXML()->xpath('did/unitid');
        if (count($unitId)) {
            return (string)$unitId[0];
        }
        return '';
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
        return simplexml_load_string($this->fields['fullrecord']);
    }

    /**
     * Replace placeholders in the URL with the values from the record
     *
     * @param string $url URL
     *
     * @return string URL with placeholders replaced
     */
    protected function replaceURLPlaceholders($url)
    {
        $originationId = $this->getOriginationId();
        list(, $nonPrefixedOriginationId) = explode('-', $originationId, 2);
        $url = str_replace(
            array(
                '{id}',
                '{originationId}',
                '{nonPrefixedOriginationId}'
            ),
            array(
                urlencode($this->getUniqueID()),
                urlencode($originationId),
                urlencode($nonPrefixedOriginationId),
            ),
            $url
        );
        return $url;
    }

    /**
     * Check if a URL (typically from getURLs()) is blacklisted based on the URL
     * itself and optionally its description.
     *
     * @param string $url  URL
     * @param string $desc Optional description of the URL
     *
     * @return boolean Whether the URL is blacklisted
     */
    protected function urlBlacklisted($url, $desc = '')
    {
        if (!isset($this->recordConfig->Record->url_blacklist)) {
            return false;
        }
        foreach ($this->recordConfig->Record->url_blacklist as $rule) {
            if (substr($rule, 0, 1) == '/' && substr($rule, -1, 1) == '/') {
                if (preg_match($rule, $url)
                    || ($desc !== '' && preg_match($rule, $desc))
                ) {
                    return true;
                }
            } elseif ($rule == $url || $rule == $desc) {
                return true;
            }
        }
        return false;
    }
}