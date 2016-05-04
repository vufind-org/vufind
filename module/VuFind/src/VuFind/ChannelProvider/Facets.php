<?php
/**
 * Facet-driven channel provider.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\ChannelProvider;
use VuFind\Search\Base\Params, VuFind\Search\Base\Results;

/**
 * Facet-driven channel provider.
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Facets
{
    /**
     * Facet fields to use (field name => description).
     *
     * @var array
     */
    protected $fields = [
        'topic_facet' => 'Topic',
        'author_facet' => 'Author',
    ];

    /**
     * Hook to configure search parameters before executing search.
     *
     * @param Params $params Search parameters to adjust
     *
     * @return void
     */
    public function configureSearchParams(Params $params)
    {
        foreach ($this->fields as $field => $desc) {
            $params->addFacet($field, $desc);
        }
    }

    /**
     * Return channel information derived from a search results object.
     *
     * @param Results $results Search results
     *
     * @return array
     */
    public function getFromSearch(Results $results)
    {
        $maxFieldsToSuggest = 2;
        $maxValuesToSuggestPerField = 2;

        $channels = [];
        $fieldCount = 0;
        $facetList = $results->getFacetList();
        foreach (array_keys($this->fields) as $field) {
            if (!isset($facetList[$field])) {
                continue;
            }
            $currentValueCount = 0;
            foreach ($facetList[$field]['list'] as $current) {
                if (!$current['isApplied']) {
                    $channel = $this
                        ->buildChannelFromFacet($results, $field, $current);
                    if (count($channel['contents']) > 0) {
                        $channels[] = $channel;
                        $currentValueCount++;
                    }
                }
                if ($currentValueCount >= $maxValuesToSuggestPerField) {
                    break;
                }
            }
            if ($currentValueCount >= $maxValuesToSuggestPerField) {
                $fieldCount++;
            }
            if ($fieldCount >= $maxFieldsToSuggest) {
                break;
            }
        }
        return $channels;
    }

    /**
     * Convert a search results object into channel contents.
     *
     * @param Results $results Results object
     *
     * @return array
     */
    protected function summarizeResults(Results $results)
    {
        $summary = [];
        foreach ($results->getResults() as $current) {
            $summary[] = [
                'title' => $current->getTitle(),
                'source' => $current->getSourceIdentifier(),
                'thumbnail' => $current->getThumbnail('medium'),
                'id' => $current->getUniqueId(),
            ];
        }
        return $summary;
    }

    /**
     * Add a new filter to an existing search results object to populate a
     * channel.
     *
     * @param Results $results Results object
     * @param string  $field   Field name (for filter)
     * @param array   $value   Field value information (for filter)
     *
     * @return array
     */
    protected function buildChannelFromFacet(Results $results, $field, $value)
    {
        $newResults = clone($results);
        $params = $newResults->getParams();

        // Determine the filter for the current channel, and add it:
        $filter = "$field:{$value['value']}";
        $params->addFilter($filter);

        // Run the search and convert the results into a channel:
        $newResults->performAndProcessSearch();
        return [
            'title' => "{$this->fields[$field]}: {$value['displayText']}",
            'contents' => $this->summarizeResults($newResults)
        ];
    }
}
