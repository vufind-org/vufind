<?php
/**
 * Ajax Controller for Libraries Extension
 *
 * PHP version 5
 *
 * Copyright (C) Staats- und Universitätsbibliothek 2017.
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
 * @category VuFind2
 * @package  Controller
 * @author   Hajo Seng <hajo.seng@sub.uni-hamburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/subhh/beluga
 */
namespace VuFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Stdlib\Parameters;
use VuFind\AjaxHandler\AbstractBase;
use VuFind\Search\Results\PluginManager as ResultsManager;

/**
 * This controller handles global AJAX functionality
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class GetResultCount extends AbstractBase
{
    /**
     * resultsManager
     *
     * @var resultsManager
     */
    protected $resultsManager;

    /**
     * Constructor
     *
     * @param ResultsManager $resultsManager
     */
    public function __construct(ResultsManager $resultsManager)
    {
        $this->resultsManager = $resultsManager;
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
        $queryString = $params->fromQuery('querystring');
        $queryString = urldecode(
            str_replace(
                '&amp;',
                '&',
                trim($queryString)
            )
        );

        $queryArray = explode('&', $queryString);
        $searchParams = [];
        foreach ($queryArray as $queryItem) {
            [$key, $value] = explode('=', $queryItem, 2);
            if (strpos($key, '[]') > 0) {
                $key = str_replace('[]', '', $key);
                if (!isset($searchParams[$key])) {
                    $searchParams[$key] = [];
                }
                $searchParams[$key][] = $value;
            } else {
                $searchParams[$key] = $value;
            }
        }

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
