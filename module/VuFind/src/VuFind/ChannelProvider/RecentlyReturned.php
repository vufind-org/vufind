<?php
/**
 * "Recently returned" channel provider.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2017.
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

/**
 * "Recently returned" channel provider.
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RecentlyReturned extends AbstractChannelProvider
    implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Number of results to include in each channel.
     *
     * @var int
     */
    protected $channelSize;

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
    public function __construct(\VuFindSearch\Service $search,
        \VuFind\ILS\Connection $ils, array $options = []
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
        $this->channelSize = isset($options['channelSize'])
            ? $options['channelSize'] : 20;
        $this->maxAge = isset($options['maxAge'])
            ? $options['maxAge'] : 30;
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
     * Recently returned channel contents are always the same; this does not
     * care about specific records or search parameters.
     *
     * @return array
     */
    protected function getChannel()
    {
        // If the ILS does not support this channel, give up now:
        if (!$this->ils->checkCapability('getRecentlyReturnedBibs')) {
            return [];
        }
        // Set up channel metadata:
        $retVal = [
            'title' => $this->translate('recently_returned_channel_title'),
            'providerId' => $this->providerId,
        ];
        // Use a callback to extract IDs from the arrays in the ILS return value:
        $callback = function ($arr) {
            return $arr['id'];
        };
        $ids = array_map(
            $callback,
            $this->ils->getRecentlyReturnedBibs($this->channelSize, $this->maxAge)
        );
        // Look up the record drivers for the recently returned IDs:
        $recent = $this->searchService->retrieveBatch('Solr', $ids)->getRecords();
        // Build the channel contents:
        $retVal['contents'] = $this->summarizeRecordDrivers($recent);
        return (count($retVal['contents']) > 0) ? [$retVal] : [];
    }
}
