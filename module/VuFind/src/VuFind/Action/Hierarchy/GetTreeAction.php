<?php

/**
 * "Get hierarchy tree" action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2023.
 * Copyright (C) The National Library of Finland 2024-2026.
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

namespace VuFind\Action\Hierarchy;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractAction;
use VuFind\ActionHelper\ResponseHelper;
use VuFind\Http\HttpStatus;
use VuFind\Record\Loader as RecordLoader;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * "Get hierarchy tree" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class GetTreeAction extends AbstractAction
{
    /**
     * Constructor.
     *
     * @param RecordLoader $recordLoader Record loader
     */
    #[Autowire]
    public function __construct(
        protected RecordLoader $recordLoader,
    ) {
    }

    /**
     * Get hierarchy tree.
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
        $this->disableSessionWrites();  // avoid session write timing bug

        $responseHelper = $this->getHelper(ResponseHelper::class);
        if (null === ($id = $this->getQueryParam('id'))) {
            return $responseHelper->getJsonResponse($response, ['error' => 'Missing ID'], HttpStatus::BAD_REQUEST);
        }
        $source = $this->getQueryParam('sourceId', DEFAULT_SEARCH_BACKEND);
        try {
            $recordDriver = $this->recordLoader->load($id, $source);
            $hierarchyDriver = $recordDriver->tryMethod('getHierarchyDriver');
            if ($hierarchyDriver) {
                return $responseHelper->getJsonResponse(
                    $response,
                    [
                        'html' => $hierarchyDriver->render(
                            $recordDriver,
                            $this->getQueryParam('context', 'Record'),
                            'List',
                            $this->getQueryParam('hierarchyId', ''),
                            $request->getQueryParams(),
                        ),
                    ],
                    allowCaching: true
                );
            }
        } catch (\Exception $e) {
            // Return the exception if in development mode:
            if (APPLICATION_ENV === 'development') {
                return $responseHelper->getJsonResponse($response, ['error' => (string)$e], HttpStatus::ERROR);
            }
        }

        // If we got this far, something went wrong:
        return $responseHelper->getJsonResponse($response, ['error' => 'An error has occurred'], HttpStatus::ERROR);
    }
}
