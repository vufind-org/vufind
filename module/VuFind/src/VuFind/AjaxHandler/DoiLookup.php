<?php

/**
 * AJAX handler to look up DOI data.
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

use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Renderer\RendererInterface;
use VuFind\DoiLinker\PluginManager;

use function count;

/**
 * AJAX handler to look up DOI data.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class DoiLookup extends AbstractBase
{
    /**
     * DOI Linker Plugin Manager
     *
     * @var PluginManager
     */
    protected $pluginManager;

    /**
     * DOI resolver configuration value, exploded into an array of options
     *
     * @var string[]
     */
    protected $resolvers;

    /**
     * Behavior to use when multiple resolvers find results for the same DOI (may
     * be 'first' -- use first match, or 'merge' -- use all results)
     *
     * @var string
     */
    protected $multiMode;

    /**
     * Whether to load icons via the cover proxy
     *
     * @var bool
     */
    protected $proxyIcons = false;

    /**
     * Whether to open links in a new window
     *
     * @var bool
     */
    protected $openInNewWindow = false;

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $viewRenderer = null;

    /**
     * Constructor
     *
     * @param PluginManager     $pluginManager DOI Linker Plugin Manager
     * @param RendererInterface $viewRenderer  View renderer
     * @param array             $config        Main configuration
     */
    public function __construct(
        PluginManager $pluginManager,
        RendererInterface $viewRenderer,
        array $config
    ) {
        $this->pluginManager = $pluginManager;
        $this->resolvers
            = array_map('trim', explode(',', $config['DOI']['resolver'] ?? ''));
        // Behavior to use when multiple resolvers to find results for the same
        // DOI (may be 'first' -- use first match, or 'merge' -- use all
        // results):
        $this->multiMode
            = trim(strtolower($config['DOI']['multi_resolver_mode'] ?? 'first'));
        $this->proxyIcons = !empty($config['DOI']['proxy_icons']);
        $this->openInNewWindow = !empty($config['DOI']['new_window']);
        $this->viewRenderer = $viewRenderer;
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
        $response = [];
        $dois = (array)$params->fromQuery('doi', []);
        foreach ($this->resolvers as $resolver) {
            if ($this->pluginManager->has($resolver)) {
                $next = $this->pluginManager->get($resolver)->getLinks($dois);
                $next = $this->processIconLinks($next);
                foreach ($next as $doi => $data) {
                    foreach ($data as &$current) {
                        $current['newWindow'] = $this->openInNewWindow;
                    }
                    unset($current);
                    if (!isset($response[$doi])) {
                        $response[$doi] = $data;
                    } elseif ($this->multiMode == 'merge') {
                        $response[$doi] = array_merge($response[$doi], $data);
                    }
                }
                // If all DOIs have been found and we're not in merge mode, we
                // can short circuit out of here.
                if (
                    $this->multiMode !== 'merge'
                    && count(array_diff($dois, array_keys($response))) == 0
                ) {
                    break;
                }
            }
        }
        return $this->formatResponse($response);
    }

    /**
     * Proxify external DOI icon links and render local icons
     *
     * @param array $dois DOIs
     *
     * @return array
     */
    protected function processIconLinks(array $dois): array
    {
        $serverHelper = $this->viewRenderer->plugin('serverurl');
        $urlHelper = $this->viewRenderer->plugin('url');
        $iconHelper = $this->viewRenderer->plugin('icon');

        foreach ($dois as &$doiLinks) {
            foreach ($doiLinks as &$doi) {
                if ($this->proxyIcons && !empty($doi['icon'])) {
                    $doi['icon'] = $serverHelper(
                        $urlHelper(
                            'cover-show',
                            [],
                            ['query' => ['proxy' => $doi['icon']]]
                        )
                    );
                }
                if (!empty($doi['localIcon'])) {
                    $doi['localIcon'] = $iconHelper($doi['localIcon']);
                }
            }
            unset($doi);
        }
        unset($doiLinks);
        return $dois;
    }
}
