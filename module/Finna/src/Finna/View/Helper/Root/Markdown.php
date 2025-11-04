<?php

/**
 * Markdown view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2022.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\View\Helper\Root;

use League\CommonMark\Util\RegexHelper;

/**
 * Markdown view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Markdown extends \VuFind\View\Helper\Root\Markdown
{
    /**
     * Return HTML.
     *
     * @param string $markdown              Markdown
     * @param bool   $replaceDeprecatedTags Replace deprecated tags?
     *
     * @return string
     */
    public function toHtml(
        string $markdown,
        bool $replaceDeprecatedTags = true
    ): string {
        if ($replaceDeprecatedTags) {
            $markdown = $this->replaceDeprecatedTags($markdown);
        } else {
            // Fix for deprecated tags.
            $markdown = str_replace('</summary>', "</summary>\n", $markdown);
        }

        // Clean HTML while in Markdown format, since HTML from server-side rendered
        // custom tags should not be cleaned.
        $cleanHtml = $this->getView()->plugin('cleanHtml');
        $text = $this->converter->convert($cleanHtml($markdown, true));

        // Adjust heading level by +1.
        $text = $this->getView()->plugin('adjustHeadingLevel')($text, 1);

        if (!$replaceDeprecatedTags) {
            // Fix for Markdown processed deprecated tags.
            $text = str_replace('<p><br /></p>', '', $text);
        }

        return $text;
    }

    /**
     * Converts markdown to html
     *
     * Finna: back-compatibility with default param and call logic
     *
     * @param ?string $markdown Markdown formatted text
     *
     * @return string
     */
    public function __invoke(?string $markdown = null)
    {
        return null === $markdown ? $this : parent::__invoke($markdown);
    }

    /**
     * Replace deprecated tags with supported ones.
     *
     * @param string $markdown Markdown formatted text
     *
     * @return string
     */
    public function replaceDeprecatedTags(string $markdown): string
    {
        // Replace details elements.
        $markdown = preg_replace(
            $this->getTagContentRegex('details'),
            "<finna-panel>\n$1\n</finna-panel>\n",
            $markdown
        );

        // Replace details > summary elements, which have the markdown attribute.
        $markdown = preg_replace_callback(
            $this->getTagContentRegex('summary', ' markdown="1"'),
            function ($matches) {
                $heading = str_replace('**', '', $matches[1]);
                return "  <h3 slot=\"heading\">$heading</h3>\n\n";
            },
            $markdown
        );

        // Replace truncate elements.
        $markdown = preg_replace(
            $this->getTagContentRegex('truncate'),
            "<finna-truncate>\n$1\n</finna-truncate>\n",
            $markdown
        );

        // Replace truncate > summary elements, which do not have the markdown
        // attribute.
        $markdown = preg_replace(
            $this->getTagContentRegex('summary'),
            "  <span slot=\"label\">$1</span>\n",
            $markdown
        );

        return $markdown;
    }

    /**
     * Get tag content capturing regex.
     *
     * @param string $tagName    Tag name
     * @param string $attributes Tag attributes (optional)
     *
     * @return string
     */
    protected function getTagContentRegex(
        string $tagName,
        string $attributes = RegexHelper::PARTIAL_ATTRIBUTE . '*'
    ): string {
        return '/<' . $tagName . $attributes . '\s*>(.*?)'
            . '<\/' . $tagName . '\s*[>]/s';
    }
}
