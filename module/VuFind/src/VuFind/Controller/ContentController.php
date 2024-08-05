<?php

/**
 * Content Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2014-2024.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller;

use Laminas\View\Model\ViewModel;

use function is_callable;

/**
 * Controller for mostly static pages that doesn't fall under any particular
 * function.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ContentController extends AbstractBase
{
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
     * Default action if none provided
     *
     * @return ViewModel
     */
    public function contentAction()
    {
        $pathPrefix = 'templates/content/';
        $page = $this->params()->fromRoute('page');
        // Path regex should prevent dots, but double-check to make sure:
        if (str_contains($page, '..')) {
            return $this->notFoundAction();
        }
        // Find last slash and add preceding part to path if found:
        if (false !== ($p = strrpos($page, '/'))) {
            $subPath = substr($page, 0, $p + 1);
            $pathPrefix .= $subPath;
            // Ensure the the path prefix does not contain extra slashes:
            if (str_ends_with($pathPrefix, '//')) {
                return $this->notFoundAction();
            }
            $page = substr($page, $p + 1);
        }
        $pageLocator = $this->getService(\VuFind\Content\PageLocator::class);
        $data = $pageLocator->determineTemplateAndRenderer($pathPrefix, $page);

        $method = isset($data) ? 'getViewFor' . ucwords($data['renderer']) : false;

        return $method && is_callable([$this, $method])
            ? $this->$method($data['page'], $data['relativePath'], $data['path'])
            : $this->notFoundAction();
    }

    /**
     * Get ViewModel for markdown based page
     *
     * @param string $page    Page name/route (if applicable)
     * @param string $relPath Relative path to file with content (if applicable)
     * @param string $path    Full path to file with content (if applicable)
     *
     * @return ViewModel
     */
    protected function getViewForMd(string $page, string $relPath, string $path): ViewModel
    {
        $view = $this->createViewModel(['data' => file_get_contents($path)]);
        $view->setTemplate('content/markdown');
        return $view;
    }

    /**
     * Get ViewModel for phtml base page
     *
     * @param string $page    Page name/route (if applicable)
     * @param string $relPath Relative path to file with content (if applicable)
     * @param string $path    Full path to file with content (if applicable)
     *
     * @return ViewModel
     */
    protected function getViewForPhtml(string $page, string $relPath, string $path): ViewModel
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
            return $this->notFoundAction();
        }
        return $this->createViewModel(['page' => $relPage]);
    }
}
