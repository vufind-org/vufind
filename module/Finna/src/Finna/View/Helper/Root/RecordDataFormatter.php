<?php
/**
 * Record driver data formatting view helper
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2016.
 * Copyright (C) The National Library of Finland 2017.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */
namespace Finna\View\Helper\Root;

use Finna\View\Helper\Root\RecordDataFormatter\FieldGroupBuilder;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

/**
 * Record driver data formatting view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
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
            'Access', 'Accessibility Feature', 'Accessibility Hazard',
            'Access Restrictions Extended', 'Additional Information', 'Age Limit',
            'Archive Films', 'Aspect Ratio', 'Audience',
            'Author Notes', 'Available Online', 'Awards',
            'Bibliography', 'Broadcasting Dates', 'child_records',
            'Classification', 'Collection', 'Color',
            'Content Description', 'Copyright Notes', 'Date',
            'Description FWD', 'Dissertation Note', 'Distribution',
            'DOI', 'Educational Level', 'Educational Role',
            'Educational Subject', 'Educational Use', 'Education Programs',
            'Event Notice', 'Events', 'Exterior Images',
            'Film Copies', 'Film Festivals', 'Filming Date',
            'Filming Location Notes', 'Finding Aid', 'First Lyrics',
            'Foreign Distribution', 'Funding', 'Genre',
            'Inscriptions', 'Inspection Details', 'Interior Images',
            'Inventory ID', 'ISBN', 'ISSN',
            'Item Description', 'Item History', 'Keywords',
            'Language', 'Language Notes', 'Learning Resource Type',
            'Location', 'Manufacturer', 'Measurements',
            'Medium of Performance', 'Methodology', 'New Title',
            'Notated Music Format', 'Notes', 'Objective and Content',
            'original_work_language', 'Other Classification',
            'Other Classifications', 'Other ID',
            'Other Links', 'Other Screenings',
            'Other Titles', 'Physical Description', 'Place of Origin',
            'Playing Time', 'Premiere Night', 'Premiere Theaters',
            'Presenters', 'Previous Title', 'Production',
            'Production Costs', 'Production Credits', 'Projected Publication Date',
            'Publication Frequency', 'Publications', 'Publication_Place',
            'Publish date', 'Publisher', 'Publisher or Distributor Number',
            'Record Links', 'Related Items', 'Related Places',
            'Scale', 'Secondary Authors', 'Series',
            'Sound', 'Source of Acquisition', 'Standard Codes',
            'Studios', 'Subject Actor', 'Subject Date',
            'Subject Detail', 'Subject Place', 'subjects_extended',
            'System Format', 'Terms of Use', 'Time Period',
            'Time Period of Creation', 'Trade Availability Note',
            'Uncontrolled Title', 'Uniform Title', 'Unit ID', 'Unit IDs'
        ];

        return array_intersect_key($coreFields, array_flip($include));
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
            'Accessibility Feature', 'Accessibility Hazard',
            'Access Restrictions Extended', 'Additional Information',
            'Age Limit', 'Archive Films',
            'Archive Origination', 'Archive Series', 'Aspect Ratio',
            'Audience', 'Author Notes', 'Available Online',
            'Awards', 'Bibliography', 'Broadcasting Dates',
            'child_records', 'Classification', 'Collection',
            'Color', 'Content Description', 'Copyright Notes',
            'Date', 'Description FWD', 'Dissertation Note',
            'Distribution', 'DOI', 'Edition',
            'Educational Level', 'Educational Role', 'Educational Subject',
            'Educational Use', 'Education Programs', 'Event Notice',
            'Events', 'Exterior Images', 'Film Copies',
            'Film Festivals', 'Filming Date', 'Filming Location Notes',
            'Finding Aid', 'First Lyrics', 'Foreign Distribution',
            'Format', 'Funding', 'Genre',
            'Inscriptions', 'Inspection Details', 'Interior Images',
            'Inventory ID', 'ISBN', 'ISSN',
            'Item Description', 'Item History', 'Keywords',
            'Language', 'Language Notes', 'Learning Resource Type',
            'Location', 'Manufacturer', 'Measurements',
            'Medium of Performance', 'Methodology', 'New Title',
            'Notated Music Format', 'Notes', 'Objective and Content',
            'Organisation', 'original_work_language', 'Other Classification',
            'Other Classifications', 'Other ID', 'Other Links',
            'Other Screenings', 'Physical Description', 'Place of Origin',
            'Playing Time', 'Premiere Night', 'Premiere Theaters',
            'Presenters', 'Previous Title', 'Production',
            'Production Costs', 'Production Credits', 'Projected Publication Date',
            'Publication Frequency', 'Publications', 'Publication_Place',
            'Published in', 'Publisher or Distributor Number', 'Record Links',
            'Related Items', 'Related Places', 'Scale',
            'Secondary Authors', 'Series', 'Sound',
            'Source of Acquisition', 'Standard Codes', 'Studios',
            'Subject Actor', 'Subject Date', 'Subject Detail',
            'Subject Place', 'Subjects', 'subjects_extended',
            'System Format', 'Terms of Use', 'Time Period',
            'Time Period of Creation', 'Trade Availability Note',
            'Uncontrolled Title', 'Uniform Title', 'Unit IDs'
        ];

        return array_intersect_key($coreFields, array_flip($include));
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
            'Access', 'Accessibility Feature', 'Accessibility Hazard',
            'Access Restrictions Extended', 'Additional Information', 'Age Limit',
            'Archive Films', 'Archive Origination', 'Archive Series',
            'Aspect Ratio', 'Audience', 'Author Notes',
            'Available Online', 'Awards', 'Bibliography',
            'Broadcasting Dates', 'child_records', 'Classification',
            'Collection', 'Color', 'Content Description',
            'Copyright Notes', 'Date', 'Description FWD',
            'Dissertation Note', 'Distribution', 'DOI',
            'Edition', 'Educational Level', 'Educational Role',
            'Educational Subject', 'Educational Use', 'Education Programs',
            'Event Notice', 'Events', 'Exterior Images',
            'Film Copies', 'Film Festivals', 'Filming Date',
            'Filming Location Notes', 'Finding Aid', 'First Lyrics',
            'Foreign Distribution', 'Funding', 'Genre',
            'Inscriptions', 'Inspection Details', 'Interior Images',
            'Inventory ID', 'ISSN', 'Item Description',
            'Item History', 'Keywords', 'Language',
            'Language Notes', 'Learning Resource Type', 'Location',
            'Manufacturer', 'Measurements', 'Medium of Performance',
            'Methodology', 'New Title', 'Notated Music Format',
            'Notes', 'Objective and Content', 'original_work_language',
            'Other Classification', 'Other Classifications', 'Other ID',
            'Other Links', 'Other Screenings', 'Other Titles',
            'Physical Description', 'Place of Origin', 'Playing Time',
            'Premiere Night', 'Premiere Theaters', 'Presenters',
            'Previous Title', 'Production', 'Production Costs',
            'Production Credits', 'Projected Publication Date',
            'Publication Frequency', 'Publications',
            'Publication_Place', 'Published in',
            'Publisher or Distributor Number', 'Record Links', 'Related Items',
            'Related Places', 'Scale', 'Secondary Authors',
            'Series', 'Sound', 'Source of Acquisition',
            'Standard Codes', 'Studios', 'Subject Actor',
            'Subject Date', 'Subject Detail', 'Subject Place',
            'Subjects', 'subjects_extended', 'System Format',
            'Terms of Use', 'Time Period', 'Time Period of Creation',
            'Trade Availability Note', 'Uncontrolled Title', 'Uniform Title',
            'Unit ID', 'Unit IDs'
        ];

        return array_intersect_key($coreFields, array_flip($include));
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
            'Access', 'Accessibility Feature', 'Accessibility Hazard',
            'Access Restrictions', 'Access Restrictions Extended',
            'Additional Information', 'Age Limit',
            'Archive Films', 'Archive Origination',
            'Archive Series', 'Aspect Ratio', 'Audience',
            'Author Notes', 'Available Online', 'Awards',
            'Bibliography', 'Broadcasting Dates', 'child_records',
            'Classification', 'Collection', 'Color',
            'Copyright Notes', 'Date', 'Dissertation Note',
            'Distribution', 'DOI', 'Edition',
            'Educational Level', 'Educational Role', 'Educational Subject',
            'Educational Use', 'Education Programs', 'Event Notice',
            'Events', 'Extent', 'Exterior Images',
            'Film Copies', 'Film Festivals', 'Filming Date',
            'Filming Location Notes', 'Finding Aid', 'First Lyrics',
            'Foreign Distribution', 'Funding', 'Genre',
            'Inscriptions', 'Inspection Details', 'Interior Images',
            'Inventory ID', 'ISBN', 'ISSN',
            'Item Description FWD', 'Item History', 'Keywords',
            'Language', 'Language Notes', 'Learning Resource Type',
            'Location', 'Manufacturer', 'Measurements',
            'Medium of Performance', 'Methodology', 'Music',
            'New Title', 'Notated Music Format', 'Notes',
            'Objective and Content', 'Online Access', 'Original Work',
            'original_work_language', 'Other Classification',
            'Other Classifications', 'Other ID', 'Other Links', 'Other Screenings',
            'Other Titles', 'Physical Description', 'Place of Origin',
            'Playing Time', 'Premiere Night', 'Premiere Theaters',
            'Presenters', 'Press Reviews', 'Previous Title',
            'Production', 'Production Costs', 'Production Credits',
            'Projected Publication Date', 'Publication Frequency', 'Publications',
            'Publication_Place', 'Published in', 'Publisher or Distributor Number',
            'Record Links', 'Related Items', 'Related Places',
            'Scale', 'Secondary Authors', 'Series',
            'Sound', 'Source of Acquisition', 'Standard Codes',
            'Studios', 'Subject Actor', 'Subject Date',
            'Subject Detail', 'Subject Place', 'Subjects',
            'subjects_extended', 'System Format', 'Terms of Use',
            'Time Period', 'Time Period of Creation', 'Trade Availability Note',
            'Uncontrolled Title', 'Uniform Title', 'Unit ID',
            'Unit IDs'
        ];

        return array_intersect_key($coreFields, array_flip($include));
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
            'Accessibility Feature', 'Accessibility Hazard', 'Access Restrictions',
            'Access Restrictions Extended', 'Additional Information', 'Age Limit',
            'Archive', 'Archive Films', 'Archive Origination',
            'Archive Series', 'Aspect Ratio', 'Audience',
            'Author Notes', 'Authors', 'Available Online',
            'Awards', 'Bibliography', 'Broadcasting Dates',
            'child_records', 'Classification', 'Collection',
            'Color', 'Content Description', 'Copyright Notes',
            'Date', 'Description FWD', 'Dissertation Note',
            'Distribution', 'DOI', 'Edition',
            'Educational Level', 'Educational Role', 'Educational Subject',
            'Educational Use', 'Education Programs', 'Event Notice',
            'Events', 'Extent', 'Exterior Images',
            'Film Copies', 'Film Festivals', 'Filming Date',
            'Filming Location Notes', 'Finding Aid', 'First Lyrics',
            'Foreign Distribution', 'Format', 'Funding',
            'Genre', 'Inscriptions', 'Inspection Details',
            'Interior Images', 'ISBN', 'ISSN',
            'Item Description', 'Item History', 'Keywords',
            'Language', 'Language Notes', 'Learning Resource Type',
            'Location', 'Manufacturer', 'Measurements',
            'Medium of Performance', 'Methodology', 'Music',
            'New Title', 'Notated Music Format', 'Notes',
            'Objective and Content', 'Original Work', 'original_work_language',
            'Other Classification', 'Other Classifications', 'Other ID',
            'Other Links', 'Other Screenings', 'Other Titles',
            'Place of Origin', 'Playing Time', 'Premiere Night',
            'Premiere Theaters', 'Presenters', 'Press Reviews',
            'Previous Title', 'Production', 'Production Costs',
            'Production Credits', 'Projected Publication Date',
            'Publication Frequency', 'Publications',
            'Publication_Place', 'Publish date',
            'Publisher', 'Publisher or Distributor Number', 'Record Links',
            'Related Items', 'Related Places', 'Scale',
            'Secondary Authors', 'Sound', 'Source of Acquisition',
            'Standard Codes', 'Studios', 'Subject Actor',
            'Subject Date', 'Subject Detail', 'Subject Place',
            'Subjects', 'subjects_extended', 'System Format',
            'Terms of Use', 'Time Period', 'Time Period of Creation',
            'Trade Availability Note', 'Uncontrolled Title', 'Uniform Title',
            'Unit ID', 'Unit IDs'
        ];

        return array_intersect_key($coreFields, array_flip($include));
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
            'Accessibility Feature', 'Accessibility Hazard',
            'Access Restrictions', 'Access Restrictions Extended',
            'Additional Information',
            'Age Limit', 'Archive', 'Archive Films',
            'Archive Origination', 'Archive Relations',
            'Archive Series', 'Aspect Ratio', 'Audience',
            'Authors', 'Author Notes', 'Available Online', 'Awards',
            'Bibliography', 'Broadcasting Dates', 'child_records',
            'Classification', 'Collection', 'Color',
            'Content Description', 'Copyright Notes', 'Dates',
            'Description FWD', 'Dissertation Note', 'Distribution',
            'DOI', 'Edition', 'Educational Level',
            'Educational Role', 'Educational Subject', 'Educational Use',
            'Education Programs', 'Event Notice', 'Events',
            'Extent', 'Exterior Images', 'Film Copies',
            'Film Festivals', 'Filming Date', 'Filming Location Notes',
            'Finding Aid', 'First Lyrics', 'Foreign Distribution',
            'Format', 'Funding', 'Genre',
            'Inscriptions', 'Inspection Details', 'Interior Images',
            'ISBN', 'ISSN', 'Item Description',
            'Item History', 'Keywords', 'Language',
            'Language Notes', 'Learning Resource Type', 'Location',
            'Manufacturer', 'Measurements', 'Medium of Performance',
            'Methodology', 'Music', 'New Title',
            'Notated Music Format', 'Notes', 'Objective and Content',
            'Original Work', 'original_work_language', 'Other Classification',
            'Other Classifications', 'Other ID', 'Other Links',
            'Other Screenings', 'Other Titles', 'Place of Origin',
            'Playing Time', 'Premiere Night', 'Premiere Theaters',
            'Presenters', 'Press Reviews', 'Previous Title',
            'Production', 'Production Costs', 'Production Credits',
            'Projected Publication Date', 'Publication Frequency', 'Publications',
            'Publication_Place', 'Publish date', 'Publisher',
            'Publisher or Distributor Number', 'Record Links', 'Related Items',
            'Related Places', 'Scale', 'Secondary Authors',
            'Sound', 'Source of Acquisition', 'Standard Codes',
            'Studios', 'Subject Actor', 'Subject Date',
            'Subject Detail', 'Subject Place', 'Subjects',
            'subjects_extended', 'System Format', 'Terms of Use',
            'Time Period', 'Time Period of Creation', 'Trade Availability Note',
            'Uncontrolled Title', 'Uniform Title', 'Unit IDs'
        ];

        return array_intersect_key($coreFields, array_flip($include));
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
            'Access', 'Accessibility Feature', 'Accessibility Hazard',
            'Access Restrictions Extended', 'Additional Information', 'Age Limit',
            'Archive Films', 'Archive Origination', 'Archive Series',
            'Aspect Ratio', 'Audience', 'Author Notes',
            'Authors', 'Available Online', 'Awards',
            'Bibliography', 'Broadcasting Dates', 'child_records',
            'Classification', 'Collection', 'Color',
            'Content Description', 'Copyright Notes', 'Date',
            'Description FWD', 'Dissertation Note', 'Distribution',
            'DOI', 'Edition', 'Educational Level',
            'Educational Role', 'Educational Subject', 'Educational Use',
            'Education Programs', 'Event Notice', 'Events',
            'Exterior Images', 'Film Copies', 'Film Festivals',
            'Filming Date', 'Filming Location Notes', 'Finding Aid',
            'First Lyrics', 'Foreign Distribution', 'Funding',
            'Genre', 'Inscriptions', 'Inspection Details',
            'Interior Images', 'Inventory ID', 'ISBN',
            'ISSN', 'Item Description', 'Item History',
            'Keywords', 'Language', 'Language Notes',
            'Learning Resource Type', 'Location', 'Manufacturer',
            'Measurements', 'Medium of Performance', 'Methodology',
            'Music', 'New Title', 'Notated Music Format',
            'Notes', 'Objective and Content', 'Original Work',
            'original_work_language', 'Other Classification',
            'Other Classifications', 'Other ID',
            'Other Links', 'Other Screenings',
            'Other Titles', 'Physical Description', 'Place of Origin',
            'Playing Time', 'Premiere Night', 'Premiere Theaters',
            'Presenters', 'Press Reviews', 'Previous Title',
            'Production', 'Production Costs', 'Production Credits',
            'Projected Publication Date', 'Publication Frequency', 'Publications',
            'Publication_Place', 'Publish date', 'Publisher or Distributor Number',
            'Record Links', 'Related Items', 'Related Places',
            'Scale', 'Secondary Authors', 'Series',
            'Sound', 'Source Collection', 'Source of Acquisition',
            'Standard Codes', 'Studios', 'Subject Actor',
            'Subject Date', 'Subject Detail', 'Subject Place',
            'Subjects', 'subjects_extended', 'System Format',
            'Terms of Use', 'Time Period', 'Time Period of Creation',
            'Trade Availability Note', 'Uncontrolled Title', 'Uniform Title',
            'Unit ID', 'Unit IDs'
        ];

        return array_intersect_key($coreFields, array_flip($include));
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
            'Access', 'Accessibility Feature', 'Accessibility Hazard',
            'Access Restrictions Extended', 'Actors', 'Additional Information',
            'Age Limit', 'Archive Films', 'Archive Origination',
            'Archive Series', 'Aspect Ratio', 'Audience',
            'Author Notes', 'Available Online', 'Awards',
            'Bibliography', 'Broadcasting Dates', 'child_records',
            'Classification', 'Collection', 'Color',
            'Content Description', 'Copyright Notes', 'Date',
            'Description FWD', 'Dissertation Note', 'Distribution',
            'DOI', 'Educational Level', 'Educational Role',
            'Educational Subject', 'Educational Use', 'Education Programs',
            'Event Notice', 'Events', 'Exterior Images',
            'Film Copies', 'Film Festivals', 'Filming Date',
            'Filming Location Notes', 'Finding Aid', 'First Lyrics',
            'Foreign Distribution', 'Funding', 'Genre',
            'Inscriptions', 'Inspection Details', 'Interior Images',
            'Inventory ID', 'ISBN', 'ISSN',
            'Item Description FWD', 'Item History', 'Keywords',
            'Language', 'Language Notes', 'Learning Resource Type',
            'Location', 'Manufacturer', 'Measurements',
            'Medium of Performance', 'Methodology', 'Music',
            'New Title', 'Notated Music Format', 'Notes',
            'Objective and Content', 'Online Access', 'Original Work',
            'original_work_language', 'Other Classification',
            'Other Classifications', 'Other ID',
            'Other Links', 'Other Screenings',
            'Physical Description', 'Place of Origin', 'Playing Time',
            'Premiere Night', 'Premiere Theaters', 'Press Reviews',
            'Previous Title', 'Production', 'Production Costs',
            'Production Credits', 'Projected Publication Date',
            'Publication Frequency', 'Publications',
            'Publication_Place', 'Publish date',
            'Published', 'Publisher or Distributor Number', 'Record Links',
            'Related Items', 'Related Places', 'Scale',
            'Secondary Authors', 'Series', 'Sound',
            'Source of Acquisition', 'Standard Codes', 'Studios',
            'Subject Actor', 'Subject Date', 'Subject Detail',
            'Subject Place', 'Subjects', 'subjects_extended',
            'System Format', 'Terms of Use', 'Time Period',
            'Time Period of Creation', 'Trade Availability Note',
            'Uncontrolled Title', 'Uniform Title', 'Unit ID', 'Unit IDs'
        ];

        return array_intersect_key($coreFields, array_flip($include));
    }

    /**
     * Filter unnecessary fields from EAD-collection records.
     *
     * @param array  $coreFields data to filter.
     * @param string $type       Collection type (ead|ead3)
     *
     * @return array
     */
    public function filterCollectionFields($coreFields, $type = 'ead')
    {
        $include = [
            'Accessibility Feature', 'Accessibility Hazard', 'Access Restrictions',
            'Access Restrictions Extended', 'Actors', 'Additional Information',
            'Age Limit', 'Archive', 'Archive Films',
            'Archive Origination', 'Archive Series', 'Aspect Ratio',
            'Audience', 'Author Notes', 'Authors',
            'Available Online', 'Awards', 'Bibliography',
            'Broadcasting Dates', 'child_records', 'Classification',
            'Collection', 'Color', 'Content Description',
            'Copyright Notes', 'Date', 'Dates', 'Description FWD',
            'Dissertation Note', 'Distribution', 'DOI',
            'Edition', 'Educational Level', 'Educational Role',
            'Educational Subject', 'Educational Use', 'Education Programs',
            'Event Notice', 'Events', 'Extent',
            'Exterior Images', 'Film Copies', 'Film Festivals',
            'Filming Date', 'Filming Location Notes', 'Finding Aid',
            'First Lyrics', 'Foreign Distribution', 'Funding',
            'Genre', 'Inscriptions', 'Inspection Details',
            'Interior Images', 'Inventory ID', 'ISBN',
            'ISSN', 'Item Description', 'Item History',
            'Keywords', 'Language', 'Language Notes',
            'Learning Resource Type', 'Location', 'Manufacturer',
            'Measurements', 'Medium of Performance', 'Methodology',
            'Music', 'New Title', 'Notated Music Format',
            'Notes', 'Objective and Content', 'Organisation',
            'Original Work', 'original_work_language', 'Other Classification',
            'Other Classifications', 'Other ID', 'Other Links',
            'Other Screenings', 'Other Titles', 'Place of Origin',
            'Playing Time', 'Premiere Night', 'Premiere Theaters',
            'Presenters', 'Press Reviews', 'Previous Title',
            'Production', 'Production Costs', 'Production Credits',
            'Projected Publication Date', 'Publication Frequency', 'Publications',
            'Publication_Place', 'Publish date', 'Publisher',
            'Publisher or Distributor Number', 'Record Links', 'Related Items',
            'Related Places', 'Scale', 'Secondary Authors',
            'Series', 'Sound', 'Source of Acquisition',
            'Standard Codes', 'Studios', 'Subject Actor',
            'Subject Date', 'Subject Detail', 'Subject Place',
            'Subjects', 'subjects_extended', 'System Format',
            'Terms of Use', 'Time Period', 'Time Period of Creation',
            'Trade Availability Note', 'Uncontrolled Title', 'Uniform Title',
            'Unit ID', 'Unit IDs'
        ];

        $fields = array_intersect_key($coreFields, array_flip($include));

        return $type === 'ead' ?
            $this->filterEADFields($fields) :
            $this->filterEAD3Fields($fields);
    }

    /**
     * Helper method for getting a spec of field groups.
     *
     * @param array  $groups        Array specifying the groups.
     * @param array  $lines         All lines used in the groups.
     * @param string $template      Default group template to use if not
     *                              specified (optional).
     * @param array  $options       Additional options to use if not specified
     *                              for a group (optional).
     * @param array  $unusedOptions Additional options for unused lines
     *                              (optional).
     *
     * @return array
     */
    public function getGroupedFields($groups, $lines,
        $template = 'core-field-group-fields.phtml', $options = [],
        $unusedOptions = []
    ) {
        $fieldGroups = new FieldGroupBuilder();
        $fieldGroups->setGroups(
            $groups, $lines, $template, $options, $unusedOptions
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
            $lines = $group['lines'];
            $data = $this->getData($driver, $lines);
            if (empty($data)) {
                continue;
            }
            // Render the fields in the group as the value for the group.
            $value = $this->renderRecordDriverTemplate(
                $driver, $data, ['template' => $group['template']]
            );
            $result[] = [
                'label' => $group['label'],
                'value' => $value,
                'context' => $group['context'],
            ];
        }
        return $result;
    }
}
