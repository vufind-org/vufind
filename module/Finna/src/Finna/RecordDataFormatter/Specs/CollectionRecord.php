<?php

/**
 * Collection RecordDataFormatter specs.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  RecordDataFormatter
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace Finna\RecordDataFormatter\Specs;

/**
 * Collection RecordDataFormatter specs.
 *
 * @category VuFind
 * @package  RecordDataFormatter
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */
class CollectionRecord extends \Finna\RecordDataFormatter\Specs\DefaultRecord
{
    /**
     * Order of record fields in record page
     *
     * @var array
     */
    protected array $recordFieldOrder = [
        'Contains collections',
        'Record Links',
        'child_records',
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
}
