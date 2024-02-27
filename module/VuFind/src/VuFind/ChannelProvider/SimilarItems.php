<?php

/**
 * "Similar items" channel provider.
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
use VuFindSearch\Command\RetrieveCommand;
use VuFindSearch\Command\SimilarCommand;

use function count;
use function is_object;

/**
 * "Similar items" channel provider.
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SimilarItems extends AbstractChannelProvider implements TranslatorAwareInterface
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
            if (count($channels) < $this->maxRecordsToExamine) {
                $channel = $this->buildChannelFromRecord($driver);
                if (count($channel['contents']) > 0) {
                    $channels[] = $channel;
                }
            } else {
                $channels[] = $this->buildChannelFromRecord($driver, true);
            }
        }
        // If the search results did not include the object we were looking for,
        // we need to fetch it from the search service:
        if (
            empty($channels)
            && is_object($driver ?? null)
            && $channelToken !== null
        ) {
            $command = new RetrieveCommand(
                $driver->getSourceIdentifier(),
                $channelToken
            );
            $driver = $this->searchService->invoke(
                $command
            )->getResult()->first();
            if ($driver) {
                $channels[] = $this->buildChannelFromRecord($driver);
            }
        }
        return $channels;
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
        $heading = $this->translate('Similar Items');
        $retVal = [
            'title' => "{$heading}: {$driver->getBreadcrumb()}",
            'providerId' => $this->providerId,
            'links' => [],
        ];
        if ($tokenOnly) {
            $retVal['token'] = $driver->getUniqueID();
        } else {
            $params = new \VuFindSearch\ParamBag(['rows' => $this->channelSize]);
            $command = new SimilarCommand(
                $driver->getSourceIdentifier(),
                $driver->getUniqueID(),
                $params
            );
            $similar = $this->searchService->invoke($command)->getResult();
            $retVal['contents'] = $this->summarizeRecordDrivers($similar);
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
        }
        return $retVal;
    }
}
