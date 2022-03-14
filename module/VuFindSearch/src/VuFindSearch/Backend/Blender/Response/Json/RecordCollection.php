<?php

/**
 * JSON-based record collection for records from multiple sources.
 *
 * PHP version 7
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
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindSearch\Backend\Blender\Response\Json;

use VuFindSearch\Backend\EDS\Response\RecordCollection as EDSRecordCollection;
use VuFindSearch\Backend\Solr\Response\Json\Facets;
use VuFindSearch\Response\RecordCollectionInterface;

/**
 * JSON-based record collection for records from multiple sources.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class RecordCollection
    extends \VuFindSearch\Backend\Solr\Response\Json\RecordCollection
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
    protected $initialResults;

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
        $this->initialResults = isset($this->config->Blending->initialResults)
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

        $backendRecords = [];
        foreach ($collections as $backendId => $collection) {
            $records = $collection->getRecords();
            $label = $this->config->Backends[$backendId];
            foreach ($records as $record) {
                $record->setSourceIdentifiers(
                    $record->getSourceIdentifier(),
                    $backendId
                );
                if ($label) {
                    $record->addLabel($label, 'source');
                }
                $backendRecords[$backendId][] = $record;
            }

            foreach ($collection->getErrors() as $error) {
                if (is_string($error)) {
                    $error = [
                        'message' => $error,
                        'details' => $label
                    ];
                }
                $this->addError($error);
            }
        }

        $records = [];
        $backendIds = array_keys($backendRecords);
        // Filter out unavailable backends from initial results source list:
        $initialResults = array_values(
            array_filter(
                $this->initialResults,
                function ($s) use ($backendIds) {
                    return in_array($s, $backendIds);
                }
            )
        );
        for ($pos = 0; $pos < $limit; $pos++) {
            $backendId = $this->getBackendAtPosition(
                $pos,
                $blockSize,
                $backendIds,
                $initialResults
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
     * Slice the record collection
     *
     * @param int $offset Offset
     * @param int $limit  Limit
     *
     * @return void
     */
    public function slice(int $offset, int $limit): void
    {
        $this->records = array_slice(
            $this->records,
            $offset,
            $limit
        );
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
     * Each error can be a translatable string or an array with keys 'message' and
     * 'additional', both translatable strings.
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
    }

    /**
     * Calculate the backend to be used for the record at a given position
     *
     * Note: This does not take into account whether there are enough records in the
     * source.
     *
     * @param int   $position       Position
     * @param int   $blockSize      Record block size
     * @param array $backendIds     Available backends
     * @param array $initialResults List of backends for initial result boosts
     *
     * @return string
     */
    protected function getBackendAtPosition(
        int $position,
        int $blockSize,
        array $backendIds,
        array $initialResults
    ): string {
        if ($boostBackend = $initialResults[$position] ?? false) {
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
        // way since multiple facets may to a single one.
        foreach ($this->mappings['Facets']['Fields'] ?? []
            as $facetField => $settings
        ) {
            $list = [];
            foreach ($collections as $backendId => $collection) {
                $facets = $this->convertFacets($collection);

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

                    if (isset($list[$value])) {
                        $list[$value] += $count;
                    } else {
                        $list[$value] = intval($count);
                    }

                    if ($hierarchical) {
                        $parts = explode('/', $value);
                        $level = array_shift($parts);
                        for ($i = $level - 1; $i >= 0; $i--) {
                            $key = $i . '/'
                                . implode('/', array_slice($parts, 0, $i + 1))
                                . '/';
                            if (isset($list[$key])) {
                                $list[$key] += $count;
                            } else {
                                $list[$key] = intval($count);
                            }
                        }
                    }
                }
            }

            // Re-sort the list
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
     * Get facet counts for Blender backend facet
     *
     * @param array $collections Collections
     *
     * @return array
     */
    protected function getBlenderFacetStats(array $collections): array
    {
        $result = [];
        foreach (array_keys($this->config->Backends->toArray()) as $backendId) {
            $result[$backendId]
                = isset($collections[$backendId])
                ? $collections[$backendId]->getTotal() : 0;
        }
        return $result;
    }

    /**
     * Convert facets into an associative array format for processing
     *
     * @param RecordCollectionInterface $collection Collection
     *
     * @return array
     */
    protected function convertFacets(RecordCollectionInterface $collection)
    {
        $facets = $collection->getFacets();
        if ($facets instanceof Facets) {
            return $facets->getFieldFacets();
        }
        if ($collection instanceof EDSRecordCollection) {
            // Convert EDS facets:
            $converted = [];
            foreach ($facets as $field => $facetData) {
                $valueCounts = [];
                foreach ($facetData['counts'] as $count) {
                    $valueCounts[$count['displayText']] = $count['count'];
                }
                $converted[$field] = $valueCounts;
            }
            return $converted;
        }

        if (!is_array($facets)) {
            throw new \Exception(
                'Unhandled facet format for ' . get_class($collection)
            );
        }

        return $facets;
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
