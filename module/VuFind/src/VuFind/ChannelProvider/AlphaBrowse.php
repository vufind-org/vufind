<?php

/**
 * Alphabrowse channel provider.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016, 2022.
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
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ChannelProvider;

use Laminas\Mvc\Controller\Plugin\Url;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Record\Router as RecordRouter;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\Search\Base\Results;
use VuFindSearch\Command\AlphabeticBrowseCommand;
use VuFindSearch\Command\RetrieveBatchCommand;
use VuFindSearch\Command\RetrieveCommand;
use VuFindSearch\ParamBag;

use function count;
use function is_object;

/**
 * Alphabrowse channel provider.
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class AlphaBrowse extends AbstractChannelProvider implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Number of results to include in each channel.
     *
     * @var int
     */
    protected $channelSize;

    /**
     * Maximum number of records to examine for similar results.
     *
     * @var int
     */
    protected $maxRecordsToExamine;

    /**
     * Search service
     *
     * @var \VuFindSearch\Service
     */
    protected $searchService;

    /**
     * URL helper
     *
     * @var Url
     */
    protected $url;

    /**
     * Record router
     *
     * @var RecordRouter
     */
    protected $recordRouter;

    /**
     * Browse index to search
     *
     * @var string
     */
    protected $browseIndex;

    /**
     * Solr field to use for search seed
     *
     * @var string
     */
    protected $solrField;

    /**
     * How many rows to show before the selected value
     *
     * @var int
     */
    protected $rowsBefore;

    /**
     * The search backend to query
     *
     * @var string
     */
    protected $source;

    /**
     * Constructor
     *
     * @param \VuFindSearch\Service $search  Search service
     * @param Url                   $url     URL helper
     * @param RecordRouter          $router  Record router
     * @param array                 $options Settings (optional)
     */
    public function __construct(
        \VuFindSearch\Service $search,
        Url $url,
        RecordRouter $router,
        array $options = []
    ) {
        $this->searchService = $search;
        $this->url = $url;
        $this->recordRouter = $router;
        $this->setOptions($options);
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
        $this->channelSize = $options['channelSize'] ?? 20;
        $this->maxRecordsToExamine = $options['maxRecordsToExamine'] ?? 2;
        $this->browseIndex = $options['browseIndex'] ?? 'lcc';
        $this->solrField = $options['solrField'] ?? 'callnumber-raw';
        $this->rowsBefore = $options['rows_before'] ?? 10;
        $this->source = $options['source'] ?? 'Solr';
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
        // If we have a token and it doesn't match the record driver, we can't
        // fetch any results!
        if ($channelToken !== null && $channelToken !== $driver->getUniqueID()) {
            return [];
        }
        $channel = $this->buildChannelFromRecord($driver);
        return (count($channel['contents']) > 0) ? [$channel] : [];
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
        $driver = null;
        $channels = [];
        foreach ($results->getResults() as $driver) {
            // If we have a token and it doesn't match the current driver, skip
            // that driver.
            if ($channelToken !== null && $channelToken !== $driver->getUniqueID()) {
                continue;
            }
            $channel = (count($channels) < $this->maxRecordsToExamine)
                ? $this->buildChannelFromRecord($driver)
                : $this->buildChannelFromRecord($driver, true);
            if (isset($channel['token']) || count($channel['contents']) > 0) {
                $channels[] = $channel;
            }
        }
        // If the search results did not include the object we were looking for,
        // we need to fetch it from the search service:
        if (empty($channels) && is_object($driver) && $channelToken !== null) {
            $command = new RetrieveCommand(
                $driver->getSourceIdentifier(),
                $channelToken
            );
            $driver = $this->searchService->invoke($command)->getResult()->first();
            if ($driver) {
                $channels[] = $this->buildChannelFromRecord($driver);
            }
        }
        return $channels;
    }

    /**
     * Given details from alphabeticBrowse(), create channel contents.
     *
     * @param array $details Details from alphabetic browse index
     *
     * @return array
     */
    protected function summarizeBrowseDetails($details)
    {
        $ids = $results = [];
        if (isset($details['Browse']['items'])) {
            foreach ($details['Browse']['items'] as $item) {
                if (!isset($item['extras']['title'][0][0])) {
                    continue;
                }
                // Collect a list of IDs in the result set while we create it:
                $ids[] = $id = $item['extras']['id'][0][0];
                $results[] = [
                    'title' => $item['extras']['title'][0][0],
                    'source' => $this->source,
                    'thumbnail' => false, // TODO: better thumbnails!
                    'id' => $id,
                ];
            }
        }
        // If we have a cover router and a non-empty ID list, look up thumbnails:
        if ($this->coverRouter && !empty($ids)) {
            $command = new RetrieveBatchCommand($this->source, $ids);
            $records = $this->searchService->invoke($command)->getResult();
            $thumbs = [];
            // First map record drivers to an ID => thumb array...
            foreach ($records as $record) {
                $thumbs[$record->getUniqueId()] = $this->coverRouter
                    ->getUrl($record, 'medium');
            }
            // Now apply the thumbnails to the existing result set...
            foreach ($results as $i => $current) {
                if (isset($thumbs[$current['id']])) {
                    $results[$i]['thumbnail'] = $thumbs[$current['id']];
                }
            }
        }
        return $results;
    }

    /**
     * Add a new filter to an existing search results object to populate a
     * channel.
     *
     * @param RecordDriver $driver    Record driver
     * @param bool         $tokenOnly Create full channel (false) or return a
     * token for future loading (true)?
     *
     * @return array
     */
    protected function buildChannelFromRecord(
        RecordDriver $driver,
        $tokenOnly = false
    ) {
        $retVal = [
            'title' => $this->translate(
                'nearby_items',
                ['%%title%%' => $driver->getBreadcrumb()]
            ),
            'providerId' => $this->providerId,
            'links' => [],
        ];
        $raw = $driver->getRawData();
        $from = isset($raw[$this->solrField]) ? (array)$raw[$this->solrField] : null;
        if (empty($from[0])) {
            // If there is no "from" value to look up, skip this so we don't
            //generate a token that retrieves nothing later!
            $retVal['contents'] = [];
        } elseif ($tokenOnly) {
            $retVal['token'] = $driver->getUniqueID();
        } else {
            $command = new AlphabeticBrowseCommand(
                $this->source,
                $this->browseIndex,
                // If we got this far, we can safely assume that $from[0] is set
                $from[0],
                0,
                $this->channelSize,
                new ParamBag(['extras' => 'title:author:isbn:id']),
                -$this->rowsBefore
            );
            $details = $this->searchService->invoke($command)->getResult();
            $retVal['contents'] = $this->summarizeBrowseDetails($details);
            $route = $this->recordRouter->getRouteDetails($driver);
            $retVal['links'][] = [
                'label' => 'View Record',
                'icon' => 'fa-file-text-o',
                'url' => $this->url
                    ->fromRoute($route['route'], $route['params']),
            ];
            $retVal['links'][] = [
                'label' => 'channel_expand',
                'icon' => 'fa-search-plus',
                'url' => $this->url->fromRoute('channels-record')
                    . '?id=' . urlencode($driver->getUniqueID())
                    . '&source=' . urlencode($driver->getSourceIdentifier()),
            ];
            $retVal['links'][] = [
                'label' => 'channel_browse',
                'icon' => 'fa-list',
                'url' => $this->url->fromRoute('alphabrowse-home')
                    . '?source=' . urlencode($this->browseIndex)
                    . '&from=' . $from[0],
            ];
        }
        return $retVal;
    }
}
