<?php

/**
 * Content Action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2014-2026.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
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
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Action\Content;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\Content\PageLocator;
use VuFind\ServiceManager\Factory\Autowire;

use function is_callable;

/**
 * Action for mostly static pages that doesn't fall under any particular function.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ContentAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param PageLocator $pageLocator Page locator
     */
    #[Autowire()]
    public function __construct(
        protected PageLocator $pageLocator,
    ) {
        parent::__construct();
    }

    /**
     * Types/formats of content
     *
     * @var array $types
     */
    protected $types = [
        'phtml',
        'md',
    ];

    /**
     * Resolve full version of shortlink & redirect to target.
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
        $pathPrefix = 'templates/content/';
        if (!($page = $this->getRouteParam('page'))) {
            throw new InvalidArgumentException("Route param 'page' missing");
        }
        // Path regex should prevent dots, but double-check to make sure:
        if (str_contains($page, '..')) {
            return $this->renderNotFoundPage($request, $response);
        }
        // Find last slash and add preceding part to path if found:
        if (false !== ($p = strrpos($page, '/'))) {
            $subPath = substr($page, 0, $p + 1);
            $pathPrefix .= $subPath;
            // Ensure the path prefix does not contain extra slashes:
            if (str_ends_with($pathPrefix, '//')) {
                return $this->renderNotFoundPage($request, $response);
            }
            $page = substr($page, $p + 1);
        }
        $data = $this->pageLocator->determineTemplateAndRenderer($pathPrefix, $page);

        $method = isset($data) ? 'getViewFor' . ucwords($data['renderer']) : false;

        return $method && is_callable([$this, $method])
            ? $this->$method($data['page'], $data['relativePath'], $data['path'])
            : $this->renderNotFoundPage($request, $response);
    }

    /**
     * Get response for markdown based page
     *
     * @param string $page    Page name/route (if applicable)
     * @param string $relPath Relative path to file with content (if applicable)
     * @param string $path    Full path to file with content (if applicable)
     *
     * @return ResponseInterface
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function getViewForMd(string $page, string $relPath, string $path): ResponseInterface
    {
        return $this->renderTemplate(
            $this->request,
            $this->response,
            ['data' => file_get_contents($path)],
            'content/markdown'
        );
    }

    /**
     * Get ViewModel for phtml base page
     *
     * @param string $page    Page name/route (if applicable)
     * @param string $relPath Relative path to file with content (if applicable)
     * @param string $path    Full path to file with content (if applicable)
     *
     * @return ResponseInterface
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function getViewForPhtml(string $page, string $relPath, string $path): ResponseInterface
    {
        // Convert relative path to a relative page name:
        $relPage = $relPath;
        if (str_starts_with($relPage, 'content/')) {
            $relPage = substr($relPage, 8);
        }
        if (str_ends_with($relPage, '.phtml')) {
            $relPage = substr($relPage, 0, -6);
        }
        // Prevent circular inclusion:
        if ('content' === $relPage) {
            return $this->renderNotFoundPage($this->request, $this->response);
        }
        return $this->renderTemplate(
            $this->request,
            $this->response,
            ['page' => $relPage]
        );
    }
}
