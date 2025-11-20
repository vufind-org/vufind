<?php

/**
 * Facet-driven channel provider.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ChannelProvider;

use Laminas\Mvc\Controller\Plugin\Url;
use VuFind\Http\PhpEnvironment\Request as HttpRequest;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\Search\Base\Params;
use VuFind\Search\Base\Results;
use VuFind\Search\Results\PluginManager as ResultsManager;

use function count;
use function intval;

/**
 * Facet-driven channel provider.
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Facets extends AbstractChannelProvider implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use BatchTrait;

    /**
     * Facet fields to use (field name => description).
     *
     * @var array
     */
    protected $fields;

    /**
     * Maximum number of different fields to suggest in the channel list.
     *
     * @var int
     */
    protected $maxFieldsToSuggest;

    /**
     * Maximum number of values to suggest per field.
     *
     * @var int
     */
    protected $maxValuesToSuggestPerField;

    /**
     * Page of results to retrieve
     *
     * @var int
     */
    protected $page = 1;

    /**
     * Page size for retrieved results
     *
     * @var int
     */
    protected $limit;

    /**
     * Constructor
     *
     * @param ResultsManager $resultsManager Results manager
     * @param Url            $url            URL helper
     * @param array          $options        Settings (optional)
     */
    public function __construct(
        protected ResultsManager $resultsManager,
        protected Url $url,
        array $options = []
    ) {
        $this->setOptions($options);
        $this->limit = $this->batchSize;
    }

    /**
     * Set the options for the provider.
     *
     * @param array $options Options
     *
     * @return void
     */
    public function setOptions(array $options)
    {
        $this->fields = $options['fields'] ?? ['topic_facet' => 'Topic', 'author_facet' => 'Author'];
        $this->maxFieldsToSuggest = $options['maxFieldsToSuggest'] ?? 2;
        $this->maxValuesToSuggestPerField = $options['maxValuesToSuggestPerField'] ?? 2;
        $this->setBatchSizeFromOptions($options);
    }

    /**
     * Hook to configure search parameters before executing search.
     *
     * @param Params      $params  Search parameters to adjust
     * @param HttpRequest $request Current HTTP request
     *
     * @return void
     */
    public function configureSearchParams(Params $params, HttpRequest $request): void
    {
        foreach ($this->fields as $field => $desc) {
            $params->addFacet($field, $desc);
        }

        // Add pagination params
        $this->page = intval($request->getQuery('page', 1));
        $this->limit = intval($request->getQuery('limit', $this->batchSize));
        $params->setPage($this->page);
        if ($this->limit) {
            $params->setLimit(min($this->limit, $this->maxBatchSize));
        }
    }

    /**
     * Return channel information derived from a record driver object.
     *
     * @param RecordDriver $driver       Record driver
     * @param string       $channelToken Token identifying a single specific channel
     * to load (if omitted, all channels will be loaded)
     *
     * @return array
     */
    public function getFromRecord(RecordDriver $driver, $channelToken = null)
    {
        $results = $this->resultsManager->get($driver->getSourceIdentifier());
        if (null !== $channelToken) {
            return [$this->buildChannelFromToken($results, $channelToken)];
        }
        $channels = [];
        $fieldCount = 0;
        $data = $driver->getRawData();
        foreach (array_keys($this->fields) as $field) {
            if (!isset($data[$field])) {
                continue;
            }
            $currentValueCount = 0;
            foreach (array_unique($data[$field]) as $value) {
                $current = [
                    'value' => $value,
                    'displayText' => $value,
                ];
                $tokenOnly = $fieldCount >= $this->maxFieldsToSuggest
                    || $currentValueCount >= $this->maxValuesToSuggestPerField;
                $channel = $this->buildChannelFromFacet($results, $field, $current, $tokenOnly);
                if ($tokenOnly || count($channel['contents']) > 0) {
                    $channels[] = $channel;
                    $currentValueCount++;
                }
            }
            if ($currentValueCount > 0) {
                $fieldCount++;
            }
        }
        return $channels;
    }

    /**
     * Return channel information derived from a search results object.
     *
     * @param Results $results      Search results
     * @param string  $channelToken Token identifying a single specific channel
     * to load (if omitted, all channels will be loaded)
     *
     * @return array
     */
    public function getFromSearch(Results $results, $channelToken = null)
    {
        if (null !== $channelToken) {
            return [$this->buildChannelFromToken($results, $channelToken)];
        }
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
                    $tokenOnly = $fieldCount >= $this->maxFieldsToSuggest
                        || $currentValueCount >= $this->maxValuesToSuggestPerField;
                    $channel = $this->buildChannelFromFacet(
                        $results,
                        $field,
                        $current,
                        $tokenOnly
                    );
                    if ($tokenOnly || count($channel['contents']) > 0) {
                        $channels[] = $channel;
                        $currentValueCount++;
                    }
                }
            }
            if ($currentValueCount > 0) {
                $fieldCount++;
            }
        }
        return $channels;
    }

    /**
     * Turn a filter and title into a token.
     *
     * @param string $filter Filter to apply to Solr
     * @param string $title  Channel title
     *
     * @return string
     */
    protected function getToken($filter, $title)
    {
        return str_replace('|', ' ', $title)    // make sure delimiter not in title
            . '|' . $filter;
    }

    /**
     * Add a new filter to an existing search results object to populate a
     * channel.
     *
     * @param Results $results   Results object
     * @param string  $filter    Filter to apply to Solr
     * @param string  $title     Channel title
     * @param bool    $tokenOnly Create full channel (false) or return a
     * token for future loading (true)?
     *
     * @return array
     */
    protected function buildChannel(
        Results $results,
        $filter,
        $title,
        $tokenOnly = false
    ) {
        $retVal = [
            'title' => $title,
            'providerId' => $this->providerId,
            'groupId' => current(explode(':', $filter)),
            'token' => $this->getToken($filter, $title),
            'links' => [],
        ];
        if ($tokenOnly) {
            return $retVal;
        }

        $newResults = clone $results;
        $params = $newResults->getParams();

        // Determine the filter for the current channel, and add it:
        $params->addFilter($filter);

        $query = $newResults->getUrlQuery()->getParams(false);
        $retVal['links'][] = [
            'label' => 'channel_search',
            'icon' => 'search',
            'url' => $this->url->fromRoute($params->getOptions()->getSearchAction())
                . $query,
        ];
        $retVal['links'][] = [
            'label' => 'channel_expand',
            'icon' => 'ui-add',
            'url' => $this->url->fromRoute('channels-search')
                . $query . '&source=' . urlencode($params->getSearchClassId()),
        ];

        // Add pagination
        $pagedParams = $newResults->getParams();
        $pagedParams->setPage($this->page);
        if ($this->limit) {
            $pagedParams->setLimit(min($this->limit, $this->maxBatchSize));
        }
        $newResults->setParams($pagedParams);

        // Run the search and convert the results into a channel:
        $newResults->performAndProcessSearch();
        $retVal['contents'] = $this->summarizeRecordDrivers($newResults->getResults());
        $retVal['resultTotal'] = $newResults->getResultTotal();
        return $retVal;
    }

    /**
     * Call buildChannel using data from a token.
     *
     * @param Results $results Results object
     * @param string  $token   Token to parse
     *
     * @return array
     */
    protected function buildChannelFromToken(Results $results, $token)
    {
        $parts = explode('|', $token, 2);
        if (count($parts) < 2) {
            return [];
        }
        return $this->buildChannel($results, $parts[1], $parts[0]);
    }

    /**
     * Call buildChannel using data from facet results.
     *
     * @param Results $results   Results object
     * @param string  $field     Field name (for filter)
     * @param array   $value     Field value information (for filter)
     * @param bool    $tokenOnly Create full channel (false) or return a
     * token for future loading (true)?
     *
     * @return array
     */
    protected function buildChannelFromFacet(
        Results $results,
        $field,
        $value,
        $tokenOnly = false
    ) {
        return $this->buildChannel(
            $results,
            "$field:{$value['value']}",
            $this->translate($this->fields[$field]) . ": {$value['displayText']}",
            $tokenOnly
        );
    }
}
