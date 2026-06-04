<?php

/**
 * Collections home action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Collections;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFindSearch\Command\AlphabeticBrowseCommand;

use function array_slice;
use function count;

/**
 * Collections home action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HomeAction extends AbstractCollectionsAction
{
    /**
     * The name of the backend providing alphabrowse services.
     *
     * @var string
     */
    protected string $alphabrowseBackend = DEFAULT_SEARCH_BACKEND;

    /**
     * Display home page.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $browseType = $this->config['Collections']['browseType'] ?? 'Index';
        return 'Alphabetic' === $browseType
            ? $this->showBrowseAlphabetic()
            : $this->showBrowseIndex();
    }

    /**
     * Show the Browse Menu.
     *
     * @return ResponseInterface
     */
    protected function showBrowseAlphabetic(): ResponseInterface
    {
        // Process incoming parameters:
        $source = 'hierarchy';
        $from = $this->getQueryParam('from', '');
        $page = $this->getQueryParam('page', 0);
        $limit = $this->getBrowseLimit();

        // Load Solr data or die trying:
        $command = new AlphabeticBrowseCommand(
            $this->alphabrowseBackend,
            $source,
            $from,
            $page,
            $limit
        );
        $result = $this->searchService->invoke($command)->getResult();

        // No results?  Try the previous page just in case we've gone past the
        // end of the list....
        if ($result['Browse']['totalCount'] == 0) {
            $page--;
            $command->setPage($page);
            $result = $this->searchService->invoke($command)->getResult();
        }

        $templateParams = [
            'from' => $from,
            'letters' => $this->getAlphabetList(),
        ];

        // Only display next/previous page links when applicable:
        if ($result['Browse']['totalCount'] > $limit) {
            $templateParams['nextpage'] = $page + 1;
        }
        if ($result['Browse']['offset'] + $result['Browse']['startRow'] > 1) {
            $templateParams['prevpage'] = $page - 1;
        }

        // Format the results for proper display:
        $finalresult = [];
        $delimiter = $this->getBrowseDelimiter();
        foreach ($result['Browse']['items'] as $rkey => $collection) {
            $collectionIdNamePair = explode($delimiter, $collection['heading']);
            $finalresult[$rkey]['displayText'] = $collectionIdNamePair[0];
            $finalresult[$rkey]['count'] = $collection['count'];
            $finalresult[$rkey]['value'] = $collectionIdNamePair[1];
        }
        $templateParams['result'] = $finalresult;

        return $this->renderTemplate($this->request, $this->response, $templateParams);
    }

    /**
     * Show the Browse Menu.
     *
     * @return ResponseInterface
     */
    protected function showBrowseIndex(): ResponseInterface
    {
        // Process incoming parameters:
        $from = $this->getQueryParam('from', '');
        $page = $this->getQueryParam('page', 0);
        $appliedFilters = $this->getQueryParam('filter', []);
        $limit = $this->getBrowseLimit();

        $browseField = 'hierarchy_browse';

        $searchObject = $this->searchResultsPluginManager->get(DEFAULT_SEARCH_BACKEND);
        foreach ($appliedFilters as $filter) {
            $searchObject->getParams()->addFilter($filter);
        }

        // Only grab 150,000 facet values to avoid out-of-memory errors:
        $result = $searchObject->getFullFieldFacets(
            [$browseField],
            false,
            150000,
            'index'
        );
        $result = $result[$browseField]['data']['list'] ?? [];

        $delimiter = $this->getBrowseDelimiter();
        foreach ($result as $rkey => $collection) {
            [$name, $id] = explode($delimiter, $collection['value'], 2);
            $result[$rkey]['displayText'] = $name;
            $result[$rkey]['value'] = $id;
        }

        // Sort the $results and get the position of the from string once sorted
        $key = $this->sortFindKeyLocation($result, $from);

        // Offset the key by how many pages in we are
        $key += ($limit * $page);

        // Catch out of range keys
        if ($key < 0) {
            $key = 0;
        }
        if ($key >= count($result)) {
            $key = count($result) - 1;
        }

        // Begin building template params:
        $templateParams = [
            'from' => $from,
            'letters' => $this->getAlphabetList(),
            'filters' => $searchObject->getParams()->getFilterList(true),
        ];

        // Only display next/previous page links when applicable:
        if (count($result) > $key + $limit) {
            $templateParams['nextpage'] = $page + 1;
        }
        if ($key > 0) {
            $templateParams['prevpage'] = $page - 1;
        }

        // Select just the records to display
        $templateParams['result'] = array_slice(
            $result,
            $key,
            count($result) > $key + $limit ? $limit : null
        );

        return $this->renderTemplate($this->request, $this->response, $templateParams);
    }

    /**
     * Function to sort the results and find the position of the from
     * value in the result set; if the value doesn't exist, it's inserted.
     *
     * @param array  $result Array to sort
     * @param string $from   Position to find
     *
     * @return int
     */
    protected function sortFindKeyLocation(array &$result, string $from): int
    {
        // Normalize the from value so it matches the values we are looking up
        $from = $this->normalizeForBrowse($from);

        // Push the from value into the array so we can find the matching position:
        array_push($result, ['displayText' => $from, 'placeholder' => true]);

        // Declare array to hold the $result array in the right sort order
        $sorted = [];
        foreach (array_keys($this->normalizeAndSortFacets($result)) as $i) {
            // If this is the placeholder we added earlier, we have found the
            // array position we want to use as our start; otherwise, it is an
            // element that needs to be moved into the sorted version of the
            // array:
            if (isset($result[$i]['placeholder'])) {
                $key = count($sorted);
            } else {
                $sorted[] = $result[$i];
                unset($result[$i]); //clear this out of memory
            }
        }
        $result = $sorted;

        return $key ?? 0;
    }

    /**
     * Normalize the value for the browse sort.
     *
     * @param string $val Value to normalize
     *
     * @return string $valNormalized
     */
    protected function normalizeForBrowse(string $val): string
    {
        $valNormalized = iconv('UTF-8', 'US-ASCII//TRANSLIT//IGNORE', $val);
        $valNormalized = strtolower($valNormalized);
        $valNormalized = preg_replace("/[^a-zA-Z0-9\s]/", '', $valNormalized);
        $valNormalized = trim($valNormalized);
        return $valNormalized;
    }

    /**
     * Function to normalize the names so they sort properly.
     *
     * @param array $result Array to sort (passed by reference to use less memory)
     *
     * @return array $resultOut
     */
    protected function normalizeAndSortFacets(array &$result): array
    {
        $valuesSorted = [];
        foreach ($result as $resKey => $resVal) {
            $valuesSorted[$resKey] = $this->normalizeForBrowse($resVal['displayText']);
        }
        $this->getSorter()->asort($valuesSorted);

        // Now the $valuesSorted is in the right order
        return $valuesSorted;
    }
}
