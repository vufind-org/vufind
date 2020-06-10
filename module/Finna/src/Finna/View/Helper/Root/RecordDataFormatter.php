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
        $filter = [
            'Contributors', 'Extent', 'Format', 'Organisation', 'Published',
            'Online Access', 'Original Work', 'Assistants', 'Authors', 'Music',
            'Press Reviews', 'mainFormat', 'Access Restrictions', 'Edition',
            'Archive', 'Archive Series', 'Archive Origination', 'Archive Relations',
            'Item Description FWD', 'Published in', 'Relations', 'Source Collection'
        ];
        foreach ($filter as $key) {
            unset($coreFields[$key]);
        }
        return $coreFields;
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
        $filter = [
            'Contributors', 'Extent', 'Published', 'Online Access',
            'Original Work', 'Assistants', 'Authors', 'Music',
            'Press Reviews', 'Publisher', 'Access Restrictions', 'Unit ID',
            'Other Titles', 'Archive', 'Access', 'Item Description FWD',
            'Publish date', 'Relations', 'Archive Relations', 'Source Collection'
        ];
        foreach ($filter as $key) {
            unset($coreFields[$key]);
        }
        return $coreFields;
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
        $filter = [
            'Contributors', 'Extent', 'Format', 'Organisation', 'Published',
            'Online Access', 'Original Work', 'Assistants', 'Authors', 'Music',
            'Press Reviews', 'Publisher', 'Access Restrictions', 'mainFormat',
            'Archive', 'Item Description FWD', 'Publish date', 'Relations',
            'Archive Relations', 'Source Collection', 'ISBN'
        ];
        foreach ($filter as $key) {
            unset($coreFields[$key]);
        }
        return $coreFields;
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
        $filter = [
            'Contributors', 'Organisation', 'Inventory ID', 'Online Access',
            'Access', 'Item Description FWD', 'Physical Description',
            'Published in', 'Published', 'Relations', 'Archive Relations', 'Series',
            'Source Collection'
        ];
        foreach ($filter as $key) {
            unset($coreFields[$key]);
        }

        return $coreFields;
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
        $filter = [
            'Access Restrictions', 'Authors', 'Contributors', 'Organisation',
            'Inventory ID', 'Online Access', 'Access', 'Item Description FWD',
            'Physical Description', 'Published in', 'Published', 'Series',
            'Source Collection', 'Unit ID'
        ];
        foreach ($filter as $key) {
            unset($coreFields[$key]);
        }
        return $coreFields;
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
        $filter = [
            'Contributors', 'Extent', 'Archive', 'Publisher', 'Organisation',
            'Item Description FWD', 'Published in', 'Published', 'Description',
            'Format', 'Online Access', 'Relations', 'Archive Relations',
            'Access Restrictions'
        ];
        foreach ($filter as $key) {
            unset($coreFields[$key]);
        }
        return $coreFields;
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
        $filter = [
            'Publisher','Edition', 'Extent', 'Archive', 'Published in', 'Format',
            'Other Titles', 'Presenters', 'Organisation', 'Authors',
            'Access Restrictions', 'Item Description', 'Publisher', 'Relations',
            'Source Collection', 'Archive Relations'
        ];
        foreach ($filter as $key) {
            unset($coreFields[$key]);
        }
        return $coreFields;
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
        $filter = [
            'Contributors', 'Format', 'Online Access',
            'Access', 'Item Description FWD', 'Physical Description',
            'Published in', 'Published', 'Source Collection', 'Archive Relations'
        ];
        foreach ($filter as $key) {
            unset($coreFields[$key]);
        }

        $coreFields = $type === 'ead'
            ? $this->filterEADFields($coreFields)
            : $this->filterEAD3Fields($coreFields);

        return $coreFields;
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
