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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Eoghan O'Carragain <Eoghan.OCarragan@gmail.com>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @author   Lutz Biedinger <lutz.Biedinger@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrEad extends \VuFind\RecordDriver\SolrDefault
{
    use SolrFinna;

    /**
     * Record metadata
     *
     * @var \SimpleXMLElement
     */
    protected $simpleXML;

    /**
     * Get access restriction notes for the record.
     *
     * @return string[] Notes
     */
    public function getAccessRestrictions()
    {
        $record = $this->getSimpleXML();
        return isset($record->accessrestrict->p)
            ? $record->accessrestrict->p : [];
    }

    /**
     * Return type of access restriction for the record.
     *
     * @param string $language Language
     *
     * @return mixed array with keys:
     *   'copyright'   Copyright (e.g. 'CC BY 4.0')
     *   'link'        Link to copyright info, see IndexRecord::getRightsLink
     *   or false if no access restriction type is defined.
     */
    public function getAccessRestrictionsType($language)
    {
        $record = $this->getSimpleXML();
        if (!isset($record->accessrestrict)) {
            return false;
        }
        $attributes = $record->accessrestrict->attributes();
        if (isset($attributes['type'])) {
            $copyright = (string)$attributes['type'];
            $data = [];
            $data['copyright'] = $copyright;
            if ($link = $this->getRightsLink(strtoupper($copyright), $language)) {
                $data['link'] = $link;
            }
            return $data;
        }
        return false;
    }

    /**
     * Return an associative array of image URLs associated with this record
     * (key = URL, value = description), if available; false otherwise.
     *
     * @param string $size Size of requested images
     *
     * @return mixed
     */
    public function getAllThumbnails($size = 'large')
    {
        $urls = [];
        $url = '';
        $role = $size == 'large'
            ? 'image_reference'
            : 'image_thumbnail';

        foreach ($this->getSimpleXML()
            ->xpath("did/daogrp/daoloc[@role=\"$role\"]") as $node
        ) {
            $url = (string)$node->attributes()->href;
            $urls[$url] = '';
        }

        return $urls;
    }

    /**
     * Get notes on bibliography content.
     *
     * @return string[] Notes
     */
    public function getBibliographyNotes()
    {
        $record = $this->getSimpleXML();
        $bibliography = [];
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
     * Get notes on finding aids related to the record.
     *
     * @return array
     */
    public function getFindingAids()
    {
        $record = $this->getSimpleXML();
        $findingAids = [];
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
        $id = isset($record->did->unitid->attributes()->{'identifier'})
            ? (string)$record->did->unitid->attributes()->{'identifier'}
            : (string)$record->did->unitid;
        return [$id];
    }

    /**
     * Return image rights.
     *
     * @param string $language Language
     *
     * @return mixed array with keys:
     *   'copyright'   Copyright (e.g. 'CC BY 4.0') (optional)
     *   'description' Human readable description (array)
     *   'link'        Link to copyright info
     *   or false if the record contains no images
     */
    public function getImageRights($language)
    {
        if (!count($this->getAllThumbnails())) {
            return false;
        }

        $rights = [];

        if ($type = $this->getAccessRestrictionsType($language)) {
            $rights['copyright'] = $type['copyright'];
            if (isset($type['link'])) {
                $rights['link'] = $type['link'];
            }
        }

        $desc = $this->getAccessRestrictions();
        if ($desc && count($desc)) {
            $description = [];
            foreach ($desc as $p) {
                $description[] = (string)$p;
            }
            $rights['description'] = $description;
        }

        return isset($rights['copyright']) || isset($rights['description'])
            ? $rights : false;
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
     * Get physical locations.
     *
     * @return string[] Physical Locations
     */
    public function getPhysicalLocations()
    {
        $record = $this->getSimpleXML();
        $locations = [];
        if (isset($record->did->physloc)) {
            foreach ($record->did->physloc as $physloc) {
                $locations[] = (string)$physloc;
            }
        }
        return $locations;
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
     * Get an array of external service URLs
     *
     * @return array Array of urls with 'url' and 'desc' keys
     */
    public function getServiceURLs()
    {
        $urls = [];
        $source = $this->getDataSource();
        $config = $this->recordConfig->Record;
        if (isset($config->ead_document_order_link_template[$source])
            && !$this->isDigitized()
            && in_array('1/Document/ArchiveItem/', $this->getFormats())
        ) {
            $urls[] = [
                'url' => $this->replaceURLPlaceholders(
                    $config->ead_document_order_link_template[$source]
                ),
                'desc' => 'ead_document_order'
            ];
        }
        if (isset($config->ead_usage_permission_request_link_template[$source])
            && $this->getAccessRestrictions()
        ) {
            $urls[] = [
                'url' => $this->replaceURLPlaceholders(
                    $config->ead_usage_permission_request_link_template[$source]
                ),
                'desc' => 'ead_usage_permission_request'
            ];
        }
        if (isset($config->ead_external_link_template[$source])) {
            $urls[] = [
                'url' => $this->replaceURLPlaceholders(
                    $config->ead_external_link_template[$source]
                ),
                'desc' => 'ead_external_link_description'
            ];
        }
        return $urls;
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
        return [];
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
    * Return an associative array of URLs associated with this record (key = URL,
    * value = description).
    *
    * @return array
    */
    public function getURLs()
    {
        $urls = [];
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
                $urls[] = [
                    'url' => $url,
                    'desc' => $desc
                ];
            }
        }

        // Portti links parsed from bibliography
        foreach ($record->xpath('//bibliography') as $node) {
            if (preg_match(
                '/(.+) (http:\/\/wiki\.narc\.fi\/portti.*)/',
                (string)$node->p,
                $matches
            )) {
                $urls[] = [
                    'url' => $matches[2],
                    'desc' => $matches[1]
                ];
            }
        }
        return $urls;
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

    /**
     * Get the original record as a SimpleXML object
     *
     * @return SimpleXMLElement The record as SimpleXML
     */
    protected function getSimpleXML()
    {
        if ($this->simpleXML === null) {
            $this->simpleXML = simplexml_load_string($this->fields['fullrecord']);
        }
        return $this->simpleXML;
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
            [
                '{id}',
                '{originationId}',
                '{nonPrefixedOriginationId}'
            ],
            [
                urlencode(reset($this->getIdentifier())),
                urlencode($originationId),
                urlencode($nonPrefixedOriginationId),
            ],
            $url
        );
        return $url;
    }
}