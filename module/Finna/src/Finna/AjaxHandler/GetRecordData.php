<?php

/**
 * "Get Record Data" AJAX handler
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
 * Copyright (C) The National Library of Finland 2021.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\AjaxHandler;

use Laminas\Http\PhpEnvironment\Request;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Renderer\RendererInterface;
use VuFind\Record\Loader;

/**
 * "Get Record Data" AJAX handler
 *
 * Get record data elements.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetRecordData extends \VuFind\AjaxHandler\AbstractBase
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
     * @param RendererInterface $renderer Renderer
     */
    public function __construct(
        array $config,
        Request $request,
        Loader $loader,
        RendererInterface $renderer
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->recordLoader = $loader;
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
        $requestedData = $params->fromQuery('data');

        if ('onlineUrls' === $requestedData) {
            $driver = $this->recordLoader
                ->load($params->fromQuery('id'), $params->fromQuery('source'));
            $recordHelper = $this->renderer->plugin('record');
            $html = $recordHelper($driver)->getOnlineUrls('results');
        } else {
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }

        return $this->formatResponse(compact('html'));
    }
}
