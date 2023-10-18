<?php

/**
 * "Get Record Details" AJAX handler
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Http\PhpEnvironment\Request;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Renderer\RendererInterface;
use VuFind\Record\Loader;
use VuFind\RecordTab\TabManager;

/**
 * "Get Record Details" AJAX handler
 *
 * Get record for integrated list view.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetRecordDetails extends AbstractBase
{
    /**
     * Framework configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Request
     *
     * @var Request
     */
    protected $request;

    /**
     * Record loader
     *
     * @var Loader
     */
    protected $recordLoader;

    /**
     * Record tab plugin manager
     *
     * @var TabManager
     */
    protected $tabManager;

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * Constructor
     *
     * @param array             $config   Framework configuration
     * @param Request           $request  HTTP request
     * @param Loader            $loader   Record loader
     * @param TabManager        $tm       Record Tab manager
     * @param RendererInterface $renderer Renderer
     */
    public function __construct(
        array $config,
        Request $request,
        Loader $loader,
        TabManager $tm,
        RendererInterface $renderer
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->recordLoader = $loader;
        $this->tabManager = $tm;
        $this->renderer = $renderer;
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
        $driver = $this->recordLoader
            ->load($params->fromQuery('id'), $params->fromQuery('source'));
        $viewtype = preg_replace(
            '/\W/',
            '',
            trim(strtolower($params->fromQuery('type')))
        );

        $details = $this->tabManager->getTabDetailsForRecord(
            $driver,
            $this->request,
            'Information'
        );

        $html = $this->renderer->render(
            'record/ajaxview-' . $viewtype . '.phtml',
            [
                'defaultTab' => $details['default'],
                'driver' => $driver,
                'tabs' => $details['tabs'],
                'backgroundTabs' => $this->tabManager
                    ->getBackgroundTabNames($driver),
            ]
        );
        return $this->formatResponse(compact('html'));
    }
}
