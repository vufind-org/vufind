<?php

/**
 * Model for AIPA records in Solr.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2025.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace Finna\RecordDriver;

use Finna\RecordDriver\Feature\ContainerFormatInterface;
use Finna\RecordDriver\Feature\ContainerFormatTrait;
use Finna\RecordDriver\Feature\LrmiDriverTrait;

use function in_array;

/**
 * Model for AIPA records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class SolrAipa extends SolrQdc implements ContainerFormatInterface
{
    use ContainerFormatTrait {
        getEncapsulatedRecordFormat as getBaseEncapsulatedRecordFormat;
    }
    use LrmiDriverTrait;

    public const AIPA_TYPE_EDUCATION = 'aipa-education';
    public const AIPA_TYPE_RESEARCH = 'aipa-research';

    /**
     * Encapsulated content type records.
     *
     * @var array
     */
    protected array $encapsulatedContentTypeRecords;

    /**
     * Array of excluded descriptions
     *
     * @var array
     */
    protected $excludedDescriptions = [];

    /**
     * Return an array of image URLs associated with this record with keys:
     * - url         Image URL
     * - description Description text
     * - rights      Rights
     *   - copyright   Copyright (e.g. 'CC BY 4.0') (optional)
     *   - description Human readable description (array)
     *   - link        Link to copyright info
     *
     * @param string $language   Language for copyright information
     * @param bool   $includePdf Whether to include first PDF file when no image
     * links are found
     *
     * @return mixed
     */
    public function getAllImages($language = 'fi', $includePdf = false)
    {
        $cacheKey = __FUNCTION__ . "/$language" . ($includePdf ? '/1' : '/0');
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $xml = $this->getXmlRecord();
        $uniqueId = $this->getUniqueID();
        $result = [];
        $images = ['image/png', 'image/jpeg'];
        foreach ($xml->description as $desc) {
            $attr = $desc->attributes();
            $format = trim((string)($attr['format'] ?? ''));
            if ($format && in_array($format, $images)) {
                $url = (string)$desc;
                if ($this->isUrlLoadable($url, $uniqueId)) {
                    $result[] = [
                        'urls' => [
                            'small' => $url,
                            'medium' => $url,
                            'large' => $url,
                        ],
                        'description' => '',
                        'rights' => [],
                        'downloadable' => false,
                    ];
                }
            }
        }

        return $this->cache[$cacheKey] = $result;
    }

    /**
     * Get all subject headings associated with this record. Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific.
     *
     * @param bool $extended Whether to return a keyed array containing data returned
     * by SolrAipa::getFieldData()
     *
     * @return array
     */
    public function getAllSubjectHeadings($extended = false)
    {
        return array_map(
            fn ($value) => (array)$value,
            $this->getFieldData('subject', $extended)
        );
    }

    /**
     * Get all subject headings associated with this record with extended data.
     * (see getAllSubjectHeadings).
     *
     * @return array
     */
    public function getAllSubjectHeadingsExtended()
    {
        return $this->getAllSubjectHeadings(true);
    }

    /**
     * Get subject dates.
     *
     * @return array Keyed array containing data returned by SolrAipa::getFieldData()
     */
    public function getSubjectDates(): array
    {
        return $this->getFieldData('coverage', true, 'subject', 'temporal');
    }

    /**
     * Get subject places.
     *
     * @param bool $extended Whether to return a keyed array containing data returned
     * by SolrAipa::getFieldData()
     *
     * @return array
     */
    public function getSubjectPlaces(bool $extended = false)
    {
        return $this->getFieldData('coverage', true, 'place', 'spatial');
    }

    /**
     * Get extended subject places
     *
     * @return array
     */
    public function getSubjectPlacesExtended(): array
    {
        return $this->getSubjectPlaces(true);
    }

    /**
     * Get related events.
     *
     * @param bool $extended Whether to return a keyed array containing data returned
     * by SolrAipa::getFieldData()
     *
     * @return array
     */
    public function getRelatedEvents(bool $extended = false)
    {
        return $this->getFieldData('relatedEvent', $extended);
    }

    /**
     * Get extended related events.
     *
     * @return array
     */
    public function getRelatedEventsExtended(): array
    {
        return $this->getRelatedEvents(true);
    }

    /**
     * Helper method for getting field data.
     *
     * @param string  $xmlElementName XML element name to select
     * @param bool    $extended       Whether to return a keyed array with the following
     * keys:
     * - heading: the actual subject heading chunks
     * - type: heading type
     * - detail: addition details
     * - source: source vocabulary
     * - id: authority id (if defined)
     * - ids: multiple authority ids (if defined)
     * - authType: authority type (if id is defined)
     * @param string  $headingType    Heading type for extended data
     * @param ?string $requiredType   Required type for selected elements
     *
     * @return array
     */
    protected function getFieldData(
        string $xmlElementName,
        bool $extended = false,
        string $headingType = 'subject',
        ?string $requiredType = null
    ) {
        $lang = $this->getLocale();
        $lang = $lang === 'en-gb' ? 'en' : $lang;
        $xml = $this->getXmlRecord();
        $elements = [];
        foreach ($xml->{$xmlElementName} as $xmlElement) {
            if ($requiredType) {
                $type = $xmlElement->attributes()->{'type'} ?? null;
                if (!$type || $requiredType !== (string)$type) {
                    continue;
                }
            }
            $elementLang = $xmlElement->attributes()->{'lang'} ?? null;
            if ($elementLang && $lang !== (string)$elementLang) {
                continue;
            }
            $element = (string)$xmlElement;
            if ($extended) {
                $element = [
                    'heading' => [$element],
                    'type' => $headingType,
                    'detail' => '',
                    'authType' => '',
                ];
                if ($source = $xmlElement->attributes()->{'source'} ?? '') {
                    $element['source'] = (string)$source;
                }
                if ($id = $xmlElement->attributes()->{'identifier'} ?? null) {
                    $element['id'] = (string)$id;
                    $element['ids'][] = $element['id'];
                }
            }
            $elements[] = $element;
        }
        return $elements;
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
        $xml = $this->getXmlRecord();
        $rights = [];
        if (!empty($xml->rights)) {
            $rights['copyright'] = $this->getMappedRights((string)$xml->rights);
            if ($link = $this->getRightsLink($rights['copyright'], $language)) {
                $rights['link'] = $link;
            }
            return $rights;
        }
        return false;
    }

    /**
     * Get general notes on the record.
     *
     * @return array
     */
    public function getGeneralNotes()
    {
        return [];
    }

    /**
     * Return record type.
     *
     * @return string
     */
    public function getType(): string
    {
        return (string)($this->getXmlRecord()->type ?? '');
    }

    /**
     * Get topics
     *
     * @param string $type defaults to /onto/yso/
     *
     * @return array
     */
    public function getTopics(string $type = '/onto/yso/'): array
    {
        return $this->getAllSubjectHeadings();
    }

    /**
     * Return provenance.
     *
     * @return string
     */
    public function getProvenance(): string
    {
        return (string)($this->getXmlRecord()->provenance ?? '');
    }

    /**
     * Return additional information.
     *
     * @return string
     */
    public function getAdditionalInformation(): string
    {
        return (string)($this->getXmlRecord()->additionalInformation ?? '');
    }

    /**
     * Return encapsulated content type records.
     *
     * @return array Array of encapsulated content type records keyed by unique ID
     */
    public function getEncapsulatedContentTypeRecords(): array
    {
        if (!isset($this->encapsulatedContentTypeRecords)) {
            $this->encapsulatedContentTypeRecords = [];
            foreach ($this->getEncapsulatedRecords() as $encapsulatedRecord) {
                // Assume type is 'content' if driver does not support the method.
                if ($encapsulatedRecord->tryMethod('getType', [], 'content') === 'content') {
                    $this->encapsulatedContentTypeRecords[$encapsulatedRecord->getUniqueId()]
                        = $encapsulatedRecord;
                }
            }
        }
        return $this->encapsulatedContentTypeRecords;
    }

    /**
     * Returns the tag name of XML elements containing an encapsulated record.
     *
     * @return string
     */
    protected function getEncapsulatedRecordElementTagName(): string
    {
        return match ($this->getType()) {
            'aipa:education' => 'item', // For BC, to be removed later.
            self::AIPA_TYPE_EDUCATION => 'item',
            default => 'curatedRecords',
        };
    }

    /**
     * Return format for an encapsulated record.
     *
     * @param mixed $item Encapsulated record item
     *
     * @return string
     */
    protected function getEncapsulatedRecordFormat($item): string
    {
        return match ($this->getType()) {
            'aipa:education' => $this->getBaseEncapsulatedRecordFormat($item), // For BC, to be removed later.
            self::AIPA_TYPE_EDUCATION => $this->getBaseEncapsulatedRecordFormat($item),
            default => 'CuratedRecordList',
        };
    }

    /**
     * Return full record as a filtered SimpleXMLElement for public APIs.
     *
     * @return \SimpleXMLElement
     */
    public function getFilteredXMLElement(): \SimpleXMLElement
    {
        $record = parent::getFilteredXMLElement();
        $filterFields = ['abstract', 'description'];
        foreach ($filterFields as $filterField) {
            while ($record->{$filterField}) {
                unset($record->{$filterField}[0]);
            }
        }
        return $this->filterEncapsulatedRecords($record);
    }

    /**
     * Return record driver instance for an encapsulated LRMI record.
     *
     * @param \SimpleXMLElement $item AIPA item XML
     *
     * @return AipaLrmi
     *
     * @see ContainerFormatTrait::getEncapsulatedRecordDriver()
     */
    protected function getLrmiDriver(\SimpleXMLElement $item): AipaLrmi
    {
        /* @var AipaLrmi $driver */
        $driver = $this->recordDriverManager->get('AipaLrmi');

        $driver->setContainerRecord($this);

        $data = [
            'id' => $this->getUniqueID()
                . ContainerFormatInterface::ENCAPSULATED_RECORD_ID_SEPARATOR
                . (string)$item->id,
            'title' => (string)$item->title,
            'fullrecord' => $item->asXML(),
            'position' => (int)$item->position,
            'record_format' => 'lrmi',
            'datasource_str_mv' => $this->getDataSource(),
        ];

        // Facets
        foreach ($item->educationalAudience as $audience) {
            $data['educational_audience_str_mv'][]
                = (string)$audience->educationalRole;
        }
        $data['educational_level_str_mv'] = array_map(
            'strval',
            (array)($item->learningResource->educationalLevel ?? [])
        );
        $data['educational_aim_str_mv'] = array_map(
            'strval',
            (array)($item->learningResource->teaches ?? [])
        );
        foreach ($item->learningResource->educationalAlignment ?? [] as $alignment) {
            if ($subject = $alignment->educationalSubject ?? null) {
                $data['educational_subject_str_mv'][] = (string)$subject;
            }
        }

        foreach ($item->type as $type) {
            $data['educational_material_type_str_mv'][] = (string)$type;
        }

        $driver->setRawData($data);

        return $driver;
    }
}
