<?php
/**
 * Model for LIDO records in Solr.
 *
 * PHP version 5
 *
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
 * Model for LIDO records in Solr.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrLido extends \VuFind\RecordDriver\SolrDefault
{
    use SolrFinna;

    /**
     * Record metadata
     *
     * @var \SimpleXMLElement
     */
    protected $simpleXML;

    /**
     * Date Converter
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config    $mainConfig     VuFind main configuration (omit
     * for built-in defaults)
     * @param \Zend\Config\Config    $recordConfig   Record-specific configuration
     * file (omit to use $mainConfig as $recordConfig)
     * @param \Zend\Config\Config    $searchSettings Search-specific configuration
     * file
     * @param \VuFind\Date\Converter $dateConverter  Date Converter
     */
    public function __construct($mainConfig = null, $recordConfig = null,
        $searchSettings = null, $dateConverter = null
    ) {
        parent::__construct($mainConfig, $recordConfig, $searchSettings);
        $this->dateConverter = $dateConverter;
    }

    /**
     * Return access restriction notes for the record.
     *
     * @return array
     */
    public function getAccessRestrictions()
    {
        $restrictions = [];
        if ($rights = $this->getSimpleXML()->xpath(
            'lido/administrativeMetadata/resourceWrap/resourceSet/rightsResource/'
            . 'rightsType'
        )) {
            foreach ($rights as $right) {
                if (!isset($right->conceptID)) {
                    continue;
                }
                $type = strtolower((string)$right->conceptID->attributes()->type);
                if ($type == 'copyright') {
                    $term = (string)$right->term;
                    if ($term) {
                        $restrictions[] = $term;
                    }
                }
            }
        }
        return $restrictions;
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
        if ($rights = $this->getSimpleXML()->xpath(
            'lido/administrativeMetadata/resourceWrap/resourceSet/rightsResource/'
            . 'rightsType'
        )) {
            $rights = $rights[0];

            if ($conceptID = $rights->xpath('conceptID')) {
                $conceptID = $conceptID[0];
                $attributes = $conceptID->attributes();
                if ($attributes->type
                    && strtolower($attributes->type) == 'copyright'
                ) {
                    $data = [];

                    $copyright = (string)$conceptID;
                    $data['copyright'] = $copyright;

                    $copyright = strtoupper($copyright);
                    if ($link = $this->getRightsLink($copyright, $language)) {
                        $data['link'] = $link;
                    }
                    return $data;
                }
            }
        }
        return false;
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
        $urls = [];
        $url = '';
        foreach ($this->getSimpleXML()->xpath(
            '/lidoWrap/lido/administrativeMetadata/'
            . 'resourceWrap/resourceSet/resourceRepresentation'
        ) as $node) {
            if ($node->linkResource) {
                $attributes = $node->attributes();
                if (!$attributes->type
                    || (($size != 'large' && $attributes->type == 'thumb')
                    || $size == 'large' && $attributes->type == 'large'
                    || $attributes->type == 'zoomview')
                ) {
                    $url = (string)$node->linkResource;
                    $urls[$url] = '';
                }
            }
        }
        return $urls;
    }

    /**
     * Get an array of alternative titles for the record.
     *
     * @return array
     */
    public function getAlternativeTitles()
    {
        $results = [];
        $mainTitle = $this->getTitle();
        foreach ($this->getSimpleXML()->xpath(
            'lido/descriptiveMetadata/objectIdentificationWrap/titleWrap/titleSet/'
            . "appellationValue[@label='teosnimi']"
        ) as $node) {
            if ((string)$node != $mainTitle) {
                $results[] = (string)$node;
            }
        }
        return $results;
    }

    /**
     * Get the collections of the current record.
     *
     * @return string
     */
    public function getCollections()
    {
        return isset($this->fields['collection']) ?
        $this->fields['collection'] : [];
    }

    /**
     * Get an array of events for the record.
     *
     * @return array
     */
    public function getEvents()
    {
        $events = [];
        foreach ($this->getSimpleXML()->xpath(
            '/lidoWrap/lido/descriptiveMetadata/eventWrap/eventSet/event'
        ) as $node) {
            $name = isset($node->eventName->appellationValue)
                ? (string)$node->eventName->appellationValue : '';
            $type = isset($node->eventType->term)
                ? mb_strtolower((string)$node->eventType->term, 'UTF-8') : '';
            $date = isset($node->eventDate->displayDate)
                ? (string)$node->eventDate->displayDate : '';
            if (!$date && isset($node->eventDate->date)
                && !empty($node->eventDate->date)
            ) {
                $startDate = (string)$node->eventDate->date->earliestDate;
                $endDate = (string)$node->eventDate->date->latestDate;
                if (strlen($startDate) == 4 && strlen($endDate) == 4) {
                    $date = "$startDate-$endDate";
                } else {
                    $startDateType = 'Y-m-d';
                    $endDateType = 'Y-m-d';
                    if (strlen($startDate) == 7) {
                        $startDateType = 'Y-m';
                    }
                    if (strlen($endDate) == 7) {
                        $endDateType = 'Y-m';
                    }
                    $date = $this->dateConverter->convertToDisplayDate(
                        $startDateType, $startDate
                    );
                    if ($startDate != $endDate) {
                        $date .= '-' . $this->dateConverter->convertToDisplayDate(
                            $endDateType, $endDate
                        );
                    }
                }
            }
            if ($type == 'valmistus') {
                $confParam = 'lido_augment_display_date_with_period';
                if ($this->getDataSourceConfigurationValue($confParam)) {
                    if ($period = $node->periodName->term) {
                        if ($date) {
                            $date = $period . ', ' . $date;
                        } else {
                            $date = $period;
                        }
                    }
                }
            }
            $method = isset($node->eventMethod->term)
                ? (string)$node->eventMethod->term : '';
            $materials = [];

            if (isset($node->eventMaterialsTech->displayMaterialsTech)) {
                // Use displayMaterialTech (default)
                $materials[] = (string)$node->eventMaterialsTech
                    ->displayMaterialsTech;
            } else if (isset($node->eventMaterialsTech->materialsTech)) {
                // display label not defined, build from materialsTech
                $materials = [];
                foreach ($node->xpath('eventMaterialsTech/materialsTech')
                    as $materialsTech
                ) {
                    if ($terms = $materialsTech->xpath('termMaterialsTech/term')) {
                        foreach ($terms as $term) {
                            $label = null;
                            $attributes = $term->attributes();
                            if (isset($attributes->label)) {
                                // Musketti
                                $label = $attributes->label;
                            } else if (isset($materialsTech->extentMaterialsTech)) {
                                // Siiri
                                $label = $materialsTech->extentMaterialsTech;
                            }
                            if ($label) {
                                $term = "$term ($label)";
                            }
                            $materials[] = $term;
                        }
                    }
                }
            }

            $place = isset($node->eventPlace->displayPlace)
                ? (string)$node->eventPlace->displayPlace : '';
            $places = [];
            if (!$place) {
                if (isset($node->eventPlace->place->namePlaceSet)) {
                    $eventPlace = [];
                    foreach ($node->eventPlace->place->namePlaceSet as $namePlaceSet
                    ) {
                        if (trim((string)$namePlaceSet->appellationValue) != '') {
                            $eventPlace[] = isset($namePlaceSet)
                                ? trim((string)$namePlaceSet->appellationValue) : '';
                        }
                    }
                    $places[] = implode(', ', $eventPlace);
                }
                if (isset($node->eventPlace->place->partOfPlace)) {
                    foreach ($node->eventPlace->place->partOfPlace as $partOfPlace) {
                        $partOfPlaceName = [];
                        while (isset($partOfPlace->namePlaceSet)) {
                            $appellationValue = trim(
                                (string)$partOfPlace->namePlaceSet->appellationValue
                            );
                            if ($appellationValue !== '') {
                                $partOfPlaceName[] = $appellationValue;
                            }
                            $partOfPlace = $partOfPlace->partOfPlace;
                        }
                        $places[] = implode(', ', $partOfPlaceName);
                    }
                }
            } else {
                $places[] = $place;
            }
            $actors = [];
            if (isset($node->eventActor)) {
                foreach ($node->eventActor as $actor) {
                    $appellationValue = $actor->actorInRole->actor->nameActorSet
                        ->appellationValue;
                    if (isset($appellationValue) && trim($appellationValue) != '') {
                        $role = isset($actor->actorInRole->roleActor->term)
                            ? $actor->actorInRole->roleActor->term : '';
                        $actors[] = [
                            'name' => $appellationValue,
                            'role' => $role
                        ];
                    }
                }
            }
            $culture = isset($node->culture->term)
                ? (string)$node->culture->term : '';
            $description = isset($node->eventDescriptionSet->descriptiveNoteValue)
                ? (string)$node->eventDescriptionSet->descriptiveNoteValue : '';
            $event = [
                'type' => $type,
                'name' => $name,
                'date' => $date,
                'method' => $method,
                'materials' => $materials,
                'places' => $places,
                'actors' => $actors,
                'culture' => $culture,
                'description' => $description
            ];
            $events[$type][] = $event;
        }
        return $events;
    }

    /**
     * Get an array of format classifications for the record.
     *
     * @return array
     */
    public function getFormatClassifications()
    {
        $results = [];
        foreach ($this->getSimpleXML()->xpath(
            'lido/descriptiveMetadata/objectClassificationWrap'
        ) as $node) {
            $term = (string)$node->objectWorkTypeWrap->objectWorkType->term;
            if ($term == 'rakennetun ympäristön kohde') {
                foreach ($node->classificationWrap->classification
                    as $classificationNode
                ) {
                    $type = null;
                    $attributes = $classificationNode->attributes();
                    $type = isset($attributes->type) ? $attributes->type : '';
                    if ($type) {
                        $results[] = (string)$classificationNode->term
                            . " $type)";
                    } else {
                        $results[] = (string)$classificationNode->term;
                    }
                }
            } else if ($term == 'arkeologinen kohde') {
                foreach ($node->classificationWrap->classification->term
                    as $classificationNode
                ) {
                    $label = null;
                    $attributes = $classificationNode->attributes();
                    $label = isset($attributes->label) ? $attributes->label : '';
                    if ($label) {
                        $results[] = (string)$classificationNode . " ($label)";
                    } else {
                        $results[] = (string)$classificationNode;
                    }
                }
            }
        }
        return $results;
    }

    /**
     * Get identifier
     *
     * @return array
     */
    public function getIdentifier()
    {
        return isset($this->fields['identifier'])
            ? $this->fields['identifier'] : [];
    }

    /**
     * Return image rights.
     *
     * @param string $language Language
     *
     * @return mixed array with keys:
     *   'copyright'  Copyright (e.g. 'CC BY 4.0') (optional)
     *   'description Human readable description (array)
     *   'link'       Link to copyright info
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
            ? $rights : false
        ;
    }

    /**
     * Get an array of inscriptions for the record.
     *
     * @return array
     */
    public function getInscriptions()
    {
        $results = [];
        foreach ($this->getSimpleXML()->xpath(
            'lido/descriptiveMetadata/objectIdentificationWrap/inscriptionsWrap/'
            . 'inscriptions/inscriptionDescription/descriptiveNoteValue'
        ) as $node) {
            $label = null;
            $attributes = $node->attributes();
            $label = isset($attributes->label) ? $attributes->label : '';
            if ($label) {
                $results[] = (string)$node . ' (' . $label . ')';
            } else {
                $results[] = (string)$node;

            }
        }
        return $results;
    }

    /**
     * Get an array of local identifiers for the record.
     *
     * @return array
     */
    public function getLocalIdentifiers()
    {
        $results = [];
        foreach ($this->getSimpleXML()->xpath(
            'lido/descriptiveMetadata/objectIdentificationWrap/repositoryWrap/'
            . 'repositorySet/workID'
        ) as $node) {
            $type = null;
            $attributes = $node->attributes();
            $type = isset($attributes->type) ? $attributes->type : '';
            // sometimes type exists with empty value or space(s)
            if (($type) && trim((string)$node) != '') {
                $results[] = (string)$node . ' (' . $type . ')';
            }
        }
        return $results;
    }

    /**
     * Get the main format.
     *
     * @return array
     */
    public function getMainFormat()
    {
        if (!isset($this->fields['format'])) {
            return '';
        }
        $formats = $this->fields['format'];
        $format = reset($formats);
        $format = preg_replace('/^\d+\/([^\/]+)\/.*/', '\1', $format);
        return $format;
    }

    /**
     * Get measurements and augment them data source specifically if needed.
     *
     * @return array
     */
    public function getMeasurements()
    {
        $results = [];
        if (isset($this->fields['measurements'])) {
            $results = $this->fields['measurements'];
            $confParam = 'lido_augment_display_measurement_with_extent';
            if ($this->getDataSourceConfigurationValue($confParam)) {
                if ($extent = $this->getSimpleXML()->xpath(
                    'lido/descriptiveMetadata/objectIdentificationWrap/'
                    . 'objectMeasurementsWrap/objectMeasurementsSet/'
                    . 'objectMeasurements/extentMeasurements'
                )) {
                    $results[0] = "$results[0] ($extent[0])";
                }
            }
        }
        return $results;
    }

    /**
     * Get all authors apart from presenters
     *
     * @return array
     */
    public function getNonPresenterAuthors()
    {
        $authors = [];
        foreach ($this->getSimpleXML()->xpath(
            '/lidoWrap/lido/descriptiveMetadata/eventWrap/eventSet/event'
        ) as $node) {
            if (!isset($node->eventActor) || $node->eventType->term != 'valmistus') {
                continue;
            }
            foreach ($node->eventActor as $actor) {
                if (isset($actor->actorInRole->actor->nameActorSet->appellationValue)
                    && trim(
                        $actor->actorInRole->actor->nameActorSet->appellationValue
                    ) != ''
                ) {
                    $role = isset($actor->actorInRole->roleActor->term)
                        ? $actor->actorInRole->roleActor->term : '';
                    $authors[] = [
                        'name' => $actor->actorInRole->actor->nameActorSet
                            ->appellationValue,
                        'role' => $role
                    ];
                }
            }
        }
        return $authors;
    }

    /**
     * Get an array of dates for results list display
     *
     * @return array
     */
    public function getResultDateRange()
    {
        return $this->getDateRange('creation');
    }

    /**
     * Get subject actors
     *
     * @return array
     */
    public function getSubjectActors()
    {
        $results = [];
        foreach ($this->getSimpleXML()->xpath(
            'lido/descriptiveMetadata/objectRelationWrap/subjectWrap/'
            . 'subjectSet/subject/subjectActor/actor/nameActorSet/appellationValue'
        ) as $node) {
            $results[] = (string)$node;
        }
        return $results;
    }

    /**
     * Get subject dates
     *
     * @return array
     */
    public function getSubjectDates()
    {
        $results = [];
        foreach ($this->getSimpleXML()->xpath(
            'lido/descriptiveMetadata/objectRelationWrap/subjectWrap/'
            . 'subjectSet/subject/subjectDate/displayDate'
        ) as $node) {
            $results[] = (string)$node;
        }
        return $results;
    }

    /**
     * Get subject details
     *
     * @return array
     */
    public function getSubjectDetails()
    {
        $results = [];
        foreach ($this->getSimpleXML()->xpath(
            'lido/descriptiveMetadata/objectIdentificationWrap/titleWrap/titleSet/'
            . "appellationValue[@label='aiheen tarkenne']"
        ) as $node) {
            $results[] = (string)$node;
        }
        return $results;
    }

    /**
     * Get subject places
     *
     * @return array
     */
    public function getSubjectPlaces()
    {
        $results = [];
        foreach ($this->getSimpleXML()->xpath(
            'lido/descriptiveMetadata/objectRelationWrap/subjectWrap/'
            . 'subjectSet/subject/subjectPlace/displayPlace'
        ) as $node) {
            $results[] = (string)$node;
        }
        return $results;
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
        $urls = [];
        foreach (parent::getURLs() as $url) {
            if (!$this->urlBlacklisted(
                isset($url['url']) ? $url['url'] : '',
                isset($url['desc']) ? $url['desc'] : ''
            )) {
                $urls[] = $url;
            }
        }
        return $urls;
    }

    /**
     * Get the web resource link from the record.
     *
     * @return mixed
     */
    public function getWebResource()
    {
        $url = $this->getSimpleXML()->xpath(
            'lido/descriptiveMetadata/objectRelationWrap/relatedWorksWrap/'
            . 'relatedWorkSet/relatedWork/object/objectWebResource'
        );
        return isset($url[0]) ? $url[0] : false;
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
     * Is social media sharing allowed (i.e. AddThis Tool).
     *
     * @return boolean
     */
    public function socialMediaSharingAllowed()
    {
        $rights = $this->getSimpleXML()->xpath(
            'lido/administrativeMetadata/resourceWrap/resourceSet/rightsResource/'
            . 'rightsType/conceptID[@type="Social media links"]'
        );
        return empty($rights) || (string)$rights[0] != 'no';
    }

    /**
     * Does a record come from a source that has given data
     * source specific configuration set as true?
     *
     * @param string $confParam string of configuration parameter name
     *
     * @return bool
     */
    protected function getDataSourceConfigurationValue($confParam)
    {
        $datasource = $this->getDataSource();
        return isset($this->recordConfig->$confParam)
            && isset($this->recordConfig->$confParam[$datasource])
            ? $this->recordConfig->$confParam[$datasource] : null;
    }

    /**
     * Get a Date Range from Index Fields
     *
     * @param string $event Event name
     *
     * @return null|array Array of two dates or null if not available
     */
    protected function getDateRange($event)
    {
        if (!isset($this->fields["{$event}_sdaterange"])) {
            return null;
        }
        $range = explode(' ', $this->fields["{$event}_sdaterange"], 2);
        if (!$range) {
            return null;
        }
        $range[0] *= 86400;
        $range[1] *= 86400;
        $startDate = new \DateTime("@{$range[0]}");
        $endDate = new \DateTime("@{$range[1]}");
        if ($startDate->format('m') == 1 && $startDate->format('d') == 1
            && $endDate->format('m') == 12 && $endDate->format('d') == 31
        ) {
            return [$startDate->format('Y'), $endDate->format('Y')];
        }
        return [
            $this->dateConverter->convertToDisplayDate('U', $range[0]),
            $this->dateConverter->convertToDisplayDate('U', $range[1])
        ];
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
}
