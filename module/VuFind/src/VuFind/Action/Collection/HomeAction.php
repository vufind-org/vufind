<?php

/**
 * Collection tab action.
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

namespace VuFind\Action\Collection;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\Record\AbstractRecordAction;
use VuFind\RecordTab\TabManager;

/**
 * Collection tab action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
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
        // Set default tab, if specified:
        if (null !== ($defaultTab = $this->config['Collections']['defaultTab'] ?? null)) {
            $this->fallbackDefaultTab = $defaultTab;
        }
    }

    /**
     * Get the tab configuration for this controller.
     *
     * @return TabManager
     */
    protected function getRecordTabManager(): TabManager
    {
        $manager = $this->tabManager;
        $manager->setContext('collection');
        return $manager;
    }

    /**
     * Display a particular tab.
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
        // If collections are active, we may need to check if the driver is actually
        // a collection; if so, we should redirect to the collection controller.
        $checkRoute = $this->getPostOrQueryParam('checkRoute');
        if ($checkRoute && ($this->config['Collections']['collections'] ?? false)) {
            $routeConfig = $this->config['Collections']['route'] ?? [];
            $collectionRoutes
                = array_merge(['record' => 'collection'], $routeConfig);
            $routeName = $routeMatch->getMatchedRouteName() ?? '';
            if ($collectionRoute = ($collectionRoutes[$routeName] ?? null)) {
                $driver = $this->loadRecord();
                if (true === $driver->tryMethod('isCollection')) {
                    $params = $this->request->getQueryParams() + $routeMatch->getParams();
                    // Disable path normalization since it can unencode e.g. encoded
                    // slashes in record id's
                    $options = [
                        'normalize_path' => false,
                    ];
                    if ($sid = $this->searchMemory->getCurrentSearchId()) {
                        $options['query'] = compact('sid');
                    }
                    $collectionUrl = $this->getRouteUrl($collectionRoute, $params, $options);
                    return $this->getRedirectResponse($response, $collectionUrl);
                }
            }
        }

        return $this->showTab($routeMatch->getParam('tab') ?? $this->getDefaultTab());
    }

    /**
     * Display a particular tab.
     *
     * @param string $tab  Name of tab to display
     * @param bool   $ajax Are we in AJAX mode?
     *
     * @return mixed
     */
    protected function showTab($tab, $ajax = false)
    {
        // Check that collections are enabled and redirect if necessary
        if (empty($this->config['Collections']['collections'])) {
            return $this->redirectToRecord();
        }

        $result = parent::showTab($tab, $ajax);
        if (
            !$ajax && $result instanceof \Laminas\View\Model\ViewModel
            && $result->getTemplate() !== 'myresearch/login'
        ) {
            $result->setTemplate('collection/view');
        }
        return $result;
    }
}
