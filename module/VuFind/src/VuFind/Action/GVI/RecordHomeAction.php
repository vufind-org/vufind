<?php

/**
 * GVI record home action.
 *
 * PHP version 8
 *
 * Copyright (C) Universitätsbibliothek Mannheim 2026.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Action
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\GVI;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\Record\AbstractRecordAction;

/**
 * GVI record home action.
 *
 * @category VuFind
 * @package  Action
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HomeAction extends AbstractRecordAction
{
    /**
     * Initialize the action.
     *
     * @return void
     */
    protected function init(): void
    {
        parent::init();
        $this->sourceId = 'GVI';
        $this->fallbackDefaultTab = 'Description';
    }

    /**
     * Display record home page.
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
        $routeMatch = $request->getAttribute('route-match');
        $checkRoute = $this->getPostOrQueryParam('checkRoute');
        if ($checkRoute && ($this->config['Collections']['collections'] ?? false)) {
            $routeConfig = $this->config['Collections']['route'] ?? [];
            $collectionRoutes = array_merge(['record' => 'collection'], $routeConfig);
            $routeName = $routeMatch->getMatchedRouteName() ?? '';
            if ($collectionRoute = ($collectionRoutes[$routeName] ?? null)) {
                $driver = $this->loadRecord();
                if (true === $driver->tryMethod('isCollection')) {
                    $routeParams = $this->request->getQueryParams() + $routeMatch->getParams();
                    $queryParams = [];
                    if ($sid = $this->searchMemory->getCurrentSearchId()) {
                        $queryParams = compact('sid');
                    }
                    $collectionUrl = $this->getUrlFromRoute($collectionRoute, $routeParams, $queryParams);
                    return $this->getRedirectResponse($response, $collectionUrl);
                }
            }
        }
        return $this->showTab($this->getRouteParam('tab') ?? $this->getDefaultTab());
    }
}
