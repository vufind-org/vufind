<?php
/**
 * "Similar items" channel provider.
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
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\ChannelProvider;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\Record\Router as RecordRouter;
use VuFind\Search\Base\Params, VuFind\Search\Base\Results;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use Zend\Mvc\Controller\Plugin\Url;

/**
 * "Similar items" channel provider.
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SimilarItems extends AbstractChannelProvider
    implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Number of results to include in each channel.
     *
     * @var int
     */
    protected $channelSize = 20;

    /**
     * Maximum number of records to examine for similar results.
     *
     * @var int
     */
    protected $maxRecordsToExamine = 2;

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
     * @param \VuFindSearch\Service $search Search service
     * @param Url                   $url    URL helper
     * @param RecordRouter          $router Record router
     */
    public function __construct(\VuFindSearch\Service $search, Url $url,
        RecordRouter $router
    ) {
        $this->searchService = $search;
        $this->url = $url;
        $this->recordRouter = $router;
    }

    /**
     * Hook to configure search parameters before executing search.
     *
     * @param Params $params Search parameters to adjust
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function configureSearchParams(Params $params)
    {
        // No action necessary.
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
    protected function buildChannelFromRecord(RecordDriver $driver,
        $tokenOnly = false
    ) {
        $heading = $this->translate('Similar Items');
        $retVal = [
            'title' => "{$heading}: {$driver->getBreadcrumb()}",
            'providerId' => $this->providerId,
        ];
        if ($tokenOnly) {
            $retVal['token'] = $driver->getUniqueID();
        } else {
            $params = new \VuFindSearch\ParamBag(['rows' => $this->channelSize]);
            $similar = $this->searchService->similar(
                $driver->getSourceIdentifier(), $driver->getUniqueID(), $params
            );
            $retVal['contents'] = $this->summarizeRecordDrivers($similar);
            $retVal['channelsUrl'] = $this->url->fromRoute('channels-record')
                . '?id=' . urlencode($driver->getUniqueID())
                . '&source=' . urlencode($driver->getSourceIdentifier());
            $route = $this->recordRouter->getRouteDetails($driver);
            $retVal['searchUrl'] = $this->url
                ->fromRoute($route['route'], $route['params']);
        }
        return $retVal;
    }
}
