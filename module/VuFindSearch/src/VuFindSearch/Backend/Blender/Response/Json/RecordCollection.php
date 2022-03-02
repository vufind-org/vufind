<?php

/**
 * Simple JSON-based record collection.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019.
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

use VuFindSearch\Backend\Solr\Response\Json\Facets;
use VuFindSearch\Response\RecordCollectionInterface;

/**
 * Simple JSON-based record collection.
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
    }

    /**
     * Initialize blended results
     *
     * @param RecordCollectionInterface $primaryCollection   Primary record
     * collection
     * @param RecordCollectionInterface $secondaryCollection Secondary record
     * collection
     * @param int                       $offset              Results list offset
     * @param int                       $limit               Result limit
     * @param int                       $blockSize           Record block size
     *
     * @return void
     */
    public function initBlended(
        RecordCollectionInterface $primaryCollection = null,
        RecordCollectionInterface $secondaryCollection = null,
        $offset,
        $limit,
        $blockSize
    ) {
        $this->response = static::$template;
        $this->response['response']['numFound']
            = ($primaryCollection ? $primaryCollection->getTotal() : 0)
            + ($secondaryCollection ? $secondaryCollection->getTotal() : 0);
        $this->offset = $this->response['response']['start'] = $offset;
        $this->rewind();

        $primaryRecords = $primaryCollection ? $primaryCollection->getRecords() : [];
        $secondaryRecords = $secondaryCollection
            ? $secondaryCollection->getRecords() : [];

        $label = $this->config['Primary']['label'] ?? '';
        foreach ($primaryRecords as &$record) {
            $record->setExtraDetail('blendSource', 'primary');
            $record->setSourceIdentifiers(
                $record->getSourceIdentifier(),
                $primaryCollection->getSourceIdentifier()
            );
            if ($label) {
                $record->addLabel($label, 'source');
            }
        }
        unset($record);

        $label = $this->config['Secondary']['label'] ?? '';
        foreach ($secondaryRecords as &$record) {
            $record->setExtraDetail('blendSource', 'secondary');
            $record->setSourceIdentifiers(
                $record->getSourceIdentifier(),
                $secondaryCollection->getSourceIdentifier()
            );
            if ($label) {
                $record->addLabel($label, 'source');
            }
        }
        unset($record);

        $records = [];
        for ($pos = 0; $pos <= $offset + $limit; $pos++) {
            if ($this->isPrimaryAtOffset($pos, $blockSize) && $primaryRecords) {
                $records[] = array_shift($primaryRecords);
            } elseif ($secondaryRecords) {
                $records[] = array_shift($secondaryRecords);
            }
        }

        $this->records = array_slice(
            $records,
            $offset,
            $limit
        );

        $this->mergeFacets($primaryCollection, $secondaryCollection);

        if (null === $primaryCollection || null === $secondaryCollection) {
            $this->errors = ['search_backend_partial_failure'];
        } else {
            $this->errors = array_merge(
                $primaryCollection->getErrors(),
                $secondaryCollection->getErrors()
            );
            if ($this->errors
                && !($primaryCollection->getErrors()
                && $secondaryCollection->getErrors())
            ) {
                array_unshift($this->errors, 'search_backend_partial_failure');
            }
        }
    }

    /**
     * Calculate if the record at given offset should be from the primary source
     *
     * Note: This does not take into account whether there are enough records in the
     * source.
     *
     * @param int $offset    Offset
     * @param int $blockSize Record block size
     *
     * @return int
     */
    public function isPrimaryAtOffset($offset, $blockSize)
    {
        // Account for configuration being 1-based
        $boostPos = ($this->config['Blending']['boostPosition'] ?? $blockSize) - 1;
        $boostCount = $this->config['Blending']['boostCount'] ?? 0;
        $maxBoostedPos = $boostPos + $boostCount;
        $maxAffectedPos = ceil($maxBoostedPos / $blockSize) * $blockSize
            + $boostCount - 1;
        if ($offset < $boostPos
            || 0 === $boostCount
            || $offset > $maxAffectedPos
            || $maxBoostedPos > $blockSize
        ) {
            // We're outside the blocks affected by boosting, calculate by block
            $currentBlock = floor($offset / $blockSize);
            return $currentBlock % 2 === 0;
        }

        // Check if we're in a boost block
        if ($boostCount > 0
            && $offset >= $boostPos && $offset < $boostPos + $boostCount
        ) {
            return false;
        }
        // Check if we're in the first primary block
        if ($offset < $blockSize + $boostCount) {
            return true;
        }
        return false;
    }

    /**
     * Return any errors.
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
     * Merge facets
     *
     * @param RecordCollectionInterface $primaryCollection   Primary record
     * collection
     * @param RecordCollectionInterface $secondaryCollection Secondary record
     * collection
     *
     * @return void
     */
    protected function mergeFacets(
        $primaryCollection = null,
        $secondaryCollection = null
    ) {
        $facets = $primaryCollection ? $primaryCollection->getFacets() : [];
        if ($facets instanceof Facets) {
            $facets = $facets->getFieldFacets();
        }
        $secondary = $secondaryCollection ? $secondaryCollection->getFacets() : [];
        if ($secondary instanceof \VuFindSearch\Backend\Solr\Response\Json\Facets) {
            $secondary = $secondary->getFieldFacets();
        }
        foreach ($facets as $facet => &$values) {
            if (is_object($values)) {
                $values = $values->toArray();
            }
        }
        unset($values);

        // Iterate through mappings and merge secondary values.
        // It is vital to do it this way since multiple facets may map to a secondary
        // facet in checkbox facets.
        foreach ($this->mappings['Facets'] as $facet => $settings) {
            $secondaryFacet = $settings['Secondary'];
            $mappings = $settings['Values'] ?? [];
            $facetType = $settings['Type'] ?? '';

            $values = $secondary[$secondaryFacet] ?? [];
            if (is_object($values)) {
                $values = $values->toArray();
            }
            if (empty($values)) {
                continue;
            }

            $list = $facets[$facet] ?? [];
            foreach ($values as $field => $count) {
                if (isset($mappings[$field])) {
                    $field = $mappings[$field];
                    if ('boolean' === $facetType) {
                        $field = $field ? 'true' : 'false';
                    }
                } elseif ('hierarchical' === $facetType) {
                    $field = "0/$field/";
                } elseif ('boolean' === $facetType) {
                    // No mapping for boolean facet, ignore the value
                    continue;
                }
                if (isset($list[$field])) {
                    $list[$field] += $count;
                } else {
                    $list[$field] = $count;
                }

                if ('hierarchical' === $facetType) {
                    $parts = explode('/', $field);
                    $level = array_shift($parts);
                    for ($i = $level - 1; $i >= 0; $i--) {
                        $key = $i . '/'
                            . implode('/', array_slice($parts, 0, $i + 1))
                            . '/';
                        if (isset($list[$key])) {
                            $list[$key] += $count;
                        } else {
                            $list[$key] = $count;
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

            $facets[$facet] = $list;
        }

        $facets['blender_backend'] = [
            'primary' => $primaryCollection ? $primaryCollection->getTotal() : 0,
            'secondary' => $secondaryCollection
                ? $secondaryCollection->getTotal() : 0
        ];

        // Break the keyed array back to Solr-style array with two elements
        $facetFields = [];
        foreach ($facets as $facet => $values) {
            $list = [];
            foreach ($values as $key => $value) {
                $list[] = [$key, $value];
            }
            $facetFields[$facet] = $list;
        }

        $this->response['facet_counts']['facet_fields'] = $facetFields;
    }
}
