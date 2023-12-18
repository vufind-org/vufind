<?php

/**
 * Abstract base class for channel providers relying on the ILS.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018, 2022.
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
use VuFind\Search\Base\Results;
use VuFindSearch\Command\RetrieveBatchCommand;

use function count;

/**
 * Abstract base class for channel providers relying on the ILS.
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractILSChannelProvider extends AbstractChannelProvider implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Number of results to include in each channel.
     *
     * @var int
     */
    protected $channelSize;

    /**
     * Channel title (will be run through translator).
     *
     * @var string
     */
    protected $channelTitle = 'Please set $channelTitle property!';

    /**
     * Maximum age (in days) of results to retrieve.
     *
     * @var int
     */
    protected $maxAge;

    /**
     * ILS connection
     *
     * @var \VuFind\ILS\Connection
     */
    protected $ils;

    /**
     * Search service
     *
     * @var \VuFindSearch\Service
     */
    protected $searchService;

    /**
     * Constructor
     *
     * @param \VuFindSearch\Service  $search  Search service
     * @param \VuFind\ILS\Connection $ils     ILS connection
     * @param array                  $options Settings (optional)
     */
    public function __construct(
        \VuFindSearch\Service $search,
        \VuFind\ILS\Connection $ils,
        array $options = []
    ) {
        $this->searchService = $search;
        $this->ils = $ils;
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
        $this->maxAge = $options['maxAge'] ?? 30;
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
        return $this->getChannel();
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
        return $this->getChannel();
    }

    /**
     * Retrieve data from the ILS.
     *
     * @return array
     */
    abstract protected function getIlsResponse();

    /**
     * Given one element from the ILS function's response array, extract the
     * ID value.
     *
     * @param array $response Response array
     *
     * @return string
     */
    abstract protected function extractIdsFromResponse($response);

    /**
     * Recently returned channel contents are always the same; this does not
     * care about specific records or search parameters.
     *
     * @return array
     */
    protected function getChannel()
    {
        // Use a callback to extract IDs from the arrays in the ILS return value:
        $ids = array_map([$this, 'extractIdsFromResponse'], $this->getIlsResponse());
        // No IDs means no response!
        if (empty($ids)) {
            return [];
        }
        // Look up the record drivers for the recently returned IDs:
        $command = new RetrieveBatchCommand('Solr', $ids);
        $records = $this->searchService->invoke($command)->getResult()->getRecords();
        // Build the return value:
        $retVal = [
            'title' => $this->translate($this->channelTitle),
            'providerId' => $this->providerId,
            'contents' => $this->summarizeRecordDrivers($records),
        ];
        return (count($retVal['contents']) > 0) ? [$retVal] : [];
    }
}
