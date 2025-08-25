<?php

/**
 * DefaultRecord RecordDataFormatter specs.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  RecordDataFormatter
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */

namespace Finna\RecordDataFormatter\Specs;

use VuFind\RecordDriver\AbstractBase;
use VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder;

use function in_array;
use function is_array;

/**
 * DefaultRecord RecordDataFormatter specs.
 *
 * @category VuFind
 * @package  RecordDataFormatter
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */
class DefaultRecord extends \VuFind\RecordDataFormatter\Specs\DefaultRecord
{
    /**
     * Record fields with single template lines
     *
     * @var array
     */
    protected array $singleTemplateLines = [];

    /**
     * Record fields with multiple template lines
     *
     * @var array
     */
    protected array $multiTemplateLines = [];

    /**
     * Order of record fields in record page
     *
     * @var array
     */
    protected array $recordFieldOrder = [
        'Contains collections',
        'Genre',
        'Age Limit',
        'Original Work',
        'Published in',
        'New Title',
        'Previous Title',
        'Secondary Authors',
        'Actors',
        'Item Description FWD',
        'Description FWD',
        'Identifiers',
        'Press Reviews',
        'Music',
        'Projected Publication Date',
        'Dissertation Note',
        'Other Links',
        'Presenters',
        'Presenters Marc',
        'Other Titles',
        'Format',
        'Parent Archive',
        'Parent Collection',
        'Parent Subcollection',
        'Parent Series',
        'Parent Work',
        'Parent Unclassified Entity',
        'Archive Origination',
        'Archive',
        'Archive Series',
        'Archive File',
        'Physical Medium',
        'Physical Description',
        'Extent',
        'Language',
        'original_work_language',
        'Language of Abstract',
        'Item Notes',
        'Local Note',
        'Organisation',
        'Collection',
        'Content Description',
        'Item History',
        'Inventory ID',
        'Other ID',
        'Measurements',
        'Inscriptions',
        'Other Classification',
        'Events',
        'Unit ID',
        'Unit IDs',
        'Authors',
        'Publisher',
        'Published',
        'Edition',
        'Series',
        'Country of Producing Entity',
        'Classification',
        'Dewey Classification',
        'lido_editions',
        'Subject Detail',
        'Subject Place',
        'Subject Date',
        'Subject Actor',
        'Subjects',
        'SubjectsWithoutPlaces',
        'subjects_extended',
        'Methodology',
        'Publications',
        'Other Classifications',
        'Introduction',
        'Manufacturer',
        'Production',
        'Production Costs',
        'Funding',
        'Distribution',
        'Premiere Night',
        'Premiere Theaters',
        'Broadcasting Dates',
        'Number of Viewers',
        'Film Festivals',
        'Foreign Distribution',
        'Film Copies',
        'Other Screenings',
        'Movie Thanks',
        'Exterior Images',
        'Interior Images',
        'Studios',
        'Filming Location Notes',
        'Filming Date',
        'Archive Films',
        'Additional Information',
        'Additional Information Extended',
        'child_records',
        'Record Links',
        'Related Materials',
        'Online Access',
        'Source Collection',
        'Publish date',
        'Keywords',
        'Education Programs',
        'Educational Role',
        'Educational Use',
        'Educational Level',
        'Educational Subject',
        'Learning Resource Type',
        'Objective and Content',
        'Accessibility Feature',
        'Accessibility Hazard',
        'Publication Frequency',
        'Playing Time',
        'Color',
        'Sound',
        'Aspect Ratio',
        'Hardware',
        'System Format',
        'Audience',
        'Awards',
        'Production Credits',
        'Bibliography',
        'ISBN',
        'ISSN',
        'DOI',
        'Related Items',
        'Access Restrictions',
        'Access',
        'Terms of Use',
        'Security Classification',
        'Finding Aid',
        'Finding Aid Extended',
        'Publication_Place',
        'Author Notes',
        'Location',
        'Location LIDO',
        'Date',
        'Dates',
        'Material Condition',
        'Contained In',
        'Access Restrictions Extended',
        'Source of Acquisition',
        'Medium of Performance',
        'Music Compositions Extended',
        'Notated Music Format',
        'Event Notice',
        'Capture Information',
        'First Lyrics',
        'Trade Availability Note',
        'Inspection Details',
        'Scale',
        'Available Online',
        'Notes',
        'Original Version Notes',
        'Place of Origin',
        'Related Places',
        'Time Period of Creation',
        'Uniform Title',
        'Standard Codes',
        'Standard Report Number',
        'Study Program Information Notes',
        'Publisher or Distributor Number',
        'Time Period',
        'Copyright Notes',
        'Language Notes',
        'Uncontrolled Title',
        'archive_authors',
        'archive_other_authors',
        'Archive Relations',
        'Appraisal',
        'Container Information',
        'Material Arrangement',
        'Other Related Material',
        'Audience Characteristics',
        'Creator Characteristics',
        'Citations',
        'Related Events',
        'Provenance',
        'Additional Information AIPA',
    ];

    /**
     * Initialize specs.
     *
     * @return void
     */
    protected function init(): void
    {
        $this->initTemplateLines();
        parent::init();

        $this->setDefaults(
            'authority',
            [$this, 'getDefaultAuthoritySpecs']
        );
    }

    /**
     * Function to initialize template lines arrays. Used to avoid using static functions.
     *
     * @return void
     */
    protected function initTemplateLines(): void
    {
        $this->singleTemplateLines = [
            'Access' => [
                'getAccessRestrictions',
                'data-accrest.phtml',
                [
                    'context' => [
                        'class' => 'extendedAccess',
                    ],
                ],
            ],
            'Access Restrictions' => [
                'getAccessRestrictions',
                'data-accrest.phtml',
                [
                    'context' => [
                        'class' => 'extendedAccess',
                    ],
                ],
            ],
            'Accessibility Feature' => [
                'getAccessibilityFeatures',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'record-accessibility-features',
                    ],
                ],
            ],
            'Accessibility Hazard' => [
                'getAccessibilityHazards',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'record-accessibility-hazard',
                    ],
                ],
            ],
            'Actors' => [
                'getPresenters',
                'data-actors.phtml',
                [
                    'context' => [
                        'class' => 'recordPresenters',
                    ],
                ],
            ],
            'Additional Information' => [
                'getTitleStatement',
                'data-addInfo.phtml',
                [
                    'context' => [
                        'class' => 'recordTitleStatement',
                    ],
                ],
            ],
            'Additional Information AIPA' => [
                'getAdditionalInformation',
                'data-additionalInformation.phtml',
                [
                    'context' => [
                        'class' => 'recordAdditionalInformation',
                        'title' => 'AdditionalInformation',
                    ],
                ],
            ],
            'Additional Information Extended' => [
                'getTitleStatementsExtended',
                'data-addInfoExtended.phtml',
                [
                    'context' => [
                        'class' => 'recordTitleStatement',
                        'title' => 'AdditionalInformation',
                    ],
                ],
            ],
            'Age Limit' => [
                'getAgeLimit',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordAgeLimit',
                    ],
                ],
            ],
            'Appraisal' => [
                'getAppraisal',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordAppraisal',
                    ],
                ],
            ],
            'Archive' => [
                'getParentArchives',
                'data-hierarchyLinks.phtml',
                [
                    'context' => [
                        'class' => 'recordHierarchyLinks',
                    ],
                    'labelFunction' => fn (array $data, AbstractBase $driver) =>
                        $driver->tryMethod('getArchiveType') === 'collection'
                        ? 'Parent Collection'
                        : 'Parent Archive',
                ],
            ],
            'Archive File' => [
                'getParentFiles',
                'data-hierarchyLinks.phtml',
                [
                    'context' => [
                        'class' => 'recordFile',
                        'levels' => \Finna\RecordDriver\SolrEad::FILE_LEVELS,
                    ],
                ],
            ],
            'Archive Films' => [
                'getArchiveFilms',
                'data-forwardFields.phtml',
                [
                    'context' => [
                        'class' => 'record-archive-films',
                    ],
                ],
            ],
            'Archive Origination' => [
                'getOriginationExtended',
                'data-origination.phtml',
                [
                    'context' => [
                        'class' => 'record-origination',
                    ],
                ],
            ],
            'Archive Series' => [
                'getParentSeries',
                'data-hierarchyLinks.phtml',
                [
                    'context' => [
                        'class' => 'recordSeries',
                    ],
                ],
            ],
            'archive_authors' => [
                'getAuthorsWithoutRoleHeadings',
                'data-authors.phtml',
                [
                    'context' => [
                        'title' => '',
                        'class' => 'recordAuthors',
                    ],
                ],
            ],
            'archive_other_authors' => [
                'getOtherAuthors',
                'data-authors.phtml',
                [
                    'context' => [
                        'class' => 'recordAuthors',
                    ],
                ],
            ],
            'Aspect Ratio' => [
                'getAspectRatio',
                'data-escapeHtml',
                [
                    'context' => [
                        'class' => 'record-aspect-ratio',
                    ],
                ],
            ],
            'Audience' => [
                'getTargetAudienceNotes',
                'data-escapeHtml',
                [
                    'context' => [
                        'class' => 'extendedAudience',
                    ],
                ],
            ],
            'Audience Characteristics' => [
                'getAudienceCharacteristics',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'audience-characteristics',
                    ],
                ],
            ],
            'Author Notes' => [
                true,
                'data-authorNotes.phtml',
                [
                    'context' => [
                        'class' => 'extendedAuthorNotes',
                    ],
                ],
            ],
            'Authors' => [
                'getNonPresenterAuthors',
                'data-authors.phtml',
                [
                    'context' => [
                        'class' => 'recordAuthors',
                    ],
                ],
            ],
            'Available Online' => [
                'getWebResources',
                'data-detailed-urls.phtml',
                [
                    'context' => [
                        'class' => 'record-available-online',
                        'truncateUrl' => true,
                    ],
                ],
            ],
            'Awards' => [
                'getAwards',
                'data-forwardFields.phtml',
                [
                    'context' => [
                        'class' => 'extendedAwards',
                    ],
                ],
            ],
            'Bibliography' => [
                'getBibliographyNotes',
                'data-transEsc.phtml',
                [
                    'context' => [
                        'class' => 'extendedBibliography',
                    ],
                ],
            ],
            'Broadcasting Dates' => [
                'getBroadcastingInfo',
                'data-broadcasting-dates.phtml',
                [
                    'context' => [
                        'class' => 'record-broadcasting-info',
                    ],
                ],
            ],
            'Capture Information' => [
                'getCaptureInformation',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'record-capture-information',
                    ],
                ],
            ],
            'child_records' => [
                'getChildRecordCount',
                'data-childRecords.phtml',
                [
                    'allowZero' => '',
                    'context' => [
                        'class' => 'recordComponentParts',
                    ],
                ],
            ],
            'Citations' => [
                'getCitations',
                'data-citations.phtml',
                [
                    'context' => [
                        'class' => 'record-citations',
                    ],
                ],
            ],
            'Classification' => [
                'getClassifications',
                'data-classification.phtml',
                [
                    'context' => [
                        'class' => 'recordClassifications',
                    ],
                ],
            ],
            'Collection' => [
                'getCollections',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordCollection',
                    ],
                ],
            ],
            'Color' => [
                'getColor',
                'data-color.phtml',
                [
                    'context' => [
                        'class' => 'record-color',
                    ],
                ],
            ],
            'Contained In' => [
                'getAllRecordLinks',
                'data-containedIn.phtml',
                [
                    'context' => [
                        'class' => 'isPartOf',
                    ],
                ],
            ],
            'Container Information' => [
                'getContainerInformation',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordContainerInformation',
                    ],
                ],
            ],
            'Contains collections' => [
                'getChildCollections',
                'data-childCollections.phtml',
                [
                    'context' => ['class' => 'record-child-collections'],
                ],
            ],
            'Content Description' => [
                'getContentDescription',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordContentDescription',
                    ],
                ],
            ],
            'Copyright Notes' => [
                'getCopyrightNotes',
                'data-transEsc.phtml',
                [
                    'context' => [
                        'class' => 'record-copyright-notes',
                    ],
                ],
            ],
            'Country of Producing Entity' => [
                'getCountry',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'record-country',
                    ],
                ],
            ],
            'Creator Characteristics' => [
                'getCreatorCharacteristics',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'creator-characteristics',
                    ],
                ],
            ],
            'Date' => [
                'getUnitDate',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordDaterange',
                    ],
                ],
            ],
            'Dates' => [
                'getUnitDates',
                'data-lines-with-detail.phtml',
                [
                    'context' => [
                        'title' => 'Date',
                    ],
                ],
            ],
            'Description FWD' => [
                'getDescription',
                'data-forwardFields.phtml',
                [
                    'context' => [
                        'class' => 'recordDescription',
                    ],
                ],
            ],
            'Dewey Classification' => [
                'getDeweyClassifications',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordClassifications',
                    ],
                ],
            ],
            'Dissertation Note' => [
                'getDissertationNote',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'coreDissertationNote',
                    ],
                ],
            ],
            'Distribution' => [
                'getDistributors',
                'data-distribution.phtml',
                [
                    'context' => [
                        'class' => 'record-distributors',
                    ],
                ],
            ],
            'DOI' => [
                'getCleanDOI',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'extended-doi',
                    ],
                ],
            ],
            'Edition' => [
                'getEdition',
                'data-edition.phtml',
                [
                    'context' => [
                        'class' => 'recordEdition',
                    ],
                ],
            ],
            'Education Programs' => [
                'getEducationPrograms',
                'data-education.phtml',
                [
                    'context' => [
                        'class' => 'record-education-programs',
                    ],
                ],
            ],
            'Educational Level' => [
                'getEducationalLevels',
                'data-transEsc.phtml',
                [
                    'context' => [
                        'class' => 'record-educational-levels',
                    ],
                ],
            ],
            'Educational Role' => [
                'getEducationalAudiences',
                'data-transEsc.phtml',
                [
                    'context' => [
                        'class' => 'record-educational-audience',
                    ],
                ],
            ],
            'Educational Subject' => [
                'getEducationalSubjects',
                'data-transEsc.phtml',
                [
                    'context' => [
                        'class' => 'record-educational-subjects',
                    ],
                ],
            ],
            'Educational Use' => [
                'getEducationalUse',
                'data-transEsc.phtml',
                [
                    'context' => [
                        'class' => 'record-educational-uses',
                    ],
                ],
            ],
            'Event Notice' => [
                'getEventNotice',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordEventNotice',
                    ],
                ],
            ],
            'Extent' => [
                'getPhysicalDescriptions',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'record-extent',
                    ],
                ],
            ],
            'Exterior Images' => [
                'getExteriors',
                'data-forwardFields.phtml',
                [
                    'context' => [
                        'class' => 'record-exteriors',
                    ],
                ],
            ],
            'Film Copies' => [
                'getNumberOfCopies',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'record-film-copies',
                    ],
                ],
            ],
            'Film Festivals' => [
                'getFestivalInfo',
                'data-festival-info.phtml',
                [
                    'context' => [
                        'class' => 'record-festival-info',
                    ],
                ],
            ],
            'Filming Date' => [
                'getFilmingDate',
                'data-forwardFields.phtml',
                [
                    'context' => [
                        'class' => 'record-filming-date',
                    ],
                ],
            ],
            'Filming Location Notes' => [
                'getLocationNotes',
                'data-forwardFields.phtml',
                [
                    'context' => [
                        'class' => 'record-location-notes',
                    ],
                ],
            ],
            'Finding Aid' => [
                'getFindingAids',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'extendedFindingAids',
                    ],
                ],
            ],
            'Finding Aid Extended' => [
                'getFindingAidsExtended',
                'data-findingAids.phtml',
                [
                    'context' => [
                        'class' => 'extendedFindingAids',
                        'title' => 'FindingAid',
                    ],
                ],
            ],
            'First Lyrics' => [
                'getFirstLyrics',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordFirstLyrics',
                    ],
                ],
            ],
            'Foreign Distribution' => [
                'getForeignDistribution',
                'data-foreign-distribution.phtml',
                [
                    'context' => [
                        'class' => 'record-foreign-distribution',
                    ],
                ],
            ],
            'Format' => [
                'getFormats',
                'format-list.phtml',
                [
                    'context' => [
                        'class' => 'recordFormat',
                    ],
                ],
            ],
            'Funding' => [
                'getFunders',
                'data-funding.phtml',
                [
                    'context' => [
                        'class' => 'record-funders',
                    ],
                ],
            ],
            'Genre' => [
                'getGenres',
                'data-genres.phtml',
                [
                    'context' => [
                        'class' => 'recordGenres',
                    ],
                ],
            ],
            'Hardware' => [
                'getHardwareRequirements',
                'data-hardwareRequirements.phtml',
                [
                    'context' => [
                        'class' => 'record-hardware',
                    ],
                ],
            ],
            'Identifiers' => [
                'getOtherIdentifiers',
                'data-lines-with-detail.phtml',
                [
                    'context' => [
                        'class' => 'recordIdentifiers',
                    ],
                ],
            ],
            'Inscriptions' => [
                'getInscriptions',
                'data-inscriptions.phtml',
                [
                    'context' => [
                        'class' => 'recordInscriptions',
                    ],
                ],
            ],
            'Inspection Details' => [
                'getInspectionDetails',
                'data-inspection.phtml',
                [
                    'context' => [
                        'class' => 'recordInspection',
                    ],
                ],
            ],
            'Interior Images' => [
                'getInteriors',
                'data-forwardFields.phtml',
                [
                    'context' => [
                        'class' => 'record-interiors',
                    ],
                ],
            ],
            'Introduction' => [
                'getIntroduction',
                'data-markdown.phtml',
                [
                    'context' => [
                        'class' => 'record-introduction',
                    ],
                ],
            ],
            'Inventory ID' => [
                'getIdentifier',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordIdentifier',
                    ],
                ],
            ],
            'ISBN' => [
                'getISBNs',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'extendedISBNs',
                    ],
                ],
            ],
            'ISSN' => [
                'getISSNs',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'extendedISSNs',
                    ],
                ],
            ],
            'Item Description FWD' => [
                'getGeneralNotes',
                'data-forwardFields.phtml',
                [
                    'context' => [
                        'class' => 'recordDescription',
                    ],
                ],
            ],
            'Item History' => [
                'getItemHistory',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordHistory',
                    ],
                ],
            ],
            'Item Notes' => [
                'getGeneralNotes',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordDescription',
                    ],
                ],
            ],
            'Keywords' => [
                'getKeywords',
                'data-keywords.phtml',
                [
                    'context' => [
                        'class' => 'record-keywords',
                    ],
                ],
            ],
            'Language' => [
                'getLanguages',
                'data-transEscLangcode.phtml',
                [
                    'context' => [
                        'class' => 'recordLanguage',
                    ],
                ],
            ],
            'Language Notes' => [
                'getLanguageNotes',
                'data-languageNotes.phtml',
                [
                    'context' => [
                        'class' => 'record-language-notes',
                    ],
                ],
            ],
            'Language of Abstract' => [
                'getAbstractLanguage',
                'data-transEscLangcode.phtml',
                [
                    'context' => [
                        'class' => 'abstract-language',
                    ],
                ],
            ],
            'Learning Resource Type' => [
                'getEducationalMaterialType',
                'data-transEsc.phtml',
                [
                    'context' => [
                        'class' => 'record-educational-material-type',
                    ],
                ],
            ],
            'lido_editions' => [
                'getEditions',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordDisplayEdition',
                    ],
                ],
            ],
            'Local Note' => [
                'getLocalNotes',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'record-local-note',
                    ],
                ],
            ],
            'Location' => [
                'getPhysicalLocations',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordPhysicalLocation',
                    ],
                ],
            ],
            'Location LIDO' => [
                'getPhysicalLocationsExtended',
                'data-locations.phtml',
                [
                    'context' => [
                        'class' => 'recordPhysicalLocation',
                        'title' => 'Location',
                    ],
                ],
            ],
            'Manufacturer' => [
                'getManufacturer',
                'data-transEsc.phtml',
                [
                    'context' => [
                        'class' => 'recordManufacturer',
                    ],
                ],
            ],
            'Material Arrangement' => [
                'getMaterialArrangement',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordMaterialArrangement',
                    ],
                ],
            ],
            'Material Condition' => [
                'getMaterialCondition',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'materialCondition',
                    ],
                ],
            ],
            'Measurements' => [
                'getMeasurements',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordMeasurements',
                    ],
                ],
            ],
            'Medium of Performance' => [
                'getMusicCompositions',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'record-composition',
                    ],
                ],
            ],
            'Methodology' => [
                'getMethodology',
                'data-methodology-links.phtml',
                [
                    'context' => [
                        'class' => 'recordMethodology',
                    ],
                ],
            ],
            'Movie Thanks' => [
                'getMovieThanks',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'record-thanks',
                        'title' => 'movie_thanks',
                    ],
                ],
            ],
            'Music' => [
                'getMusicInfo',
                'data-forwardFields.phtml',
                [
                    'context' => [
                        'class' => 'record-music',
                    ],
                ],
            ],
            'New Title' => [
                'getNewerTitles',
                'data-titles.phtml',
                [
                    'context' => [
                        'class' => 'recordNextTitles',
                    ],
                ],
            ],
            'Notated Music Format' => [
                'getNotatedMusicFormat',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordNoteFormat',
                    ],
                ],
            ],
            'Notes' => [
                'getNotes',
                'data-transEsc.phtml',
                [
                    'context' => [
                        'class' => 'record-notes',
                    ],
                ],
            ],
            'Number of Viewers' => [
                'getAmountOfViewers',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'record-number-of-viewers',
                    ],
                ],
            ],
            'Objective and Content' => [
                'getEducationalAim',
                'data-transEsc.phtml',
                [
                    'context' => [
                        'class' => 'record-educational-aim',
                    ],
                ],
            ],
            'Online Access' => [
                true,
                'data-onlineAccess.phtml',
                [
                    'context' => [
                        'class' => 'webResource',
                    ],
                ],
            ],
            'Organisation' => [
                'getInstitutions',
                'data-organisation.phtml',
                [
                    'context' => [
                        'class' => 'recordInstitution',
                    ],
                ],
            ],
            'Original Version Notes' => [
                'getOriginalVersionNotes',
                'data-originalVersionNotes.phtml',
                [
                    'context' => [
                        'class' => 'record-original-version-notes',
                    ],
                ],
            ],
            'Original Work' => [
                'getOriginalWork',
                'data-forwardFields.phtml',
                [
                    'context' => [
                        'class' => 'recordOriginalWork',
                    ],
                ],
            ],
            'original_work_language' => [
                'getOriginalLanguages',
                'data-transEscLangcode.phtml',
                [
                    'context' => [
                        'class' => 'originalLanguage',
                    ],
                ],
            ],
            'Other Classification' => [
                'getFormatClassifications',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordClassifications',
                    ],
                ],
            ],
            'Other Classifications' => [
                'getOtherClassifications',
                'data-keywords.phtml',
                [
                    'context' => [
                        'class' => 'recordClassifications',
                        'title' => 'Classification',
                    ],
                ],
            ],
            'Other ID' => [
                'getLocalIdentifiers',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordIdentifiers',
                    ],
                ],
            ],
            'Other Links' => [
                'getOtherLinks',
                'data-getOtherLinks.phtml',
                [
                    'labelFunction' => fn (array $data) => !empty($data[0]['heading'])
                        ? $data[0]['heading']
                        : 'Other Related Material',
                    'context' => [
                        'class' => 'recordOtherLink',
                    ],
                ],
            ],
            'Other Related Material' => [
                'getOtherRelatedMaterial',
                'data-otherRelatedMaterial.phtml',
                [
                    'context' => [
                        'class' => 'other-related-material',
                    ],
                ],
            ],
            'Other Screenings' => [
                'getOtherScreenings',
                'data-other-screenings.phtml',
                [
                    'context' => [
                        'class' => 'record-other-screenings',
                    ],
                ],
            ],
            'Other Titles' => [
                'getAlternativeTitles',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordAltTitles',
                    ],
                ],
            ],
            'Parent Archive' => [
                'getParentArchives',
                'data-hierarchyLinks.phtml',
                [
                    'context' => [
                        'class' => 'recordHierarchyLinks',
                    ],
                ],
            ],
            'Parent Collection' => [
                'getParentCollections',
                'data-hierarchyLinks.phtml',
                [
                    'context' => [
                        'class' => 'recordHierarchyLinks',
                    ],
                ],
            ],
            'Parent Series' => [
                'getParentSeries',
                'data-hierarchyLinks.phtml',
                [
                    'context' => [
                        'class' => 'recordHierarchyLinks',
                    ],
                ],
            ],
            'Parent Subcollection' => [
                'getParentSubcollections',
                'data-hierarchyLinks.phtml',
                [
                    'context' => [
                        'class' => 'recordHierarchyLinks',
                    ],
                ],
            ],
            'Parent Unclassified Entity' => [
                'getParentUnclassifiedEntities',
                'data-hierarchyLinks.phtml',
                [
                    'context' => [
                        'class' => 'recordHierarchyLinks',
                    ],
                ],
            ],
            'Parent Work' => [
                'getParentWorks',
                'data-hierarchyLinks.phtml',
                [
                    'context' => [
                        'class' => 'recordHierarchyLinks',
                    ],
                ],
            ],
            'Physical Description' => [
                'getPhysicalDescriptions',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'physicalDescriptions',
                    ],
                ],
            ],
            'Physical Medium' => [
                'getPhysicalMediums',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'physical-medium',
                    ],
                ],
            ],
            'Place of Origin' => [
                'getAssociatedPlace',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'record-associated-place',
                    ],
                ],
            ],
            'Playing Time' => [
                'getPlayingTimes',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'extendedPlayTime',
                    ],
                ],
            ],
            'Premiere Night' => [
                'getPremiereTime',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'record-premiere-night',
                    ],
                ],
            ],
            'Premiere Theaters' => [
                'getPremiereTheaters',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'record-premiere-theaters',
                    ],
                ],
            ],
            'Presenters' => [
                'getPresenters',
                'data-presenters.phtml',
                [
                    'context' => [
                        'class' => 'recordPresenters',
                    ],
                ],
            ],
            'Presenters Marc' => [
                'getSecondaryPresenters',
                'data-presenters.phtml',
                [
                    'context' => [
                        'class' => 'recordPresenters',
                        'title' => 'Presenters',
                    ],
                ],
            ],
            'Press Reviews' => [
                'getPressReview',
                'data-forwardFields.phtml',
                [
                    'context' => [
                        'class' => 'record-press-review',
                    ],
                ],
            ],
            'Previous Title' => [
                'getPreviousTitles',
                'data-titles.phtml',
                [
                    'context' => [
                        'class' => 'recordPrevTitles',
                    ],
                ],
            ],
            'Production' => [
                'getProducers',
                'data-producers.phtml',
                [
                    'context' => [
                        'class' => 'record-production',
                    ],
                ],
            ],
            'Production Costs' => [
                'getProductionCost',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'record-production-cost',
                    ],
                ],
            ],
            'Production Credits' => [
                'getProductionCredits',
                'data-escapeHtml',
                [
                    'context' => [
                        'class' => 'extendedCredits',
                    ],
                ],
            ],
            'Projected Publication Date' => [
                'getProjectedPublicationDate',
                'data-transEsc.phtml',
                [
                    'context' => [
                        'class' => 'coreProjectedPublicationDate',
                    ],
                ],
            ],
            'Provenance' => [
                'getProvenance',
                'data-provenance.phtml',
                [
                    'context' => [
                        'class' => 'recordProvenance',
                    ],
                ],
            ],
            'Publication Frequency' => [
                'getPublicationFrequency',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'extendedFrequency',
                    ],
                ],
            ],
            'Publication_Place' => [
                'getHierarchicalPlaceNames',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'publicationPlace',
                    ],
                ],
            ],
            'Publications' => [
                'getRelatedPublications',
                'data-relatedPublications.phtml',
                [
                    'context' => [
                        'class' => 'record-related-publications',
                    ],
                ],
            ],
            'Publish date' => [
                'getDateSpan',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'extendedDateSpan',
                    ],
                ],
            ],
            'Published' => [
                'getPublicationDetails',
                'data-publicationDetails.phtml',
                [
                    'context' => [
                        'class' => 'recordPublications',
                    ],
                ],
            ],
            'Published in' => [
                'getContainerTitle',
                'data-containerTitle.phtml',
                [
                    'context' => [
                        'class' => 'record-container-link',
                    ],
                ],
            ],
            'Publisher' => [
                'getPublicationDetails',
                'data-publicationDetails.phtml',
                [
                    'context' => [
                        'class' => 'recordPublications',
                    ],
                ],
            ],
            'Publisher or Distributor Number' => [
                'getPubDistNumber',
                'data-transEsc.phtml',
                [
                    'context' => [
                        'class' => 'record-pubdist-number',
                    ],
                ],
            ],
            'Record Links' => [
                'getAllRecordLinks',
                'data-allRecordLinks.phtml',
                [
                    'context' => [
                        'class' => 'recordLinks',
                        'title' => '',
                    ],
                ],
            ],
            'Related Events' => [
                'getRelatedEventsExtended',
                'data-allSubjectHeadingsExtended.phtml',
                [
                    'context' => [
                        'class' => 'recordRelatedEvents',
                    ],
                ],
            ],
            'Related Items' => [
                'getRelationshipNotes',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'extendedRelatedItems',
                    ],
                ],
            ],
            'Related Materials' => [
                'getAllRecordLinks',
                'data-allRecordLinks.phtml',
                [
                    'context' => [
                        'class' => 'relatedMaterials',
                    ],
                ],
            ],
            'Related Places' => [
                'getRelatedPlacesExtended',
                'data-lines-with-detail.phtml',
                [
                    'context' => [
                        'class' => 'record-related-place',
                    ],
                ],
            ],
            'Scale' => [
                'getMapScale',
                'data-transEsc.phtml',
                [
                    'context' => [
                        'class' => 'record-map-scale',
                    ],
                ],
            ],
            'Secondary Authors' => [
                'getNonPresenterSecondaryAuthors',
                'data-contributors.phtml',
                [
                    'context' => [
                        'class' => 'recordAuthors',
                    ],
                    'labelFunction' => fn () => 'Contributors',
                ],
            ],
            'Security Classification' => [
                'getSecurityClassification',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'security-classification',
                    ],
                ],
            ],
            'Series' => [
                'getSeries',
                'data-series.phtml',
                [
                    'context' => [
                        'class' => 'recordSeries',
                    ],
                ],
            ],
            'Sound' => [
                'getSound',
                'data-sound.phtml',
                [
                    'context' => [
                        'class' => 'record-sound',
                    ],
                ],
            ],
            'Source Collection' => [
                'getSource',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordSource',
                    ],
                ],
            ],
            'Source of Acquisition' => [
                'getAcquisitionSource',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordAcquisition',
                    ],
                ],
            ],
            'Standard Codes' => [
                'getStandardCodes',
                'data-transEsc.phtml',
                [
                    'context' => [
                        'class' => 'record-standard-codes',
                    ],
                ],
            ],
            'Standard Report Number' => [
                'getStandardReportNumbers',
                'data-transEsc.phtml',
                [
                    'context' => [
                        'class' => 'record-standard-report-number',
                    ],
                ],
            ],
            'Studios' => [
                'getStudios',
                'data-forwardFields.phtml',
                [
                    'context' => [
                        'class' => 'record-studios',
                    ],
                ],
            ],
            'Study Program Information Notes' => [
                'getStudyProgramNotes',
                'data-transEsc.phtml',
                [
                    'context' => [
                        'class' => 'record-study-program-notes',
                    ],
                ],
            ],
            'Subject Actor' => [
                'getSubjectActors',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordSubjects',
                    ],
                ],
            ],
            'Subject Date' => [
                'getSubjectDates',
                'data-subjectDate.phtml',
                [
                    'context' => [
                        'class' => 'recordSubjects',
                    ],
                ],
            ],
            'Subject Detail' => [
                'getSubjectDetails',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordSubjects',
                    ],
                ],
            ],
            'Subject Place' => [
                'getSubjectPlacesExtended',
                'data-allSubjectHeadingsExtended.phtml',
                [
                    'context' => [
                        'class' => 'recordSubjects',
                        'headingType' => 'place',
                    ],
                ],
            ],
            'Subjects' => [
                'getAllSubjectHeadings',
                'data-allSubjectHeadings.phtml',
                [
                    'context' => [
                        'class' => 'recordSubjects',
                    ],
                ],
            ],
            'subjects_extended' => [
                'getAllSubjectHeadingsExtended',
                'data-allSubjectHeadingsExtended.phtml',
                [
                    'context' => [
                        'class' => 'recordSubjects',
                    ],
                ],
            ],
            'SubjectsWithoutPlaces' => [
                'getAllSubjectHeadingsWithoutPlacesExtended',
                'data-allSubjectHeadingsExtended.phtml',
                [
                    'context' => [
                        'class' => 'recordSubjects',
                        'title' => 'Subjects',
                    ],
                ],
            ],
            'System Format' => [
                'getSystemDetails',
                'data-systemFormat.phtml',
                [
                    'context' => [
                        'class' => 'extendedSystem',
                    ],
                ],
            ],
            'Terms of Use' => [
                'getTermsOfUse',
                'data-termsOfUse.phtml',
                [
                    'context' => [
                        'class' => 'extendedTermsOfUse',
                    ],
                ],
            ],
            'Time Period' => [
                'getTimePeriod',
                'data-transEsc.phtml',
                [
                    'context' => [
                        'class' => 'record-time-period',
                    ],
                ],
            ],
            'Time Period of Creation' => [
                'getTimePeriodOfCreation',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'record-time-period-creation',
                    ],
                ],
            ],
            'Trade Availability Note' => [
                'getTradeAvailabilityNote',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordTradeNote',
                    ],
                ],
            ],
            'Uncontrolled Title' => [
                'getUncontrolledTitle',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'record-uncontrolled-title',
                    ],
                ],
            ],
            'Uniform Title' => [
                'getCollectiveUniformTitle',
                'data-transEsc.phtml',
                [
                    'context' => [
                        'class' => 'record-collective-uniform-title',
                    ],
                ],
            ],
            'Unit ID' => [
                'getUnitID',
                'data-escapeHtml.phtml',
                [
                    'context' => [
                        'class' => 'recordReferenceCode',
                    ],
                ],
            ],
            'Unit IDs' => [
                'getUnitIds',
                'data-lines-with-detail.phtml',
            ],
        ];

        $this->multiTemplateLines = [
            'Access Restrictions Extended' => [
                'getExtendedAccessRestrictions',
                [$this, 'getAccessRestrictions'],
            ],
            'Archive Relations' => [
                'getAuthorsWithRoleHeadings',
                [$this, 'getRelations'],
            ],
            'Events' => [
                'getEvents',
                [$this, 'getEvents'],
            ],
            'Music Compositions Extended' => [
                'getExtendedMusicCompositions',
                [$this, 'getExtendedMusicCompositions'],
            ],
        ];
    }

    /**
     * Get default specifications for displaying data in core metadata.
     *
     * @return array
     */
    public function getDefaultCoreSpecs(): array
    {
        $spec = new SpecBuilder();

        foreach ($this->getDefaultCoreFields() as $key => $data) {
            if ($data[0] === true) {
                // Multi-line
                [, $dataMethod, $callback, $options] = $data;
                $spec->setMultiLine($key, $dataMethod, $callback, $options);
            } else {
                [, $dataMethod, $template, $options] = $data;
                $spec->setTemplateLine($key, $dataMethod, $template, $options);
            }
        }
        return $spec->getArray();
    }

    /**
     * Utility function for getting fields in core metadata
     *
     * @return array
     */
    protected function getDefaultCoreFields()
    {
        $pos = 10;
        $lines = [];
        $setTemplateLine
            = function (
                $key,
                $data
            ) use (
                &$lines,
                &$pos
            ) {
                $pos += 100;
                $dataMethod = $data[0];
                $template = $data[1];
                $options = $data[2] ?? [];
                $options['pos'] = $pos;
                $lines[$key] = [false, $dataMethod, $template, $options];
            };

        $setMultiTemplateLine
            = function (
                $key,
                $data
            ) use (
                &$lines,
                &$pos
            ) {
                $pos += 100;
                $dataMethod = $data[0];
                $callback = $data[1];
                $options = $data[2] ?? [];
                $options['pos'] = $pos;
                $lines[$key] = [true, $dataMethod, $callback, $options];
            };
        foreach ($this->recordFieldOrder as $key) {
            if ($template = $this->singleTemplateLines[$key] ?? false) {
                $setTemplateLine($key, $template);
                continue;
            }
            if ($template = $this->multiTemplateLines[$key] ?? false) {
                $setMultiTemplateLine($key, $template);
            }
        }
        return $lines;
    }

    /**
     * Get default specifications for displaying data in the description tab.
     *
     * @return array
     */
    public function getDefaultDescriptionSpecs(): array
    {
        $spec = new SpecBuilder();
        $spec->setLine('Summary', 'getSummary');
        $spec->setLine('Abstract', 'getAbstractNotes');
        $spec->setLine('Review', 'getReviewNotes');
        $spec->setLine('Content Advice', 'getContentAdviceNotes');
        $spec->setLine('Published', 'getDateSpan');
        $spec->setLine('Item Notes', 'getGeneralNotes');
        $spec->setLine('Physical Description', 'getPhysicalDescriptions');
        $spec->setLine('Publication Frequency', 'getPublicationFrequency');
        $spec->setLine('Playing Time', 'getPlayingTimes');
        $spec->setLine('Format', 'getSystemDetails');
        $spec->setLine('Audience', 'getTargetAudienceNotes');
        $spec->setLine('Awards', 'getAwards');
        $spec->setLine('Production Credits', 'getProductionCredits');
        $spec->setLine('Bibliography', 'getBibliographyNotes');
        $spec->setLine('ISBN', 'getISBNs');
        $spec->setLine('ISSN', 'getISSNs');
        $spec->setLine('DOI', 'getCleanDOI');
        $spec->setLine('Related Items', 'getRelationshipNotes');
        $spec->setLine('Access', 'getAccessRestrictions');
        $spec->setLine('Finding Aid', 'getFindingAids');
        $spec->setLine('Publication_Place', 'getHierarchicalPlaceNames');
        $spec->setTemplateLine('Author Notes', true, 'data-authorNotes.phtml');
        return $spec->getArray();
    }

    /**
     * Get default specifications for displaying data in the description tab.
     *
     * @return array
     */
    public function getDefaultAuthoritySpecs()
    {
        $spec = new SpecBuilder();
        $spec->setLine('Date of birth', 'getBirthDate');
        $spec->setLine('Place of birth', 'getBirthPlace');
        $spec->setLine('Date of death', 'getDeathDate');
        $spec->setLine('Place of death', 'getDeathPlace');

        $spec->setLine('Established', 'getEstablishedDate');
        $spec->setLine('Terminated', 'getTerminatedDate');
        $spec->setLine('Awards', 'getAwards');

        $spec->setLine('Occupation', 'getOccupations');
        $spec->setLine('Field of Activity', 'getFieldsOfActivity');
        $spec->setTemplateLine(
            'Other Forms of Name',
            'getAlternativeTitles',
            'data-lines-with-detail.phtml'
        );
        $spec->setLine('Associated Place', 'getAssociatedPlace');
        $spec->setTemplateLine(
            'Related Places',
            'getRelatedPlaces',
            'data-lines-with-detail.phtml'
        );
        $spec->setTemplateLine(
            'Identifiers',
            'getOtherIdentifiers',
            'data-lines-with-detail.phtml'
        );
        $spec->setLine('Historical Information', 'getHistory');
        $spec->setTemplateLine(
            'Publications',
            'getRelatedPublications',
            'data-relatedPublications.phtml'
        );
        $spec->setTemplateLine('Sources', 'getSources', 'data-sources.phtml');
        $spec->setTemplateLine(
            'Related Authorities',
            'getRelations',
            'data-relations-author.phtml'
        );
        $spec->setTemplateLine(
            'Associated Groups',
            'getAssociatedGroups',
            'data-lines-with-detail.phtml'
        );
        $spec->setLine('Additional Information', 'getAdditionalInformation');

        return $spec->getArray();
    }

    /**
     * Multiline constructor function for extended music compositions
     *
     * @param array $data    Field data
     * @param array $options Field options
     *
     * @return array
     */
    public function getExtendedMusicCompositions(array $data, array $options): array
    {
        $final = [];
        $pos = $options['pos'];
        foreach ($data as $field) {
            $label = $field['partial'] ? 'Partial Medium of Performance' : 'Medium of Performance';
            $final[] = [
                'label' => $label,
                'values' => $field['items'],
                'options' => [
                    'pos' => $pos++,
                    'renderType' => 'RecordDriverTemplate',
                    'template' => 'data-music-composition.phtml',
                    'context' => [
                        'class' => 'record-composition',
                    ],
                ],
            ];
        }
        return $final;
    }

    /**
     * Multiline constructor function for archive relations
     *
     * @param array $data    Field data
     * @param array $options Field options
     *
     * @return array
     */
    public function getRelations(array $data, array $options): array
    {
        $relationsByRole = [];
        foreach ($data as $relation) {
            $role = ($relation['role'] ?? '0') ?: '0';
            if (!isset($relationsByRole[$role])) {
                $relationsByRole[$role] = [];
            }
            unset($relation['role']);
            $relationsByRole[$role][] = $relation;
        }
        $final = [];
        $pos = $options['pos'];
        foreach ($relationsByRole as $role => $relations) {
            $final[] = [
                'label' => $role !== '0' ? "CreatorRoles::$role" : null,
                'values' => $relations,'options' => [
                    'pos' => $pos++,
                    'renderType' => 'RecordDriverTemplate',
                    'template' => 'data-authors.phtml',
                    'context' => [
                        'class' => 'recordRelations',
                        'schemaLabel' => null,
                    ],
                ],
            ];
        }
        return $final;
    }

    /**
     * Multiline constructor function for access restrictions
     *
     * @param array $data    Field data
     * @param array $options Field options
     *
     * @return array
     */
    public function getAccessRestrictions(array $data, array $options): array
    {
        $final = [];
        $pos = $options['pos'];
        $useSubHeadings = is_array(array_values($data)[0]);
        foreach ($data as $type => $values) {
            $values = $useSubHeadings && $values ? array_values($values) : $values;
            $label = $useSubHeadings ? "access_restrictions_$type" : null;
            if (
                $useSubHeadings && isset($options['hideSubheadings'])
                && in_array($label, $options['hideSubheadings'])
            ) {
                $label = null;
            }
            $final[] = [
                'label' => $label,
                'values' => $values,
                'options' => [
                    'pos' => $pos++,
                    'renderType' => 'RecordDriverTemplate',
                    'template' => 'data-escapeHtml.phtml',
                    'context' => [
                        'class' => 'extendedAccess',
                        'type' => "access_restrictions_$type",
                        'schemaLabel' => null,
                    ],
                ],
            ];
        }
        return $final;
    }

    /**
     * Multiline constructor function for lido event types field
     *
     * @param array $data    Field data
     * @param array $options Field options
     *
     * @return array
     */
    public function getEvents(array $data, array $options): array
    {
        $final = [];
        $pos = $options['pos'];
        foreach ($data as $eventType => $events) {
            $final[] = [
                'values' => $events,
                'options' => [
                    'pos' => $pos++,
                    'renderType' => 'RecordDriverTemplate',
                    'template' => 'data-mainFormat.phtml',
                    'context' => [
                        'class' => 'recordEvents',
                    ],
                    'labelFunction' => function ($data, $driver) use ($eventType) {
                        if (!$eventType) {
                            return '';
                        }
                        $mainFormat = $driver->getMainFormat();
                        $keys = [
                            "lido_event_type_{$mainFormat}_$eventType",
                            "lido_event_type_$eventType",
                        ];
                        foreach ($keys as $key) {
                            if ($translation = $this->translate($key, default: '')) {
                                return $translation;
                            }
                        }
                        return '';
                    },
                ],
            ];
        }
        return $final;
    }
}
