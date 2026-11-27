<?php

/**
 * Head link view helper.
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

use Stringable;

use function call_user_func_array;

/**
 * Head link view helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class HeadLink implements Stringable
{
    /**
     * Constructor.
     *
     * @param \Laminas\View\Helper\HeadLink $headLink Wrapped HeadLink helper
     */
    public function __construct(
        protected \Laminas\View\Helper\HeadLink $headLink,
    ) {
    }

    /**
     * Append an alternate link.
     *
     * @param string $href  URL
     * @param string $type  Media type
     * @param string $title Title
     *
     * @return void
     */
    public function appendAlternate(string $href, string $type, string $title): void
    {
        $rel = 'alternate';
        $this->headLink->append(compact('href', 'rel', 'type', 'title'));
    }

    /**
     * Proxy invocation to the wrapped helper.
     *
     * @param ?array $attributes Any attributes for a link to add
     *
     * @return static
     */
    public function __invoke(?array $attributes = null): static
    {
        if (null !== $attributes) {
            ($this->headLink)($attributes);
        }
        return $this;
    }

    /**
     * Convert to string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->headLink;
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
        return call_user_func_array([$this->headLink, $methodName], $params);
    }
}
