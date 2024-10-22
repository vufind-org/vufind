<?php

/**
 * Collections Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010, 2022.
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use Laminas\Config\Config;
use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFindSearch\Command\SearchCommand;
use VuFindSearch\Query\Query;

use function array_slice;
use function count;

/**
 * Collections Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class CollectionsController extends AbstractBase implements
    \VuFind\I18n\HasSorterInterface
{
    use Feature\AlphaBrowseTrait;
    use \VuFind\I18n\HasSorterTrait;

    /**
     * VuFind configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm     Service manager
     * @param Config                  $config VuFind configuration
     */
    public function __construct(ServiceLocatorInterface $sm, Config $config)
    {
        $this->config = $config;
        $this->setSorter($sm->get(\VuFind\I18n\Sorter::class));
        parent::__construct($sm);
    }

    /**
     * Search by title action
     *
     * @return mixed
     */
    public function bytitleAction()
    {
        $collections = $this->getCollectionsFromTitle(
            $this->params()->fromQuery('title')
        );
        if (count($collections) != 1) {
            return $this->createViewModel(['collections' => $collections]);
        }
        return $this->redirect()
            ->toRoute('collection', ['id' => $collections[0]->getUniqueId()]);
    }

    /**
     * Browse action
     *
     * @return mixed
     */
    public function homeAction()
    {
        $browseType = $this->config->Collections->browseType ?? 'Index';
        return ($browseType == 'Alphabetic')
            ? $this->showBrowseAlphabetic() : $this->showBrowseIndex();
    }

    /**
     * Get the delimiter used to separate title from ID in the browse strings.
     *
     * @return string
     */
    protected function getBrowseDelimiter()
    {
        return $this->config->Collections->browseDelimiter ?? '{{{_ID_}}}';
    }

    /**
     * Show the Browse Menu
     *
     * @return mixed
     */
    protected function showBrowseAlphabetic()
    {
        // Process incoming parameters:
        $source = 'hierarchy';
        $from = $this->params()->fromQuery('from', '');
        $page = $this->params()->fromQuery('page', 0);
        $limit = $this->getBrowseLimit();

        // Load Solr data or die trying:
        $result = $this->alphabeticBrowse($source, $from, $page, $limit);

        // No results?  Try the previous page just in case we've gone past the
        // end of the list....
        if ($result['Browse']['totalCount'] == 0) {
            $page--;
            $result = $this->alphabeticBrowse($source, $from, $page, $limit);
        }

        // Begin building view model:
        $view = $this->createViewModel();

        // Only display next/previous page links when applicable:
        if ($result['Browse']['totalCount'] > $limit) {
            $view->nextpage = $page + 1;
        }
        if ($result['Browse']['offset'] + $result['Browse']['startRow'] > 1) {
            $view->prevpage = $page - 1;
        }

        // Send other relevant values to the template:
        $view->from = $from;
        $view->letters = $this->getAlphabetList();

        // Format the results for proper display:
        $finalresult = [];
        $delimiter = $this->getBrowseDelimiter();
        foreach ($result['Browse']['items'] as $rkey => $collection) {
            $collectionIdNamePair
                = explode($delimiter, $collection['heading']);
            $finalresult[$rkey]['displayText'] = $collectionIdNamePair[0];
            $finalresult[$rkey]['count'] = $collection['count'];
            $finalresult[$rkey]['value'] = $collectionIdNamePair[1];
        }
        $view->result = $finalresult;

        // Display the page:
        return $view;
    }

    /**
     * Show the Browse Menu
     *
     * @return mixed
     */
    protected function showBrowseIndex()
    {
        // Process incoming parameters:
        $from = $this->params()->fromQuery('from', '');
        $page = $this->params()->fromQuery('page', 0);
        $appliedFilters = $this->params()->fromQuery('filter', []);
        $limit = $this->getBrowseLimit();

        $browseField = 'hierarchy_browse';

        $searchObject = $this->getService(\VuFind\Search\Results\PluginManager::class)->get('Solr');
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

        // Begin building view model:
        $view = $this->createViewModel();

        // Only display next/previous page links when applicable:
        if (count($result) > $key + $limit) {
            $view->nextpage = $page + 1;
        }
        if ($key > 0) {
            $view->prevpage = $page - 1;
        }

        // Select just the records to display
        $result = array_slice(
            $result,
            $key,
            count($result) > $key + $limit ? $limit : null
        );

        // Send other relevant values to the template:
        $view->from = $from;
        $view->result = $result;
        $view->letters = $this->getAlphabetList();
        $view->filters = $searchObject->getParams()->getFilterList(true);

        // Display the page:
        return $view;
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
    protected function sortFindKeyLocation(&$result, $from)
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
     * Function to normalize the names so they sort properly
     *
     * @param array $result Array to sort (passed by reference to use less
     * memory)
     *
     * @return array $resultOut
     */
    protected function normalizeAndSortFacets(&$result)
    {
        $valuesSorted = [];
        foreach ($result as $resKey => $resVal) {
            $valuesSorted[$resKey]
                = $this->normalizeForBrowse($resVal['displayText']);
        }
        $this->getSorter()->asort($valuesSorted);

        // Now the $valuesSorted is in the right order
        return $valuesSorted;
    }

    /**
     * Normalize the value for the browse sort
     *
     * @param string $val Value to normalize
     *
     * @return string $valNormalized
     */
    protected function normalizeForBrowse($val)
    {
        $valNormalized = iconv('UTF-8', 'US-ASCII//TRANSLIT//IGNORE', $val);
        $valNormalized = strtolower($valNormalized);
        $valNormalized = preg_replace("/[^a-zA-Z0-9\s]/", '', $valNormalized);
        $valNormalized = trim($valNormalized);
        return $valNormalized;
    }

    /**
     * Get a list of initial letters to display.
     *
     * @return array
     */
    protected function getAlphabetList()
    {
        return array_merge(range('0', '9'), range('A', 'Z'));
    }

    /**
     * Get the collection browse page size
     *
     * @return int
     */
    protected function getBrowseLimit()
    {
        return $this->config->Collections->browseLimit ?? 20;
    }

    /**
     * Get collection information matching a given title:
     *
     * @param string $title Title to search for
     *
     * @return array
     */
    protected function getCollectionsFromTitle($title)
    {
        $title = addcslashes($title, '"');
        $query = new Query("is_hierarchy_title:\"$title\"", 'AllFields');
        $searchService = $this->getService(\VuFindSearch\Service::class);
        $command = new SearchCommand(
            'Solr',
            $query,
            0,
            $this->getBrowseLimit()
        );
        $result = $searchService->invoke($command)->getResult();
        return $result->getRecords();
    }
}
