<?php

/**
 * Head title view helper.
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

use function call_user_func_array;

/**
 * Head title view helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class HeadTitle
{
    /**
     * Constructor.
     *
     * @param \Laminas\View\Helper\HeadTitle $headTitle Wrapped HeadTitle helper
     */
    public function __construct(
        protected \Laminas\View\Helper\HeadTitle $headTitle,
    ) {
    }

    /**
     * Set a title.
     *
     * Differs from the Laminas' implementation in that it appends the title to any existing one while this one
     * overwrites it.
     *
     * @param ?string $title Title
     *
     * @return static
     */
    public function __invoke(?string $title = null): static
    {
        if ($title !== null && $title !== '') {
            $this->headTitle->set($title);
        }
        return $this;
    }

    /**
     * Return the rendered title unescaped.
     *
     * @return string
     */
    public function renderUnescapedTitle(): string
    {
        // This isn't beautiful, but the wrapped helper doesn't allow any other way.
        return htmlspecialchars_decode($this->headTitle->renderTitle());
    }

    /**
     * By default, proxy method calls to the wrapped helper.
     *
     * @param string $methodName The name of the called method
     * @param array  $params     Array of passed parameters
     *
     * @return mixed
     */
    public function __call(string $methodName, array $params): mixed
    {
        return call_user_func_array([$this->headTitle, $methodName], $params);
    }
}
