<?php

/**
 * View helper for escaping or cleaning HTML
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024-2025.
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
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\Escaper\Escaper;
use Laminas\View\Helper\AbstractHelper;
use VuFind\String\PropertyStringInterface;

/**
 * View helper for escaping or cleaning HTML
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class EscapeOrCleanHtml extends AbstractHelper
{
    /**
     * Contexts that allow HTML
     *
     * @var array
     */
    protected array $htmlContexts;

    /**
     * Constructor
     *
     * @param Escaper   $escaper   Escaper
     * @param CleanHtml $cleanHtml Clean HTML helper
     * @param array     $config    VuFind configuration
     */
    public function __construct(protected Escaper $escaper, protected CleanHtml $cleanHtml, array $config)
    {
        $this->htmlContexts = (array)($config['Allowed_HTML_Contexts'] ?? []);
    }

    /**
     * Invoke this helper: escape a value
     *
     * @param string|PropertyStringInterface $value            Value to escape
     * @param ?string                        $dataContext      Data context (for fields that allow sanitized HTML)
     * @param ?bool                          $allowHtml        Whether to allow sanitized HTML if passed a
     * PropertyString
     * @param string                         $renderingContext Rendering context for cleaning HTML
     *
     * @return mixed Returns an escaped or HTML-safe string
     */
    public function __invoke(
        string|PropertyStringInterface $value,
        ?string $dataContext = null,
        ?bool $allowHtml = null,
        string $renderingContext = 'default'
    ) {
        if ($value instanceof PropertyStringInterface) {
            if (
                ($allowHtml ?? ($dataContext && ($this->htmlContexts[$dataContext] ?? false)))
                && $html = $value->getHtml()
            ) {
                return $value->isHtmlTrusted() ? $html : ($this->cleanHtml)($html, context: $renderingContext);
            }
            $value = (string)$value;
        }
        return $this->escape($value);
    }

    /**
     * Escape a string
     *
     * @param string $value String to escape
     *
     * @return string
     */
    protected function escape($value)
    {
        return $this->escaper->escapeHtml($value);
    }
}
