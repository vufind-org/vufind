<?php
/**
 * Record driver data formatting view helper
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2016.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\View\Helper\Root;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use Zend\View\Helper\AbstractHelper;

/**
 * Record driver data formatting view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RecordDataFormatter extends AbstractHelper
{
    /**
     * Create formatted key/value data based on a record driver and field spec.
     *
     * @param RecordDriver $driver Record driver object.
     * @param array        $spec   Formatting specification
     *
     * @return Record
     */
    public function getData(RecordDriver $driver, array $spec)
    {
        $result = [];
        foreach ($spec as $field => $current) {
            // Take the first element of the current spec and obtain the data
            // we need for further rendering.
            $data = $this->extractData($driver, array_shift($current));
            if (!empty($data)) {
                // Determine the rendering method to use with the second element
                // of the current spec.
                $renderType = array_shift($current);
                $renderMethod = empty($renderType)
                    ? 'renderSimple' : 'render' . $renderType;

                // Extract additional options from the third element of the spec:
                $options = array_shift($current) ?: [];

                // Add the rendered data to the return value if it is non-empty:
                if (is_callable([$this, $renderMethod])
                    && $text = $this->$renderMethod($driver, $data, $options)
                ) {
                    $result[$field] = $text;
                }
            }
        }
        return $result;
    }

    /**
     * Get default specifications for displaying data in the description tab.
     *
     * @return array
     */
    public function getDefaultDescriptionSpecs()
    {
        return [
            'Summary' => ['getSummary'],
            'Published' => ['getDateSpan'],
            'Item Description' => ['getGeneralNotes'],
            'Physical Description' => ['getPhysicalDescriptions'],
            'Publication Frequency' => ['getPublicationFrequency'],
            'Playing Time' => ['getPlayingTimes'],
            'Format' => ['getSystemDetails'],
            'Audience' => ['getTargetAudienceNotes'],
            'Awards' => ['getAwards'],
            'Production Credits' => ['getProductionCredits'],
            'Bibliography' => ['getBibliographyNotes'],
            'ISBN' => ['getISBNs'],
            'ISSN' => ['getISSNs'],
            'DOI' => ['getCleanDOI'],
            'Related Items' => ['getRelationshipNotes'],
            'Access' => ['getAccessRestrictions'],
            'Finding Aid' => ['getFindingAids'],
            'Publication_Place' => ['getHierarchicalPlaceNames'],
            'Author Notes' => [
                true, 'RecordDriverTemplate',
                ['template' => 'data-authorNotes.phtml']
            ],
        ];
    }

    /**
     * Extract data (usually from the record driver).
     *
     * @param RecordDriver $driver Record driver
     * @param mixed        $method Configuration for data extraction
     *
     * @return mixed
     */
    protected function extractData(RecordDriver $driver, $method)
    {
        // If $method is a bool, return it as-is; this allows us to force the
        // rendering (or non-rendering) of particular data independent of the
        // record driver.
        if ($method === true || $method === false) {
            return $method;
        }
        // Default action: try to extract data from the record driver:
        return $driver->tryMethod($method);
    }

    /**
     * Render a record driver template.
     *
     * @param RecordDriver $driver  Reoord driver object.
     * @param mixed        $data    Data to render
     * @param array        $options Rendering options.
     *
     * @return string
     */
    protected function renderRecordDriverTemplate(RecordDriver $driver, $data,
        array $options
    ) {
        if (!isset($options['template'])) {
            throw new \Exception('Template option missing.');
        }
        $helper = $this->getView()->plugin('record');
        $context = isset($options['context']) ? $options['context'] : [];
        $context['driver'] = $driver;
        $context['data'] = $data;
        return trim(
            $helper($driver)->renderTemplate($options['template'], $context)
        );
    }

    /**
     * Simple rendering method.
     *
     * @param RecordDriver $driver  Reoord driver object.
     * @param mixed        $data    Data to render
     * @param array        $options Rendering options.
     *
     * @return string
     */
    protected function renderSimple(RecordDriver $driver, $data, array $options)
    {
        $view = $this->getView();
        $escaper = (isset($options['translate']) && $options['translate'])
            ? $view->plugin('transEsc') : $view->plugin('escapeHtml');
        $separator = isset($options['separator'])
            ? $options['separator'] : '<br />';
        $retVal = '';
        $array = (array)$data;
        $remaining = count($data);
        foreach ($array as $line) {
            $remaining--;
            $retVal .= $escaper($line);
            if ($remaining > 0) {
                $retVal .= $separator;
            }
        }
        return $retVal;
    }
}
