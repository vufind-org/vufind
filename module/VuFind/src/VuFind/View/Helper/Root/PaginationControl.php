<?php

/**
 * Pagination control view helper.
 *
 * PHP version 8
 *
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
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

declare(strict_types=1);

namespace VuFind\View\Helper\Root;

use Laminas\Paginator\Paginator;
use Laminas\View\Helper\Partial;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Renderer\TemplateRendererInterface;

use function array_merge;
use function get_object_vars;

/**
 * Pagination control view helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class PaginationControl
{
    /**
     * Constructor.
     *
     * @param TemplateRendererInterface $templateRenderer Template renderer
     */
    #[Autowire]
    public function __construct(
        protected TemplateRendererInterface $templateRenderer,
    ) {
    }

    /**
     * Render the provided pages.  This checks if $view->paginator is set and,
     * if so, uses that.  Also, if no scrolling style or partial are specified,
     * the defaults will be used (if set).
     *
     * @param Paginator $paginator      Paginator
     * @param string    $scrollingStyle Scrolling style
     * @param string    $partial        View partial
     * @param array     $params         Params to pass to the partial
     *
     * @return string
     */
    public function __invoke(
        Paginator $paginator,
        string $scrollingStyle,
        string $partial,
        array|string|null $params = []
    ) {
        $pages = get_object_vars($paginator->getPages($scrollingStyle));
        $params = array_merge($pages, $params);
        return $this->templateRenderer->renderTemplateAsString(template: $partial, params: $params);
    }
}
