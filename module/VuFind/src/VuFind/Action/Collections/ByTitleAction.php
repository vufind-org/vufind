<?php

/**
 * Collections browse by title action.
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
use VuFind\ActionHelper\RedirectHelper;
use VuFindSearch\Command\SearchCommand;
use VuFindSearch\Query\Query;

use function count;

/**
 * Collections browse by title action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ByTitleAction extends AbstractCollectionsAction
{
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
        $collections = $this->getCollectionsFromTitle($this->getQueryParam('title', ''));
        if (count($collections) != 1) {
            return $this->renderTemplate($request, $response, compact('collections'));
        }
        return $this->getHelper(RedirectHelper::class)
            ->redirectToRoute($response, 'collection', ['id' => $collections[0]->getUniqueId()]);
    }

    /**
     * Get collection information matching a given title:
     *
     * @param string $title Title to search for
     *
     * @return array
     */
    protected function getCollectionsFromTitle(string $title): array
    {
        $title = addcslashes($title, '"');
        $query = new Query("is_hierarchy_title:\"$title\"", 'AllFields');
        $command = new SearchCommand(
            'Solr',
            $query,
            0,
            $this->getBrowseLimit()
        );
        $result = $this->searchService->invoke($command)->getResult();
        return $result->getRecords();
    }
}
