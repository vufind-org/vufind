<?php

/**
 * Breadcrumb trail view helper
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

use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Model\ViewModel;

/**
 * Breadcrumb trail view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Breadcrumbs extends AbstractHelper
{
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
        return $this->getView()->render('Helpers/breadcrumbs/single', compact('text', 'href', 'active'));
    }

    /**
     * Get the layout object containing breadcrumb variables.
     *
     * @return ViewModel
     */
    protected function getLayout(): ViewModel
    {
        return ($this->getView()->plugin('layout'))();
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
        $this->getLayout()->breadcrumbs .= $this->formatBreadcrumb($text, $href, $active);
        return $this;
    }

    /**
     * Disable breadcrumbs.
     *
     * @return static
     */
    public function disable(): static
    {
        $this->getLayout()->breadcrumbs = false;
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
        $this->getLayout()->breadcrumbs = $this->formatBreadcrumb($text, $href, $active)
            . $this->getLayout()->breadcrumbs;
        return $this;
    }

    /**
     * Render the full breadcrumb region.
     *
     * @return string
     */
    public function render(): string
    {
        $layout = $this->getLayout();
        $active = ($layout->showBreadcrumbs ?? true) && $layout->breadcrumbs !== false;
        $breadcrumbs = $active ? $layout->breadcrumbs : '';
        return $this->getView()->render('Helpers/breadcrumbs/all', compact('active', 'breadcrumbs'));
    }

    /**
     * Reset the breadcrumb trail to an empty list.
     *
     * @return static
     */
    public function reset(): static
    {
        $this->getLayout()->breadcrumbs = '';
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
        $this->getLayout()->breadcrumbs = $this->formatBreadcrumb($text, $href, $active);
        return $this;
    }
}
