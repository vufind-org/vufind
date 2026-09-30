<?php

/**
 * "Get Autocomplete Suggestions" AJAX handler.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Stdlib\Parameters;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Autocomplete\Suggester;
use VuFind\Session\Settings as SessionSettings;

/**
 * "Get Autocomplete Suggestions" AJAX handler.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetACSuggestions extends AbstractBase
{
    /**
     * Constructor.
     *
     * @param SessionSettings $ss        Session settings
     * @param Suggester       $suggester Autocomplete suggester
     */
    public function __construct(SessionSettings $ss, protected Suggester $suggester)
    {
        parent::__construct($ss);
    }

    /**
     * Handle a request.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(ServerRequestInterface $request): array
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $query = new Parameters($request->getQueryParams());
        $suggestions = $this->suggester->getSuggestions($query);
        return $this->formatResponse(compact('suggestions'));
    }
}
