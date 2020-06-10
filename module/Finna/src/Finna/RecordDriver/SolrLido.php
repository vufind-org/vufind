<?php
/**
 * Model for LIDO records in Solr.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2020.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for LIDO records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrLido extends \VuFind\RecordDriver\SolrDefault
{
    use SolrFinnaTrait;

    /**
     * Record metadata
     *
     * @var \SimpleXMLElement
     */
    protected $simpleXML;

    /**
     * Blacklist for undisplayable file formats
     *
     * @var array
     */
    protected $fileFormatBlackList = [];

    /**
     * Images cache
     *
     * @var array
     */
    protected $cachedImages;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $mainConfig     VuFind main configuration (omit for
     * built-in defaults)
     * @param \Zend\Config\Config $recordConfig   Record-specific configuration file
     * (omit to use $mainConfig as $recordConfig)
     * @param \Zend\Config\Config $searchSettings Search-specific configuration file
     */
    public function __construct($mainConfig = null, $recordConfig = null,
        $searchSettings = null
    ) {
        if (isset($mainConfig['Content']['lidoFileFormatBlackList'])) {
            $blackList = $mainConfig['Content']['lidoFileFormatBlackList'];
            $this->fileFormatBlackList = explode(',', $blackList);
        }
        parent::__construct($mainConfig, $recordConfig, $searchSettings);
    }

    /**
     * Return access restriction notes for the record.
     *
     * @param string $language Optional primary language to look for
     *
     * @return array
     */
    public function getAccessRestrictions($language = '')
    {
        $restrictions = [];
        $rights = $this->getSimpleXML()->xpath(
            'lido/administrativeMetadata/resourceWrap/resourceSet/rightsResource/'
            . 'rightsType'
        );
        if ($rights) {
            foreach ($rights as $right) {
                if (!isset($right->conceptID)) {
                    continue;
                }
                $type = strtolower((string)$right->conceptID->attributes()->type);
                if ($type == 'copyright') {
                    $term = (string)$this->getLanguageSpecificItem(
                        $right->term, $language
                    );
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
        $rights = $this->getSimpleXML()->xpath(
            'lido/administrativeMetadata/resourceWrap/resourceSet/rightsResource/'
            . 'rightsType'
        );
        if ($rights) {
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
     * Return an array of image URLs associated with this record with keys:
     * - urls        Image URLs
     *   - small     Small image (mandatory)
     *   - medium    Medium image (mandatory)
     *   - large     Large image (optional)
     * - description Description text
     * - rights      Rights
     *   - copyright   Copyright (e.g. 'CC BY 4.0') (optional)
     *   - description Human readable description (array)
     *   - link        Link to copyright info
     *
     * @param string $language Language for copyright information
     *
     * @return array
     */
    public function getAllImages($language = 'fi')
    {
        if (null !== $this->cachedImages) {
            return $this->cachedImages;
        }

        $result = [];
        $defaultRights = $this->getImageRights($language, true);
        foreach ($this->getSimpleXML()->xpath(
            '/lidoWrap/lido/administrativeMetadata/'
            . 'resourceWrap/resourceSet'
        ) as $resourceSet) {
            if (empty($resourceSet->resourceRepresentation->linkResource)) {
                continue;
            }
            // Process rights first since we may need to duplicate them if there
            // are multiple images in the set (non-standard)
            $rights = [];
            if (!empty($resourceSet->rightsResource->rightsType->conceptID)) {
                $conceptID = $resourceSet->rightsResource->rightsType
                    ->conceptID;
                $type = strtolower((string)$conceptID->attributes()->type);
                if ($type == 'copyright') {
                    $rights['copyright'] = (string)$conceptID;
                    $link = $this->getRightsLink(
                        $rights['copyright'], $language
                    );
                    if ($link) {
                        $rights['link'] = $link;
                    }
                }
            }
            if (!empty($resourceSet->rightsResource->rightsType->term)) {
                $term = (string)$this->getLanguageSpecificItem(
                    $resourceSet->rightsResource->rightsType->term, $language
                );
                if (!isset($rights['copyright']) || $rights['copyright'] !== $term) {
                    $rights['description'][] = $term;
                }
            }
            if (empty($rights)) {
                $rights = $defaultRights;
            }
            $urls = [];
            $highResolution = [];
            foreach ($resourceSet->resourceRepresentation as $representation) {
                $linkResource = $representation->linkResource;
                $attributes = $representation->attributes();
                if (empty((string)$linkResource)) {
                    continue;
                }
                if (!empty($this->fileFormatBlackList)
                    && isset($linkResource->attributes()->formatResource)
                    && $attributes->type !== 'image_original'
                ) {
                    $format = trim(
                        (string)$linkResource->attributes()->formatResource
                    );
                    $formatDisallowed
                        = in_array(strtolower($format), $this->fileFormatBlackList);
                    if ($formatDisallowed) {
                        continue;
                    }
                }

                $size = '';
                switch ($attributes->type) {
                case 'image_thumb':
                case 'thumb':
                    $size = 'small';
                    break;
                case 'medium':
                    $size = 'medium';
                    break;
                case 'image_large':
                case 'large':
                case 'zoomview':
                    $size = 'large';
                    break;
                case 'image_master':
                    $size = 'master';
                    break;
                case 'image_original':
                    $size = 'original';
                    break;
                }

                $url = (string)$linkResource;
                if (!$size) {
                    if ($urls) {
                        // We already have URL's, store them in the results first.
                        // This shouldn't happen unless there are multiple images
                        // without type in the same set.
                        $result[] = [
                            'urls' => $urls,
                            'description' => '',
                            'rights' => $rights
                        ];
                    }
                    $urls['small'] = $urls['medium'] = $urls['large'] = $url;
                } else {
                    $urls[$size] = $url;
                }

                if ($size === 'master' || $size === 'original') {
                    $currentHiRes = [];
                    $currentHiRes['data']
                        = $this->formatImageMeasurements(
                            $representation->resourceMeasurementsSet
                        );
                    $currentHiRes['url'] = (string)$linkResource;
                    if (!empty($resourceSet->resourceID)) {
                        $currentHiRes['resourceID']
                            = (int)$resourceSet->resourceID;
                    }
                    $format = (string)$linkResource->attributes()->formatResource;

                    $highResolution[$size][$format ?: 'jpg'] = $currentHiRes;
                }
            }
            // If current set has no images to show, continue to next one
            if (empty($urls)) {
                continue;
            }
            if (!isset($urls['small'])) {
                $urls['small'] = $urls['medium']
                    ?? $urls['large'];
            }
            if (!isset($urls['medium'])) {
                $urls['medium'] = $urls['small']
                    ?? $urls['large'];
            }
            $result[] = [
                'urls' => $urls,
                'description' => '',
                'rights' => $rights,
                'highResolution' => $highResolution
            ];
        }
        return $this->cachedImages = $result;
    }

    /**
     * Function to format given resourceMeasurementsSet to readable format
     *
     * @param object $measurements of the image
     * @param string $language     to search data for
     *
     * @return array
     */
    public function formatImageMeasurements($measurements, $language = 'en')
    {
        $data = [];
        foreach ($measurements as $set) {
            if (!isset($set->measurementValue)
                || empty((string)$set->measurementValue)
            ) {
                continue;
            }
            $type = '';
            foreach ($set->measurementType as $t) {
                if ((string)$t->attributes()->lang !== $language) {
                    continue;
                }
                $type = trim((string)$t);
                break;
            }
            $unit = '';
            foreach ($set->measurementUnit as $u) {
                if ((string)$u->attributes()->lang !== $language) {
                    continue;
                }
                $unit = trim((string)$u);
                break;
            }

            $value = trim((string)$set->measurementValue);
            $data[$type] = compact('unit', 'value');
        }
        return $data;
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
     * @return array
     */
    public function getCollections()
    {
        $results = [];
        $allowedTypes = ['Kokoelma', 'kuuluu kokoelmaan', 'kokoelma', 'Alakokoelma',
            'Erityiskokoelma'];
        foreach ($this->getSimpleXML()->xpath(
            'lido/descriptiveMetadata/objectRelationWrap/relatedWorksWrap/'
            . 'relatedWorkSet'
        ) as $node) {
            $term = isset($node->relatedWorkRelType->term)
             ? $node->relatedWorkRelType->term : '';
            if (in_array($term, $allowedTypes)) {
                $results[] = (string)$node->relatedWork->displayObject;
            }
        }
        return $results;
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

                    $date = $this->dateConverter
                        ? $this->dateConverter->convertToDisplayDate(
                            $startDateType, $startDate
                        )
                        : $startDate;

                    if ($startDate != $endDate) {
                        $date .= '-' . ($this->dateConverter
                            ? $this->dateConverter->convertToDisplayDate(
                                $endDateType, $endDate
                            )
                            : $endDate);
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
            } elseif (isset($node->eventMaterialsTech->materialsTech)) {
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
                            } elseif (isset($materialsTech->extentMaterialsTech)) {
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

            $places = [];
            $place = isset($node->eventPlace->displayPlace)
                ? (string)$node->eventPlace->displayPlace : '';
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
                    if ($eventPlace) {
                        $places[] = implode(', ', $eventPlace);
                    }
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
                        if ($partOfPlaceName) {
                            $places[] = implode(', ', $partOfPlaceName);
                        }
                    }
                }
            } else {
                $places[] = $place;
            }
            $actors = [];
            if (isset($node->eventActor)) {
                foreach ($node->eventActor as $actor) {
                    $appellationValue = isset(
                        $actor->actorInRole->actor->nameActorSet->appellationValue
                    ) ? trim(
                        $actor->actorInRole->actor->nameActorSet->appellationValue
                    ) : '';
                    if ($appellationValue !== '') {
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
            // Only add the event if it has content
            foreach ($event as $key => $field) {
                if ('type' !== $key && !empty($field)) {
                    $events[$type][] = $event;
                    break;
                }
            }
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
                            . " ($type)";
                    } else {
                        $results[] = (string)$classificationNode->term;
                    }
                }
            } elseif ($term == 'arkeologinen kohde') {
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
     * @param string $language       Language
     * @param bool   $skipImageCheck Whether to check that images exist
     *
     * @return mixed array with keys:
     *   'copyright'  Copyright (e.g. 'CC BY 4.0') (optional)
     *   'description Human readable description (array)
     *   'link'       Link to copyright info
     *   or false if the record contains no images
     */
    public function getImageRights($language, $skipImageCheck = false)
    {
        if (!$skipImageCheck && !$this->getAllImages()) {
            return false;
        }

        $rights = [];

        if ($type = $this->getAccessRestrictionsType($language)) {
            $rights['copyright'] = $type['copyright'];
            if (isset($type['link'])) {
                $rights['link'] = $type['link'];
            }
        }

        $desc = $this->getAccessRestrictions($language);
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
            . 'inscriptions'
        ) as $inscriptions) {
            $group = [];
            foreach ($inscriptions->inscriptionDescription as $node) {
                $content = (string)$node->descriptiveNoteValue;
                $type = $node->attributes()->type ?? '';
                $label = $node->descriptiveNoteValue->attributes()->label ?? '';
                $group[] = compact('type', 'label', 'content');
            }
            $results[] = $group;
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
                $extent = $this->getSimpleXML()->xpath(
                    'lido/descriptiveMetadata/objectIdentificationWrap/'
                    . 'objectMeasurementsWrap/objectMeasurementsSet/'
                    . 'objectMeasurements/extentMeasurements'
                );
                if ($extent) {
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
            $blacklisted = $this->urlBlacklisted(
                $url['url'] ?? '',
                $url['desc'] ?? ''
            );
            if (!$blacklisted) {
                $urls[] = $url;
            }
        }
        $urls = $this->checkForAudioUrls($urls);
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
        return $url[0] ?? false;
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
     * Is social media sharing allowed
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
        $key = "{$event}_daterange";
        if (!isset($this->fields[$key])) {
            return null;
        }
        if (preg_match('/\[(\d{4}).* TO (\d{4})/', $this->fields[$key], $matches)) {
            return [$matches[1], $matches[2] == '9999' ? null : $matches[2]];
        }
        return null;
    }

    /**
     * Get a language-specific item from an element array
     *
     * @param SimpleXMLElement $element  Element to use
     * @param string           $language Language to look for
     *
     * @return SimpleXMLElement
     */
    protected function getLanguageSpecificItem($element, $language)
    {
        $languages = [];
        if ($language) {
            $languages[] = $language;
            if (strlen($language) > 2) {
                $languages[] = substr($language, 0, 2);
            }
        }
        $result = null;
        foreach ($languages as $lng) {
            foreach ($element as $item) {
                $attrs = $item->attributes();
                if (!empty($attrs->lang) && (string)$attrs->lang == $lng) {
                    $result = (string)$item;
                    break 2;
                }
            }
        }
        if (null === $result) {
            $result = $element;
        }
        return $result;
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
     * Get the photographer information if availabe
     *
     * @return string Photographer's name and / or time when picture taken.
     */
    public function getPhotoInfo()
    {
        $time = $photographer = '';
        foreach ($this->getSimpleXML()->xpath(
            'lido/administrativeMetadata/resourceWrap/resourceSet'
        ) as $nodes) {
            $resourceTerm = (string)$nodes->resourceType->term;
            if (strpos($resourceTerm, 'alokuva')) {
                $photographer = !empty($nodes->resourceDescription)
                 ? (string)$nodes->resourceDescription : '';
                $time = !empty($nodes->resourceDateTaken->displayDate)
                 ? (string)$nodes->resourceDateTaken->displayDate : '';
            }
        }
        return !empty($time) ?
        $photographer . ' ' . $time : $photographer;
    }

    /**
     * Get the displaysubject and description info to summary
     *
     * @return array $results with summary from displaySubject or description field
     */
    public function getSummary()
    {
        $results = [];
        $label = null;
        $title = str_replace([',', ';'], ' ', $this->getTitle());
        foreach ($this->getSimpleXML()->xpath(
            'lido/descriptiveMetadata/objectRelationWrap/subjectWrap/subjectSet'
        ) as $node) {
            $subject = $node->displaySubject;
            $checkTitle = str_replace([',', ';'], ' ', (string)$subject) != $title;
            foreach ($subject as $attributes) {
                $label = $attributes->attributes()->label;
                if (($label == 'aihe' || $label == null) && $checkTitle) {
                    $results[] = (string)$subject;
                }
            }
        }
        if (!$results && !empty($this->fields['description'])) {
            $results[] = (string)($this->fields['description']) != $title
                ? (string)$this->fields['description'] : '';
        }
        return $results;
    }

    /**
     * Return an XML representation of the record using the specified format.
     * Return false if the format is unsupported.
     *
     * @param string     $format     Name of format to use (corresponds with OAI-PMH
     * metadataPrefix parameter).
     * @param string     $baseUrl    Base URL of host containing VuFind (optional;
     * may be used to inject record URLs into XML when appropriate).
     * @param RecordLink $recordLink Record link helper (optional; may be used to
     * inject record URLs into XML when appropriate).
     *
     * @return mixed         XML, or false if format unsupported.
     */
    public function getXML($format, $baseUrl = null, $recordLink = null)
    {
        if ('oai_lido' === $format) {
            return $this->fields['fullrecord'];
        }
        return parent::getXML($format, $baseUrl, $recordLink);
    }
}
