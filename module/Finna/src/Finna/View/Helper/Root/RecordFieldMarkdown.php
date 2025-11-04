<?php

/**
 * Record field Markdown view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021-2022.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

/**
 * Record field Markdown view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RecordFieldMarkdown extends \VuFind\View\Helper\Root\Markdown
{
    /**
     * Return HTML
     *
     * @param string  $markdown  Markdown
     * @param ?string $softBreak Alternative string to use for rendering soft breaks
     *                           (optional)
     *
     * @return string
     */
    public function toHtml(string $markdown, ?string $softBreak = null): string
    {
        $cleanHtml = $this->getView()->plugin('cleanHtml');
        $cleanMarkdown = $cleanHtml($markdown);
        try {
            return (string)$this->converter->convert($cleanMarkdown, $softBreak);
        } catch (\Exception $e) {
            return $cleanMarkdown;
        }
    }

    /**
     * Converts Markdown to HTML
     *
     * Finna: back-compatibility with default param and call logic
     *
     * @param ?string $markdown Markdown formatted text
     *
     * @return RecordFieldMarkdown|string
     */
    public function __invoke(?string $markdown = null)
    {
        return null === $markdown ? $this : parent::__invoke($markdown);
    }
}
