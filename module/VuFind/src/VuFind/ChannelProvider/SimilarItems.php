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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\ChannelProvider;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\Search\Base\Params, VuFind\Search\Base\Results;
use VuFind\I18n\Translator\TranslatorAwareInterface;

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
     * Constructor
     *
     * @param \VuFindSearch\Service $search Search service
     */
    public function __construct(\VuFindSearch\Service $search)
    {
        $this->searchService = $search;
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
     * @param RecordDriver $driver Record driver
     *
     * @return array
     */
    public function getFromRecord(RecordDriver $driver)
    {
        $channel = $this->buildChannelFromRecord($driver);
        return (count($channel['contents']) > 0) ? [$channel] : [];
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
        $channels = [];
        foreach ($results->getResults() as $driver) {
            $channel = $this->buildChannelFromRecord($driver);
            if (count($channel['contents']) > 0) {
                $channels[] = $channel;
            }
            if (count($channels) >= $this->maxRecordsToExamine) {
                break;
            }
        }
        return $channels;
    }

    /**
     * Add a new filter to an existing search results object to populate a
     * channel.
     *
     * @param RecordDriver $driver Record driver
     *
     * @return array
     */
    protected function buildChannelFromRecord(RecordDriver $driver)
    {
        $params = new \VuFindSearch\ParamBag(['rows' => $this->channelSize]);
        $similar = $this->searchService->similar(
            $driver->getSourceIdentifier(), $driver->getUniqueId(), $params
        );
        $heading = $this->translate('Similar Items');
        return [
            'title' => "{$heading}: {$driver->getBreadcrumb()}",
            'contents' => $this->summarizeRecordDrivers($similar)
        ];
    }
}
