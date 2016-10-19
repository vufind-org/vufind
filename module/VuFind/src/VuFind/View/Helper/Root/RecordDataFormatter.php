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
            // Extract the three key components of the spec: data retrieval
            // method, rendering type, and additional options array.
            $dataMethod = array_shift($current);
            $renderType = array_shift($current);
            $options = array_shift($current) ?: [];

            // Extract the relevant data from the driver.
            $data = $this->extractData($driver, $dataMethod, $options);
            if (!empty($data)) {
                // Determine the rendering method to use with the second element
                // of the current spec.
                $renderMethod = empty($renderType)
                    ? 'renderSimple' : 'render' . $renderType;

                // Add the rendered data to the return value if it is non-empty:
                if (is_callable([$this, $renderMethod])
                    && $text = $this->$renderMethod($driver, $data, $options)
                ) {
                    // Allow dynamic label override:
                    if (isset($options['labelFunction'])
                        && is_callable($options['labelFunction'])
                    ) {
                        $field = call_user_func($options['labelFunction'], $data);
                    }
                    $result[$field] = $text;
                }
            }
        }
        return $result;
    }

    /**
     * Get default specifications for displaying data in core metadata.
     *
     * @return array
     */
    public function getDefaultCoreSpecs()
    {
        return [
            'Published in' => [
                'getContainerTitle', 'RecordDriverTemplate',
                ['template' => 'data-containerTitle.phtml']
            ],
            'New Title' => ['getNewerTitles', null, ['recordLink' => 'title']],
            'Previous Title' => [
                'getPreviousTitles', null, ['recordLink' => 'title']
            ],
            'Main Authors' => [
                'getDeduplicatedAuthors', 'RecordDriverTemplate',
                [
                    'useCache' => true,
                    'labelFunction' => function ($data) {
                        return count($data['main']) > 1
                            ? 'Main Authors' : 'Main Author';
                    },
                    'template' => 'data-authors.phtml',
                    'context' => ['type' => 'main', 'schemaLabel' => 'author'],
                ]
            ],
            'Corporate Authors' => [
                'getDeduplicatedAuthors', 'RecordDriverTemplate',
                [
                    'useCache' => true,
                    'labelFunction' => function ($data) {
                        return count($data['corporate']) > 1
                            ? 'Corporate Authors' : 'Corporate Author';
                    },
                    'template' => 'data-authors.phtml',
                    'context' => ['type' => 'corporate', 'schemaLabel' => 'creator'],
                ]
            ],
            'Other Authors' => [
                'getDeduplicatedAuthors', 'RecordDriverTemplate',
                [
                    'useCache' => true,
                    'template' => 'data-authors.phtml',
                    'context' => [
                        'type' => 'secondary', 'schemaLabel' => 'contributor'
                    ],
                ]
            ],
            'Format' => [
                'getFormats', 'RecordHelper', ['method' => 'getFormatList']
            ],
            'Language' => ['getLanguages'],
            'Published' => [
                'getPublicationDetails', 'RecordDriverTemplate',
                ['template' => 'data-publicationDetails.phtml']
            ],
            'Edition' => [
                'getEdition', null,
                ['prefix' => '<span property="bookEdition">', 'suffix' => '</span>']
            ],
            'Series' => [
                'getSeries', 'RecordDriverTemplate',
                ['template' => 'data-series.phtml']
            ],
            'Subjects' => [
                'getAllSubjectHeadings', 'RecordDriverTemplate',
                ['template' => 'data-allSubjectHeadings.phtml']
            ],
            'child_records' => [
                'getChildRecordCount', 'RecordDriverTemplate',
                ['template' => 'data-childRecords.phtml']
            ],
            'Online Access' => [
                true, 'RecordDriverTemplate',
                ['template' => 'data-onlineAccess.phtml']
            ],
            'Related Items' => [
                'getAllRecordLinks', 'RecordDriverTemplate',
                ['template' => 'data-allRecordLinks.phtml']
            ],
            'Tags' => [
                true, 'RecordDriverTemplate', ['template' => 'data-tags.phtml']
            ],
        ];
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
     * @param RecordDriver $driver  Record driver
     * @param mixed        $method  Configuration for data extraction
     * @param array        $options Incoming options
     *
     * @return mixed
     */
    protected function extractData(RecordDriver $driver, $method, array $options)
    {
        // Static cache for persisting data.
        static $cache = [];

        // If $method is a bool, return it as-is; this allows us to force the
        // rendering (or non-rendering) of particular data independent of the
        // record driver.
        if ($method === true || $method === false) {
            return $method;
        }

        $useCache = isset($options['cacheData']) && $options['cacheData'];

        if ($useCache) {
            $cacheKey = $driver->getUniqueID() . '|'
                . $driver->getSourceIdentifier() . '|' . $method;
            if (isset($cache[$cacheKey])) {
                return $cache[$cacheKey];
            }
        }

        // Default action: try to extract data from the record driver:
        $data = $driver->tryMethod($method);

        if ($useCache) {
            $cache[$cacheKey] = $data;
        }

        return $data;
    }

    /**
     * Render using the record view helper.
     *
     * @param RecordDriver $driver  Reoord driver object.
     * @param mixed        $data    Data to render
     * @param array        $options Rendering options.
     *
     * @return string
     */
    protected function renderRecordHelper(RecordDriver $driver, $data,
        array $options
    ) {
        $method = isset($options['method']) ? $options['method'] : null;
        $plugin = $this->getView()->plugin('record');
        if (empty($method) || !is_callable([$plugin, $method])) {
            throw new \Exception('Cannot call "' . $method . '" on helper.');
        }
        return $plugin($driver)->$method($data);
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
     * Get a link associated with a value, or else return false if link does
     * not apply.
     *
     * @param string $value   Value associated with link.
     * @param array  $options Rendering options.
     *
     * @return string|bool
     */
    protected function getLink($value, $options)
    {
        if (isset($options['recordLink']) && $options['recordLink']) {
            $helper = $this->getView()->plugin('record');
            return $helper->getLink($options['recordLink'], $value);
        }
        return false;
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
            $text = $escaper($line);
            $retVal .= ($link = $this->getLink($line, $options))
                ? '<a href="' . $link . '">' . $text . '</a>' : $text;
            if ($remaining > 0) {
                $retVal .= $separator;
            }
        }
        return (isset($options['prefix']) ? $options['prefix'] : '')
            . $retVal
            . (isset($options['suffix']) ? $options['suffix'] : '');
    }
}
