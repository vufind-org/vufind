<?php

/**
 * Breadcrumb trail view helper.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Renderer\RendererInterface;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\GlobalsContainer;

/**
 * Breadcrumb trail view helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Breadcrumbs
{
    /**
     * Constructor.
     *
     * @param RendererInterface $view             View renderer
     * @param GlobalsContainer  $globalsContainer Global data container
     */
    public function __construct(
        protected RendererInterface $view,
        #[Autowire]
        protected GlobalsContainer $globalsContainer
    ) {
    }

    /**
     * Format a single breadcrumb.
     *
     * @param string  $text   Text of breadcrumb
     * @param ?string $href   Link of breadcrumb (null for no link)
     * @param bool    $active Is this the active breadcrumb (end of trail)?
     *
     * @return string
     */
    protected function formatBreadcrumb(string $text, ?string $href = null, bool $active = false): string
    {
        return $this->view->render('Helpers/breadcrumbs/single', compact('text', 'href', 'active'));
    }

    /**
     * Get the object containing breadcrumb variables.
     *
     * @return GlobalsContainer
     */
    protected function getContainer(): GlobalsContainer
    {
        return $this->globalsContainer;
    }

    /**
     * Append a breadcrumb to the end of the trail.
     *
     * @param string  $text   Text of breadcrumb
     * @param ?string $href   Link of breadcrumb (null for no link)
     * @param bool    $active Is this the active breadcrumb (end of trail)?
     *
     * @return static
     */
    public function add(string $text, ?string $href = null, bool $active = false): static
    {
        $this->getContainer()['breadcrumbs'] .= $this->formatBreadcrumb($text, $href, $active);
        return $this;
    }

    /**
     * Disable breadcrumbs.
     *
     * @return static
     */
    public function disable(): static
    {
        $this->getContainer()['breadcrumbs'] = false;
        return $this;
    }

    /**
     * Prepend a breadcrumb to the beginning of the trail.
     *
     * @param string  $text   Text of breadcrumb
     * @param ?string $href   Link of breadcrumb (null for no link)
     * @param bool    $active Is this the active breadcrumb (end of trail)?
     *
     * @return static
     */
    public function prepend(string $text, ?string $href = null, bool $active = false): static
    {
        $this->getContainer()['breadcrumbs'] = $this->formatBreadcrumb($text, $href, $active)
            . $this->getContainer()['breadcrumbs'];
        return $this;
    }

    /**
     * Render the full breadcrumb region.
     *
     * @return string
     */
    public function render(): string
    {
        $container = $this->getContainer();
        $active = ($container['showBreadcrumbs'] ?? true) && $container['breadcrumbs'] !== false;
        $breadcrumbs = $active ? $container['breadcrumbs'] : '';
        return $this->view->render('Helpers/breadcrumbs/all', compact('active', 'breadcrumbs'));
    }

    /**
     * Reset the breadcrumb trail to an empty list.
     *
     * @return static
     */
    public function reset(): static
    {
        $this->getContainer()['breadcrumbs'] = '';
        return $this;
    }

    /**
     * Reset the breadcrumb trail to contain the single specified breadcrumb.
     *
     * @param string  $text   Text of breadcrumb
     * @param ?string $href   Link of breadcrumb (null for no link)
     * @param bool    $active Is this the active breadcrumb (end of trail)?
     *
     * @return static
     */
    public function set(string $text, ?string $href = null, bool $active = false): static
    {
        $this->getContainer()['breadcrumbs'] = $this->formatBreadcrumb($text, $href, $active);
        return $this;
    }

    /**
     * Make helper invokable.
     *
     * @return static
     */
    public function __invoke(): static
    {
        return $this;
    }
}
