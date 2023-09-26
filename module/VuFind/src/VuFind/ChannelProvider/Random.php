<?php

/**
 * "Random items" channel provider.
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

use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\Search\Base\Params;
use VuFind\Search\Base\Results;
use VuFindSearch\Command\RandomCommand;

use function count;

/**
 * "Random items" channel provider.
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Random extends AbstractChannelProvider implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Number of results to include in each channel.
     *
     * @var int
     */
    protected $channelSize;

    /**
     * Mode
     *
     * @var string
     */
    protected $mode;

    /**
     * Search service
     *
     * @var \VuFindSearch\Service
     */
    protected $searchService;

    /**
     * Params manager
     *
     * @var \VuFind\Search\Params\PluginManager
     */
    protected $paramManager;

    /**
     * Constructor
     *
     * @param \VuFindSearch\Service               $search       Search service
     * @param \VuFind\Search\Params\PluginManager $paramManager Params manager
     * @param array                               $options      Settings (optional)
     */
    public function __construct(
        \VuFindSearch\Service $search,
        \VuFind\Search\Params\PluginManager $paramManager,
        array $options = []
    ) {
        $this->searchService = $search;
        $this->paramManager = $paramManager;
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
        $this->mode = $options['mode'] ?? 'retain';
    }

    /**
     * Return channel information derived from a record driver object.
     *
     * @param RecordDriver $driver       Record driver
     * @param string       $channelToken Token identifying a single specific channel
     * to load (if omitted, all channels will be loaded) -- not used in this provider
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getFromRecord(RecordDriver $driver, $channelToken = null)
    {
        $randomParams = $this->paramManager->get($driver->getSourceIdentifier());
        $channel = $this->buildChannelFromParams($randomParams);
        return (count($channel['contents']) > 0) ? [$channel] : [];
    }

    /**
     * Return channel information derived from a search results object.
     *
     * @param Results $results      Search results
     * @param string  $channelToken Token identifying a single specific channel
     * to load (if omitted, all channels will be loaded) -- not used in this provider
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getFromSearch(Results $results, $channelToken = null)
    {
        $params = $results->getParams();
        if ('retain' !== $this->mode) {
            $randomParams = $this->paramManager->get($params->getSearchClassId());
        } else {
            $randomParams = clone $params;
        }
        $channel = $this->buildChannelFromParams($randomParams);
        return (count($channel['contents']) > 0) ? [$channel] : [];
    }

    /**
     * Add a new filter to an existing search results object to populate a
     * channel.
     *
     * @param Params $params Search parameter object
     *
     * @return array
     */
    protected function buildChannelFromParams(Params $params)
    {
        $retVal = [
            'title' => $this->translate('random_recommendation_title'),
            'providerId' => $this->providerId,
        ];
        $query = $params->getQuery();
        $paramBag = $params->getBackendParameters();
        $command = new RandomCommand(
            $params->getSearchClassId(),
            $query,
            $this->channelSize,
            $paramBag
        );
        $random = $this->searchService->invoke($command)->getResult()->getRecords();
        $retVal['contents'] = $this->summarizeRecordDrivers($random);
        return $retVal;
    }
}
