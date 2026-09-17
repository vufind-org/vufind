<?php

/**
 * Edit search memory action.
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

namespace VuFind\Action\Search;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractAction;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\ActionHelper\UrlHelper;
use VuFind\Search\Factory\UrlQueryHelperFactory;
use VuFind\Search\Memory;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Helper\Root\SearchMemory;

/**
 * Edit search memory action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EditMemoryAction extends AbstractAction
{
    /**
     * Constructor.
     *
     * @param Memory                $memory                Search memory
     * @param SearchMemory          $searchMemoryHelper    Search memory view helper
     * @param UrlQueryHelperFactory $urlQueryHelperFactory UrlQueryHelper factory
     */
    public function __construct(
        protected Memory $memory,
        #[Autowire(container: 'ViewHelperManager')]
        protected SearchMemory $searchMemoryHelper,
        protected UrlQueryHelperFactory $urlQueryHelperFactory,
    ) {
        parent::__construct();
    }

    /**
     * Edit search memory.
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
        // Get the user's referer, with the home page as a fallback; we'll
        // redirect here after the work is done.
        $from = $request->getHeader('Referer')[0] ?? null;
        if ($from || !$this->getHelper(UrlHelper::class)->isLocalUrl($from)) {
            $from = $this->routeHelper->getUrlFromRoute('home');
        }

        // Get parameters:
        $searchClassId = $this->getQueryParam('searchClassId', DEFAULT_SEARCH_BACKEND);
        $removeAllFilters = $this->getQueryParam('removeAllFilters');
        $removeFacet = $this->getQueryParam('removeFacet');
        $removeFilter = $this->getQueryParam('removeFilter');

        // Retrieve and manipulate the parameters:
        $params = $this->searchMemoryHelper->getLastSearchParams($searchClassId);
        $initialParams = $this->urlQueryHelperFactory->fromParams($params);

        if ($removeAllFilters) {
            $defaultFilters = $params->getOptions()->getDefaultFilters();
            $query = $initialParams->removeAllFilters();
            foreach ($defaultFilters as $filter) {
                $query = $query->addFilter($filter);
            }
        } elseif ($removeFacet) {
            $query = $initialParams->removeFacet(
                $removeFacet['field'] ?? '',
                $removeFacet['value'] ?? '',
                $removeFacet['operator'] ?? 'AND'
            );
        } elseif ($removeFilter) {
            $query = $initialParams->removeFilter($removeFilter);
        } else {
            $query = null;
        }

        // Remember the altered parameters:
        if ($query) {
            $base = $this->routeHelper->getUrlFromRoute($params->getOptions()->getSearchAction());
            $this->memory->rememberSearch($base . $query->getParams(false));
        }

        // Send the user back where they came from (but strip off the SID
        // so we don't override the modified search with an older version):
        $from = rtrim(preg_replace('/([?&])sid=\d+/', '$1', $from), '&?');
        return $this->getHelper(RedirectHelper::class)->redirectToUrl($response, $from);
    }
}
