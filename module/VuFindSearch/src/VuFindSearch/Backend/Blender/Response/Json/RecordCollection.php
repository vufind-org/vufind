<?php

/**
 * JSON-based record collection for records from multiple sources.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */

namespace VuFindSearch\Backend\Blender\Response\Json;

use VuFindSearch\Response\RecordInterface;

use function array_slice;
use function count;
use function in_array;
use function intval;
use function is_array;
use function is_string;

/**
 * JSON-based record collection for records from multiple sources.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class RecordCollection extends \VuFindSearch\Backend\Solr\Response\Json\RecordCollection
{
    /**
     * Blender configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Mappings configuration
     *
     * @var array
     */
    protected $mappings;

    /**
     * Backends to be used for initial results
     *
     * @var array
     */
    protected $initialResultsBackends;

    /**
     * Any errors encountered
     *
     * @var array
     */
    protected $errors = [];

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $config   Configuration
     * @param array                  $mappings Mappings configuration
     */
    public function __construct($config = null, $mappings = [])
    {
        $this->config = $config;
        $this->mappings = $mappings;
        $this->response = static::$template;
        $this->initialResultsBackends
            = isset($this->config->Blending->initialResults)
            ? $this->config->Blending->initialResults->toArray()
            : [];
    }

    /**
     * Initialize blended results
     *
     * Creates a record list from 0 to $limit
     *
     * @param array $collections Array of record collections
     * @param int   $limit       Result limit
     * @param int   $blockSize   Blending block size
     * @param int   $totalCount  Total result count
     *
     * @return array Remaining records keyed by backend identifier
     */
    public function initBlended(
        array $collections,
        int $limit,
        int $blockSize,
        int $totalCount
    ): array {
        $this->response = static::$template;
        $this->response['response']['numFound'] = $totalCount;
        $this->rewind();

        if (!$collections) {
            return [];
        }

        $backendRecords = $this->collectBackendRecords($collections);
        $this->addErrorsFromBackends($collections);

        $backendIds = array_keys($backendRecords);
        // Filter out unavailable backends from initial results source list:
        $initialResultsBackends
            = array_intersect($this->initialResultsBackends, $backendIds);
        // Fill the initial results up to limit with records from correct backends
        // (no need to care about missing ones as the list will be filled later on in
        // Backend):
        for ($pos = 0; $pos < $limit; $pos++) {
            $backendId = $this->getBackendAtPosition(
                $pos,
                $blockSize,
                $backendIds,
                $initialResultsBackends
            );
            if (!empty($backendRecords[$backendId])) {
                $this->add(array_shift($backendRecords[$backendId]), false);
            }
        }

        $this->response['facet_counts']['facet_fields']
            = $this->getMergedFacets($collections);

        return $backendRecords;
    }

    /**
     * Add an error message
     *
     * @param mixed $error Error
     *
     * @return void
     */
    public function addError($error): void
    {
        if (!in_array($error, $this->errors)) {
            $this->errors[] = $error;
        }
    }

    /**
     * Return any errors.
     *
     * Each error can be a translatable string or an array that the Flashmessages
     * view helper understands.
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Set the source backend identifier.
     *
     * @param string $identifier Backend identifier
     *
     * @return void
     */
    public function setSourceIdentifier($identifier)
    {
        $this->source = $identifier;
        // Don't touch the records here to keep their original source identifiers
        // intact. We'll handle their search backend identifiers in
        // collectBackendRecords below.
    }

    /**
     * Get delimiter for the given facet field
     *
     * @param string $field Facet field
     *
     * @return string
     */
    public function getFacetDelimiter(string $field): string
    {
        $delimitedFacets = $this->config->Advanced_Settings->delimited_facets ?? [];
        foreach ($delimitedFacets as $current) {
            $parts = explode('|', $current);
            if ($parts[0] === $field) {
                return $parts[1] ?? $this->config->Advanced_Settings->delimiter
                    ?? '';
            }
        }
        return '';
    }

    /**
     * Collect records from all backends to an associative array
     *
     * @param array $collections Array of record collections
     *
     * @return array
     */
    protected function collectBackendRecords(array $collections): array
    {
        $result = [];
        foreach ($collections as $backendId => $collection) {
            $result[$backendId] = [];
            $records = $collection->getRecords();
            foreach ($records as $record) {
                $record->setSourceIdentifiers(
                    $record->getSourceIdentifier(),
                    $backendId
                );
                $result[$backendId][] = $record;
            }
        }
        return $result;
    }

    /**
     * Add a record to the collection.
     *
     * @param RecordInterface $record        Record to add
     * @param bool            $checkExisting Whether to check for existing record in
     * the collection (slower, but makes sure there are no duplicates)
     *
     * @return void
     */
    public function add(RecordInterface $record, $checkExisting = true)
    {
        $label = $this->config->Backends[$record->getSearchBackendIdentifier()]
            ?? '';
        if ($label) {
            $record->addLabel($label, 'source');
        }
        parent::add($record, $checkExisting);
    }

    /**
     * Store errors from all backends
     *
     * @param array $collections Array of record collections
     *
     * @return void
     */
    protected function addErrorsFromBackends(array $collections): void
    {
        foreach ($collections as $backendId => $collection) {
            foreach ($collection->getErrors() as $error) {
                $label = $this->config->Backends[$backendId];
                if (is_string($error) && $label) {
                    $error = [
                        'msg' => '%%error%% -- %%label%%',
                        'tokens' => [
                            '%%error%%' => $error,
                            '%%label%%' => $label,
                        ],
                        'translate' => true,
                        'translateTokens' => true,
                    ];
                }
                $this->addError($error);
            }
        }
    }

    /**
     * Calculate the backend to be used for a record at the given position
     *
     * Note: This does not take into account whether there are enough records in the
     * source.
     *
     * @param int   $position               Position
     * @param int   $blockSize              Record block size
     * @param array $backendIds             Available backends
     * @param array $initialResultsBackends List of backends for initial result
     * boosts
     *
     * @return string
     */
    protected function getBackendAtPosition(
        int $position,
        int $blockSize,
        array $backendIds,
        array $initialResultsBackends
    ): string {
        if ($boostBackend = $initialResultsBackends[$position] ?? false) {
            return $boostBackend;
        }

        // We're outside the blocks affected by boosting, calculate by block
        $currentBlock = floor($position / $blockSize);
        $backendCount = count($backendIds);
        return $backendCount ? $backendIds[$currentBlock % $backendCount] : '';
    }

    /**
     * Merge facets
     *
     * @param array $collections Result collections
     *
     * @return array
     */
    protected function getMergedFacets(array $collections): array
    {
        $mergedFacets = [];

        // Iterate through mappings and merge values. It is important to do it this
        // way since multiple facets may map to a single one.
        $facetFieldData = $this->mappings['Facets']['Fields'] ?? [];
        foreach ($facetFieldData as $facetField => $settings) {
            // Get merged list of facet values:
            $list = $this->mapFacetValues($collections, $settings);
            // Re-sort the list:
            // TODO: Could we support alphabetical order?
            uasort(
                $list,
                function ($a, $b) {
                    return $b - $a;
                }
            );
            $mergedFacets[$facetField] = $list;
        }

        $mergedFacets['blender_backend'] = $this->getBlenderFacetStats($collections);

        // Convert the array back to Solr-style array with two elements
        $facetFields = [];
        foreach ($mergedFacets as $facet => $values) {
            $list = [];
            foreach ($values as $key => $value) {
                $list[] = [$key, $value];
            }
            $facetFields[$facet] = $list;
        }

        return $facetFields;
    }

    /**
     * Map facet values from the backends into a merged list
     *
     * @param array $collections Result collections
     * @param array $settings    Settings for a single facet field
     *
     * @return array
     */
    protected function mapFacetValues(array $collections, array $settings): array
    {
        $result = [];
        foreach ($collections as $backendId => $collection) {
            $facets = $collection->getFacets();
            $facetType = $settings['Type'] ?? 'normal';
            $mappings = $settings['Mappings'][$backendId] ?? [];
            $backendFacetField = $mappings['Field'] ?? '';
            if (!$mappings || !$backendFacetField) {
                continue;
            }
            $valueMap = $mappings['Values'] ?? [];
            $unmappedRule = $mappings['Unmapped'] ?? 'keep';
            $hierarchical = $mappings['Hierarchical'] ?? false;
            foreach ($facets[$backendFacetField] ?? [] as $value => $count) {
                $value = $this->convertFacetValue(
                    $value,
                    $facetType,
                    $unmappedRule,
                    $valueMap,
                    $hierarchical
                );
                if ('' === $value) {
                    continue;
                }

                $result[$value] = ($result[$value] ?? 0) + intval($count);
                if ($hierarchical) {
                    foreach ($this->getHierarchyParentKeys($value) as $key) {
                        $result[$key] = ($result[$key] ?? 0) + intval($count);
                    }
                }
            }
        }

        foreach ($settings['Mappings'] as $backendId => $mappings) {
            $ignore = $mappings['Ignore'] ?? false;
            if ($ignore && ($collections[$backendId] ?? false)) {
                $ignoredKeys = is_array($ignore) ? $ignore : array_keys($result);
                foreach ($ignoredKeys as $ignoredValue) {
                    $result[$ignoredValue] = ($result[$ignoredValue] ?? 0)
                        + $collections[$backendId]->getTotal();
                }
            }
        }

        return $result;
    }

    /**
     * Get parent hierarchy keys for a facet value
     *
     * For example with '2/Main/Sub/Shelf/' the result is:
     * [
     *   '1/Main/Sub/',
     *   '0/Main/'
     * ]
     *
     * @param string $value Hierarchical facet value
     *
     * @return array
     */
    protected function getHierarchyParentKeys(string $value): array
    {
        $parts = explode('/', $value);
        $level = array_shift($parts);
        $result = [];
        for ($i = intval($level) - 1; $i >= 0; $i--) {
            $result[] = $i . '/' . implode('/', array_slice($parts, 0, $i + 1))
                . '/';
        }
        return $result;
    }

    /**
     * Get facet counts for Blender backend facet
     *
     * @param array $collections Collections
     *
     * @return array
     */
    protected function getBlenderFacetStats(array $collections): array
    {
        $delimiter = $this->getFacetDelimiter('blender_backend');
        $orFacets = $this->config->Results_Settings->orFacets ?? '';
        $orFacetList = array_map('trim', explode(',', $orFacets));
        $isOrFacet = '*' === $orFacets || in_array('blender_backend', $orFacetList);
        $result = [];
        foreach ($this->config->Backends as $backendId => $name) {
            $key = $delimiter ? ($backendId . $delimiter . $name) : $backendId;
            if (isset($collections[$backendId])) {
                if ($total = $collections[$backendId]->getTotal()) {
                    $result[$key] = $total;
                }
            } elseif ($isOrFacet) {
                $result[$key] = null;
            }
        }
        return $result;
    }

    /**
     * Convert a facet value from a backend
     *
     * @param string $value        Facet value
     * @param string $type         Facet type
     * @param string $unmapped     Unmapped facet handling rule
     * @param array  $valueMap     Value map for the field
     * @param bool   $hierarchical Whether the facet is hierarchical
     *
     * @return string
     */
    protected function convertFacetValue(
        string $value,
        string $type,
        string $unmapped,
        array $valueMap,
        bool $hierarchical
    ): string {
        if (isset($valueMap[$value])) {
            $value = $valueMap[$value];
            if ('boolean' === $type) {
                $value = $value ? 'true' : 'false';
            }
        } elseif ('boolean' === $type || 'drop' === $unmapped) {
            // No mapping defined for boolean facet or "drop" as the Unmapped rule;
            // ignore the value:
            return '';
        }
        if ($hierarchical && !preg_match('/^\d+\/.+\/$/', $value)) {
            $value = "0/$value/";
        }

        return $value;
    }
}
