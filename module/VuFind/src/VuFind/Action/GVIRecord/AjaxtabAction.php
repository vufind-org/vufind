<?php

/**
 * GVI record AJAX tab action.
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

namespace VuFind\Action\GVIRecord;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\Record\AbstractRecordAction;

/**
 * GVI record AJAX tab action.
 *
 * @category VuFind
 * @package  Action
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AjaxtabAction extends AbstractRecordAction
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
     * Display a particular tab via AJAX.
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
        $this->request = $request;
        $tab = $request->getParsedBody()['tab'] ?? $this->getRouteParam('tab') ?? $this->getDefaultTab();

        $driver = $this->loadRecord();
        $tabs = $this->getAllTabs();
        $activeTab = strtolower($tab);

        $tabParams = [
            'tabs' => $tabs,
            'activeTab' => $activeTab,
            'defaultTab' => strtolower($this->getDefaultTab()),
            'backgroundTabs' => $this->getBackgroundTabs(),
            'tabsExtraScripts' => $this->getTabsExtraScripts($tabs),
            'driver' => $driver,
        ];

        $content = $this->getTemplateRenderer()->renderTemplateAsString(
            $request,
            'record/ajaxtab',
            $tabParams,
            useLayout: false
        );

        $response->getBody()->write($content);
        return $response;
    }
}
