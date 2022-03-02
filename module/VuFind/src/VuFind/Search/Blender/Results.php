<?php
/**
 * Blender aspect of the Search Multi-class (Results)
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2019.
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
 * @package  Search_Blender
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Search\Blender;

use VuFindSearch\Command\SearchCommand;

/**
 * Blender aspect of the Search Multi-class (Results)
 *
 * @category VuFind
 * @package  Search_Blender
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Results extends \VuFind\Search\Solr\Results
{
    /**
     * Search backend identifiers.
     *
     * @var string
     */
    protected $backendId = 'Blender';

    /**
     * Support method for performAndProcessSearch -- perform a search based on the
     * parameters passed to the object.
     *
     * @return void
     */
    protected function performSearch()
    {
        $query  = $this->getParams()->getQuery();
        $limit  = $this->getParams()->getLimit();
        $offset = $this->getStartRecord() - 1;
        $params = $this->getParams()->getBackendParameters();
        $searchService = $this->getSearchService();

        try {
            $command = new SearchCommand(
                $this->backendId,
                $query,
                $offset,
                $limit,
                $params
            );
            $searchService->invoke($command);
            $collection = $command->getResult();
        } catch (\VuFindSearch\Backend\Exception\BackendException $e) {
            // If the query caused a parser error, see if we can clean it up:
            if ($e->hasTag('VuFind\Search\ParserError')
                && $newQuery = $this->fixBadQuery($query)
            ) {
                // We need to get a fresh command, since the previous one was
                // manipulated by the previous search.
                $command = new SearchCommand(
                    $this->backendId,
                    $newQuery,
                    $offset,
                    $limit,
                    $params
                );
                $searchService->invoke($command);
                $collection = $command->getResult();
            } else {
                throw $e;
            }
        }

        $this->responseFacets = $collection->getFacets();
        $this->resultTotal = $collection->getTotal();

        // Process spelling suggestions
        $spellcheck = $collection->getSpellcheck();
        $this->spellingQuery = $spellcheck->getQuery();
        $this->suggestions = $this->getSpellingProcessor()
            ->getSuggestions($spellcheck, $this->getParams()->getQuery());

        // Construct record drivers for all the items in the response:
        $this->results = $collection->getRecords();

        $this->errors = $collection->getErrors();
    }
}
