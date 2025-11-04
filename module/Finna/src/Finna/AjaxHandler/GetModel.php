<?php

/**
 * 3D model ajax handler.
 *
 * PHP version 8
 *
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
 * @package  Controller
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\AjaxHandler;

use Finna\File\Loader as FileLoader;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Router\Http\TreeRouteStack;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Session\Settings as SessionSettings;
use VuFind\View\Helper\Root\Url;

/**
 * GetModel AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetModel extends \VuFind\AjaxHandler\AbstractBase implements \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Session settings
     *
     * @var Settings
     */
    protected $sessionSettings;

    /**
     * Loader
     *
     * @var RecordLoader
     */
    protected $recordLoader;

    /**
     * File loader
     *
     * @var Loader
     */
    protected $fileLoader;

    /**
     * Domain url
     *
     * @var Url
     */
    protected $urlHelper;

    /**
     * Router
     *
     * @var \Laminas\Router\Http\TreeRouteStack
     */
    protected $router;

    /**
     * Constructor
     *
     * @param SessionSettings $ss           Session settings
     * @param RecordLoader    $recordLoader Recordloader
     * @param Url             $urlHelper    Url helper
     * @param FileLoader      $fileLoader   Fileloader
     * @param Router          $router       Router
     */
    public function __construct(
        SessionSettings $ss,
        RecordLoader $recordLoader,
        Url $urlHelper,
        FileLoader $fileLoader,
        TreeRouteStack $router
    ) {
        $this->sessionSettings = $ss;
        $this->recordLoader = $recordLoader;
        $this->urlHelper = $urlHelper;
        $this->fileLoader = $fileLoader;
        $this->router = $router;
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
        $this->disableSessionWrites();  // avoid session write timing bug

        $id = $params->fromPost('id', $params->fromQuery('id'));
        $index = $params->fromPost('index', $params->fromQuery('index'));
        $format = $params->fromPost('format', $params->fromQuery('format'));
        $source = $params->fromPost('source', $params->fromQuery('source'));

        if (!$id || !isset($index) || !$format) {
            return $this->formatResponse(
                ['json' => ['status' => self::STATUS_HTTP_BAD_REQUEST]]
            );
        }
        $format = strtolower($format);
        $fileName = urlencode($id) . '-' . $index . '.' . $format;
        $driver = $this->recordLoader->load($id, $source ?? DEFAULT_SEARCH_BACKEND);
        $models = $driver->tryMethod('getModels')[$index]['models'] ?? [];
        $found = array_search('preview', array_column($models, 'type'));
        if (false === $found) {
            return $this->formatResponse(['json' => ['status' => '404']]);
        }
        // Always force preview model to be fetched
        $url = $models[$found]['url'];
        // Use fileloader for proxies
        $file = $this->fileLoader->getFile($url, $fileName, 'Models', 'public');
        if (!empty($file['result'])) {
            $route = stripslashes($this->router->getBaseUrl());
            // Point url to public cache so viewer has access to it
            $url = ($this->urlHelper)('home') . 'cache/' . $fileName;
            return $this->formatResponse(compact('url'));
        } else {
            return $this->formatResponse(
                ['status' => self::STATUS_HTTP_ERROR]
            );
        }
    }
}
