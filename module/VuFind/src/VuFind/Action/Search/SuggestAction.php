<?php

/**
 * OpenSearch suggest action.
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

use Laminas\Stdlib\Parameters;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractAction;
use VuFind\ActionHelper\ResponseHelper;
use VuFind\Autocomplete\Suggester;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * OpenSearch suggest action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SuggestAction extends AbstractAction
{
    /**
     * Constructor.
     *
     * @param Suggester $suggester Suggester
     */
    #[Autowire]
    public function __construct(
        protected Suggester $suggester,
    ) {
        parent::__construct();
    }

    /**
     * Provide OpenSearch suggestions as specified at
     * http://www.opensearch.org/Specifications/OpenSearch/Extensions/Suggestions/1.0.
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
        // Always use 'AllFields' as our autosuggest type:
        $query = new Parameters($request->getQueryParams());
        $query->set('type', 'AllFields');

        // Get suggestions and make sure they are an array (we don't want to JSON encode them into an object):
        $suggestions = $this->suggester->getSuggestions($query, 'type', 'lookfor');

        // Send the JSON response:
        return $this->getHelper(ResponseHelper::class)->getJsonResponse(
            $response,
            [$query->get('lookfor', ''), $suggestions]
        );
    }
}
