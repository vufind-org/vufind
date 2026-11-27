<?php

/**
 * Render a template in root (main template) context.
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
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Renderer\TemplateRendererInterface;

/**
 * Render a template in root (main template) context.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Render
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
     * Render a template.
     *
     * @param string $template Template
     * @param array  $params   Additional parameters
     *
     * @return string
     */
    public function __invoke(string $template, array $params = []): string
    {
        return $this->templateRenderer->renderTemplateAsString(template: $template, params: $params, rootContext: true);
    }
}
