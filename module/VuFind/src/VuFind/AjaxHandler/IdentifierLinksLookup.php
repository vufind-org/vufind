<?php

/**
 * AJAX handler to look up identifier-based link data.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Psr\Http\Message\ServerRequestInterface;
use VuFind\Http\RouteHelper;
use VuFind\Http\ServerUrlHelper;
use VuFind\IdentifierLinker\PluginManager as LinkerPluginManager;
use VuFind\View\Helper\Root\Icon;
use VuFind\View\Renderer\TemplateRendererInterface;

use function count;

/**
 * AJAX handler to look up identifier-based link data.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class IdentifierLinksLookup extends AbstractBase
{
    /**
     * Identifier link resolver configuration value, exploded into an array of options.
     *
     * @var string[]
     */
    protected $resolvers;

    /**
     * Behavior to use when multiple resolvers find results for the same identifier set (may
     * be 'first' -- use first match, or 'merge' -- use all results).
     *
     * @var string
     */
    protected $multiMode;

    /**
     * Whether to load icons via the cover proxy.
     *
     * @var bool
     */
    protected $proxyIcons = false;

    /**
     * Whether to open links in a new window.
     *
     * @var bool
     */
    protected $openInNewWindow = false;

    /**
     * Constructor.
     *
     * @param LinkerPluginManager       $pluginManager   Identifier Linker Plugin Manager
     * @param TemplateRendererInterface $renderer        Template renderer
     * @param ServerUrlHelper           $serverUrlHelper Server URL helper
     * @param RouteHelper               $routeHelper     Route helper
     * @param Icon                      $iconHelper      Icon helper
     * @param array                     $config          Main configuration
     */
    public function __construct(
        protected LinkerPluginManager $pluginManager,
        protected TemplateRendererInterface $renderer,
        protected ServerUrlHelper $serverUrlHelper,
        protected RouteHelper $routeHelper,
        protected Icon $iconHelper,
        array $config
    ) {
        parent::__construct(null);
        // DOI config section is supported as a legacy fallback for back-compatibility:
        $idConfig = $config['IdentifierLinks'] ?? $config['DOI'] ?? [];
        $this->resolvers
            = array_map('trim', explode(',', $idConfig['resolver'] ?? ''));
        // Behavior to use when multiple resolvers to find results for the same
        // identifier set (may be 'first' -- use first match, or 'merge' -- use all
        // results):
        $this->multiMode
            = trim(strtolower($idConfig['multi_resolver_mode'] ?? 'first'));
        $this->proxyIcons = !empty($idConfig['proxy_icons']);
        $this->openInNewWindow = !empty($idConfig['new_window']);
    }

    /**
     * Handle a request.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(ServerRequestInterface $request): array
    {
        $gatheredData = [];
        $stream = $request->getBody();
        $stream->rewind();
        $ids = json_decode($stream->getContents(), true);
        foreach ($this->resolvers as $resolver) {
            if ($this->pluginManager->has($resolver)) {
                $next = $this->pluginManager->get($resolver)->getLinks($ids);
                $next = $this->processIconLinks($next);
                foreach ($next as $key => $data) {
                    if (!isset($gatheredData[$key])) {
                        $gatheredData[$key] = $data;
                    } elseif ($this->multiMode == 'merge') {
                        $gatheredData[$key] = array_merge($gatheredData[$key], $data);
                    }
                }
                // If all keys have been found and we're not in merge mode, we
                // can short circuit out of here.
                if (
                    $this->multiMode !== 'merge'
                    && count(array_diff(array_keys($ids), array_keys($gatheredData))) == 0
                ) {
                    break;
                }
            }
        }
        $response = array_map(fn ($data) => $this->renderResponseChunk($request, $data), $gatheredData);
        return $this->formatResponse($response);
    }

    /**
     * Render the links for a single record.
     *
     * @param ServerRequestInterface $request Request
     * @param array                  $data    Data to render
     *
     * @return string
     */
    protected function renderResponseChunk(ServerRequestInterface $request, array $data): string
    {
        $newWindow = $this->openInNewWindow;
        return $this->renderer->renderTemplateAsString(
            $request,
            'ajax/identifierLinks.phtml',
            compact('data', 'newWindow')
        );
    }

    /**
     * Proxify external icon links and render local icons.
     *
     * @param array $data Identifier plugin data
     *
     * @return array
     */
    protected function processIconLinks(array $data): array
    {
        foreach ($data as &$links) {
            foreach ($links as &$link) {
                if ($this->proxyIcons && !empty($link['icon'])) {
                    $link['icon'] = $this->serverUrlHelper->getUrlForPath(
                        $this->routeHelper->getUrlFromRoute(
                            'cover-show',
                            [],
                            ['proxy' => $link['icon']]
                        )
                    );
                }
                if (!empty($link['localIcon'])) {
                    $link['localIcon'] = ($this->iconHelper)($link['localIcon'], 'icon-link__icon');
                }
            }
            unset($link);
        }
        unset($links);
        return $data;
    }
}
