<?php

/**
 * "Get Result Counts" AJAX Handler
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) Staats- und Universitätsbibliothek 2021-2022.
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
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Hajo Seng <hajo.seng@sub.uni-hamburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Stdlib\Parameters;
use VuFind\Search\Results\PluginManager as ResultsManager;
use VuFind\Session\Settings as SessionSettings;

/**
 * "Get Result Counts" AJAX Handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Hajo Seng <hajo.seng@sub.uni-hamburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetResultCount extends AbstractBase
{
    /**
     * ResultsManager
     *
     * @var resultsManager
     */
    protected $resultsManager;

    /**
     * Constructor
     *
     * @param ResultsManager  $resultsManager Results Manager
     * @param SessionSettings $ss             Session settings
     */
    public function __construct(ResultsManager $resultsManager, SessionSettings $ss)
    {
        $this->resultsManager = $resultsManager;
        $this->sessionSettings = $ss;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites();
        $queryString = $params->fromQuery('querystring');
        parse_str(parse_url($queryString, PHP_URL_QUERY), $searchParams);

        $backend = $params->fromQuery('source', DEFAULT_SEARCH_BACKEND);
        $results = $this->resultsManager->get($backend);
        $paramsObj = $results->getParams();
        $paramsObj->getOptions()->disableHighlighting();
        $paramsObj->getOptions()->spellcheckEnabled(false);
        $paramsObj->getOptions()->setLimitOptions([0]);
        $paramsObj->initFromRequest(new Parameters($searchParams));

        return $this->formatResponse(['total' => $results->getResultTotal()]);
    }
}
