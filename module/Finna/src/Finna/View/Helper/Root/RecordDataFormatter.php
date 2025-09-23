<?php

/**
 * Record driver data formatting view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
 * Copyright (C) The National Library of Finland 2017-2023.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma  <juha.luoma@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */

namespace Finna\View\Helper\Root;

use Finna\View\Helper\Root\RecordDataFormatter\FieldGroupBuilder;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

use function in_array;

/**
 * Record driver data formatting view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma  <juha.luoma@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */
class RecordDataFormatter extends \VuFind\View\Helper\Root\RecordDataFormatter
{
    /**
     * Filter unnecessary fields from Marc records.
     *
     * @param array $coreFields data to filter.
     *
     * @return array
     */
    public function filterMarcFields($coreFields)
    {
        $include = [
            'Access',
            'Accessibility Feature',
            'Accessibility Hazard',
            'Additional Information',
            'Age Limit',
            'Audience',
            'Audience Characteristics',
            'Author Notes',
            'Awards',
            'Bibliography',
            'child_records',
            'Capture Information',
            'Classification',
            'Contains collections',
            'Copyright Notes',
            'Country of Producing Entity',
            'Creator Characteristics',
            'DOI',
            'Dewey Classification',
            'Dissertation Note',
            'Education Programs',
            'Event Notice',
            'Finding Aid',
            'First Lyrics',
            'Genre',
            'Hardware',
            'ISBN',
            'ISSN',
            'Inventory ID',
            'Item Notes',
            'Keywords',
            'Language',
            'Language Notes',
            'Language of Abstract',
            'Local Note',
            'Manufacturer',
            'Methodology',
            'Music Compositions Extended',
            'New Title',
            'Notated Music Format',
            'Notes',
            'Original Version Notes',
            'original_work_language',
            'Other Links',
            'Other Titles',
            'Physical Description',
            'Place of Origin',
            'Playing Time',
            'Presenters Marc',
            'Previous Title',
            'Production',
            'Production Credits',
            'Projected Publication Date',
            'Publication Frequency',
            'Publication_Place',
            'Publish date',
            'Publisher',
            'Publisher or Distributor Number',
            'Record Links',
            'Related Items',
            'Related Places',
            'Scale',
            'Security Classification',
            'Series',
            'Source of Acquisition',
            'Standard Codes',
            'Standard Report Number',
            'Study Program Information Notes',
            'subjects_extended',
            'System Format',
            'Terms of Use',
            'Time Period',
            'Time Period of Creation',
            'Trade Availability Note',
            'Uncontrolled Title',
            'Uniform Title',
        ];
        return $this->filterFields($coreFields, $include);
    }

    /**
     * Filter unnecessary fields from Lido records.
     *
     * @param array $coreFields data to filter.
     *
     * @return array
     */
    public function filterLidoFields($coreFields)
    {
        $include = [
            'Author Notes',
            'Available Online',
            'child_records',
            'Collection',
            'DOI',
            'Edition',
            'Events',
            'Extent',
            'Format',
            'Inscriptions',
            'Introduction',
            'Inventory ID',
            'ISBN',
            'ISSN',
            'Language',
            'lido_editions',
            'Location LIDO',
            'Measurements',
            'Organisation',
            'original_work_language',
            'Other Classification',
            'Other Classifications',
            'Other ID',
            'Parent Archive',
            'Parent Collection',
            'Parent Series',
            'Parent Unclassified Entity',
            'Parent Work',
            'Publications',
            'Published in',
            'Subject Actor',
            'Subject Date',
            'Subject Detail',
            'Subject Place',
            'SubjectsWithoutPlaces',
        ];
        return $this->filterFields($coreFields, $include);
    }

    /**
     * Filter unnecessary fields from QDC records.
     *
     * @param array $coreFields data to filter.
     *
     * @return array
     */
    public function filterQDCFields($coreFields)
    {
        $include = [
            'Access',
            'Additional Information',
            'Audience',
            'Author Notes',
            'Awards',
            'Bibliography',
            'child_records',
            'Contained In',
            'DOI',
            'Edition',
            'Education Programs',
            'Finding Aid',
            'Genre',
            'ISBN',
            'ISSN',
            'Inventory ID',
            'Item Notes',
            'Keywords',
            'Language',
            'New Title',
            'original_work_language',
            'Physical Description',
            'Physical Medium',
            'Playing Time',
            'Presenters',
            'Previous Title',
            'Production Credits',
            'Publication Frequency',
            'Publication_Place',
            'Published in',
            'Related Items',
            'Related Places',
            'Series',
            'Subjects',
            'System Format',
        ];
        return $this->filterFields($coreFields, $include);
    }

    /**
     * Filter unnecessary fields from Lrmi records
     *
     * @param array $coreFields data to filter
     *
     * @return array
     */
    public function filterLrmiFields($coreFields)
    {
        $include = [
            'Access',
            'Access Restrictions',
            'Accessibility Feature',
            'Accessibility Hazard',
            'Actors',
            'Additional Information',
            'Audience',
            'Author Notes',
            'Authors',
            'Awards',
            'Bibliography',
            'child_records',
            'Contained In',
            'DOI',
            'Edition',
            'Education Programs',
            'Educational Level',
            'Educational Role',
            'Educational Subject',
            'Educational Use',
            'Extent',
            'Finding Aid',
            'Format',
            'Genre',
            'ISBN',
            'ISSN',
            'Identifiers',
            'Inventory ID',
            'Item Description FWD',
            'Item Notes',
            'Keywords',
            'Language',
            'Learning Resource Type',
            'New Title',
            'Objective and Content',
            'Online Access',
            'Organisation',
            'original_work_language',
            'Other Titles',
            'Physical Description',
            'Physical Medium',
            'Playing Time',
            'Presenters',
            'Previous Title',
            'Production Credits',
            'Publication Frequency',
            'Publication_Place',
            'Publish date',
            'Published',
            'Published in',
            'Record Links',
            'Related Items',
            'Related Materials',
            'Related Places',
            'Series',
            'Source Collection',
            'Subjects',
            'System Format',
        ];
        return $this->filterFields($coreFields, $include);
    }

    /**
     * Filter unnecessary fields from EAD records.
     *
     * @param array $coreFields data to filter.
     *
     * @return array
     */
    public function filterEADFields($coreFields)
    {
        $include = [
            'Access Restrictions',
            'Additional Information',
            'Archive',
            'Archive File',
            'Archive Origination',
            'Archive Series',
            'Audience',
            'Author Notes',
            'Authors',
            'Awards',
            'Bibliography',
            'DOI',
            'Date',
            'Edition',
            'Education Programs',
            'Extent',
            'Finding Aid',
            'Format',
            'Genre',
            'ISBN',
            'ISSN',
            'Item Notes',
            'Keywords',
            'Language',
            'Location',
            'New Title',
            'Other Titles',
            'Playing Time',
            'Presenters',
            'Previous Title',
            'Production Credits',
            'Publication Frequency',
            'Publication_Place',
            'Publish date',
            'Publisher',
            'Record Links',
            'Related Items',
            'Related Places',
            'Subjects',
            'System Format',
            'Unit ID',
            'original_work_language',
        ];
        return $this->filterFields($coreFields, $include);
    }

    /**
     * Filter unnecessary fields from EAD records.
     *
     * @param array $coreFields data to filter.
     *
     * @return array
     */
    public function filterEAD3Fields($coreFields)
    {
        $include = [
            'Access Restrictions',
            'Access Restrictions Extended',
            'Additional Information Extended',
            'Appraisal',
            'Archive',
            'archive_authors',
            'Archive File',
            'Archive Origination',
            'archive_other_authors',
            'Archive Relations',
            'Archive Series',
            'Audience',
            'Author Notes',
            'Awards',
            'Bibliography', 'Container Information',
            'Content Description',
            'DOI',
            'Dates',
            'Edition',
            'Education Programs',
            'Extent',
            'Finding Aid Extended',
            'Format',
            'Genre',
            'ISBN',
            'ISSN',
            'Item History',
            'Item Notes',
            'Keywords',
            'Language',
            'Location',
            'Material Arrangement',
            'Material Condition',
            'New Title',
            'original_work_language',
            'Other Related Material',
            'Other Titles',
            'Playing Time',
            'Presenters',
            'Previous Title',
            'Production Credits',
            'Publication Frequency',
            'Publication_Place',
            'Publish date',
            'Publisher',
            'Related Items',
            'Related Materials',
            'Related Places',
            'Subject Actor',
            'subjects_extended',
            'System Format',
            'Unit IDs',
        ];
        return $this->filterFields($coreFields, $include);
    }

    /**
     * Filter unnecessary fields from Primo records.
     *
     * @param array $coreFields data to filter.
     *
     * @return array
     */
    public function filterPrimoFields($coreFields)
    {
        $include = [
            'Access',
            'Additional Information',
            'Audience',
            'Author Notes',
            'Awards',
            'Bibliography',
            'child_records',
            'DOI',
            'Description FWD',
            'Edition',
            'Finding Aid',
            'ISBN',
            'ISSN',
            'Item Notes',
            'Language',
            'New Title',
            'Physical Description',
            'Playing Time',
            'Previous Title',
            'Production Credits',
            'Publication Frequency',
            'Publication_Place',
            'Publish date',
            'Record Links',
            'Related Items',
            'Series',
            'Source Collection',
            'Subjects',
            'System Format',
            'Citations',
        ];
        return $this->filterFields($coreFields, $include);
    }

    /**
     * Filter unnecessary fields from Forward records.
     *
     * @param array $coreFields data to filter.
     *
     * @return array
     */
    public function filterForwardFields($coreFields)
    {
        $include = [
            'Access',
            'Actors',
            'Additional Information',
            'Age Limit',
            'Archive Films',
            'Aspect Ratio',
            'Audience',
            'Author Notes',
            'Awards',
            'Bibliography',
            'Broadcasting Dates',
            'child_records',
            'Color',
            'DOI',
            'Description FWD',
            'Distribution',
            'Education Programs',
            'Exterior Images',
            'Film Copies',
            'Film Festivals',
            'Filming Date',
            'Filming Location Notes',
            'Finding Aid',
            'Foreign Distribution',
            'Funding',
            'Genre',
            'ISBN',
            'ISSN',
            'Inspection Details',
            'Interior Images',
            'Inventory ID',
            'Item Description FWD',
            'Keywords',
            'Language',
            'Movie Thanks',
            'Music',
            'New Title',
            'Number of Viewers',
            'Online Access',
            'Original Work',
            'original_work_language',
            'Other Screenings',
            'Physical Description',
            'Playing Time',
            'Premiere Night',
            'Premiere Theaters',
            'Press Reviews',
            'Previous Title',
            'Production',
            'Production Costs',
            'Production Credits',
            'Publication Frequency',
            'Publication_Place',
            'Publish date',
            'Published',
            'Record Links',
            'Related Items',
            'Related Places',
            'Secondary Authors',
            'Series',
            'Sound',
            'Studios',
            'Subjects',
            'System Format',
        ];
        return $this->filterFields($coreFields, $include);
    }

    /**
     * Filter unnecessary fields from AIPA records.
     *
     * @param array $coreFields data to filter.
     *
     * @return array
     */
    public function filterAipaFields($coreFields)
    {
        $include = [
            'Additional Information AIPA',
            'Provenance',
            'Related Events',
            'subjects_extended',
            'Subject Date',
            'Subject Place',
        ];
        return $this->filterFields($coreFields, $include);
    }

    /**
     * Get default configuration.
     *
     * @param string $key Key for configuration to look up.
     *
     * @return array
     */
    public function getDefaults($key = 'core'): array
    {
        $defaults = parent::getDefaults($key);
        if (!isset($this->driver)) {
            return $defaults;
        }
        $backend = $this->driver->getSourceIdentifier();
        if (in_array($backend, ['Solr', 'SolrAuth', 'L1'])) {
            $type = strtolower($this->driver->getRecordFormat());
        } else {
            $type = strtolower($backend);
        }
        switch ($type) {
            case 'aipa':
                return $this->filterAipaFields($defaults);
            case 'dc':
            case 'qdc':
                return $this->filterQDCFields($defaults);
            case 'eaccpf':
                return $this->filterFields($defaults);
            case 'ead':
                return $this->filterEADFields($defaults);
            case 'ead3':
                return $this->filterEAD3Fields($defaults);
            case 'forward':
                return $this->filterForwardFields($defaults);
            case 'forwardauthority':
                return $defaults;
            case 'lido':
                return $this->filterLidoFields($defaults);
            case 'lrmi':
                return $this->filterLrmiFields($defaults);
            case 'marc':
                return $this->filterMarcFields($defaults);
            case 'marcauthority':
                return $defaults;
            case 'primo':
                return $this->filterPrimoFields($defaults);
            default:
                return $defaults;
        }
    }

    /**
     * Helper method for getting a spec of field groups from FieldGroupBuilder.
     *
     * @param array  $groups        Array specifying the groups. See
     *                              FieldGroupBuilder::addGroup() for details.
     * @param array  $lines         All lines used in the groups. If this contains
     *                              lines not specified in $groups, all unused lines
     *                              will be appended as their own group.
     * @param string $template      Default group template to use if not specified
     *                              for a group (optional, set to null to use the
     *                              default value).
     * @param array  $options       Additional options to be merged with group
     *                              specific additional options (optional, set to
     *                              null to use the default value). See
     *                              FieldGroupBuilder::addGroup() for details.
     * @param array  $unusedOptions Additional options for the unused lines group
     *                              (optional, set to null to use the default value).
     *                              See FieldGroupBuilder::addGroup()
     *                              for details.
     *
     * @return array
     */
    public function getGroupedFields(
        $groups,
        $lines,
        $template = null,
        $options = null,
        $unusedOptions = null
    ) {
        $template ??= 'core-field-group-fields.phtml';
        $options ??= [];
        $unusedOptions ??= $options;

        $fieldGroups = new FieldGroupBuilder();
        $fieldGroups->setGroups(
            $groups,
            $lines,
            $template,
            $options,
            $unusedOptions
        );
        return $fieldGroups->getArray();
    }

    /**
     * Create formatted key/value data based on a record driver and grouped
     * field spec.
     *
     * @param RecordDriver $driver Record driver object.
     * @param array        $groups Grouped formatting specification.
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getGroupedData(RecordDriver $driver, array $groups)
    {
        // Apply the group spec.
        $result = [];
        foreach ($groups as $group) {
            if (!empty($group['skipGroup'])) {
                continue;
            }
            $lines = $group['lines'];
            $data = $this->getData($driver, $lines);
            if (empty($data)) {
                continue;
            }
            // Render the fields in the group as the value for the group.
            $value = $this->renderRecordDriverTemplate(
                $data,
                ['template' => $group['template']]
            );
            $result[] = [
                'label' => $group['label'],
                'value' => $value,
                'context' => $group['options']['context'] ?? [],
            ];
        }
        return $result;
    }

    /**
     * Returns an array containing core fields suitable to be shown.
     * If record source has hidden fields, excludes them from result.
     *
     * @param array $coreFields Core fields list
     * @param array $include    Fields to include for the driver (optional)
     *
     * @return array
     */
    protected function filterFields(array $coreFields, array $include = []): array
    {
        $intersected = $include ? array_intersect_key($coreFields, array_flip($include)) : $coreFields;
        $config = $this->getView()->plugin('config')->get('datasources');
        $source = $this->driver?->tryMethod('getDataSource');
        if ($source && $hide = $config->$source?->hidden_record_fields) {
            $intersected = array_diff_key($intersected, array_flip($hide->toArray()));
        }
        return $intersected;
    }
}
