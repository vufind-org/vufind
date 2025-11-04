<?php

/**
 * Abstract base class for description providers.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @package  Content
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Content\Description;

use Laminas\Http\Response;

/**
 * Abstract base class for description providers.
 *
 * @category VuFind
 * @package  Content
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractDescriptionProvider implements DescriptionProviderInterface
{
    /**
     * Process a response
     *
     * @param Response $response HTTP response
     *
     * @return string Ready-to-display HTML or an empty string on error
     */
    protected function processResponse(Response $response): string
    {
        if (!($content = $response->getBody())) {
            return '';
        }
        $contentType = $response->getHeaders()->get('Content-Type');
        if ($contentType instanceof \Laminas\Http\Header\ContentType) {
            $encoding = strtoupper($contentType->getCharset());
        } else {
            $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1']);
        }
        if ('UTF-8' !== $encoding) {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        // Remove head tag, so no titles will be printed.
        $content = preg_replace(
            '/<head[^>]*>(.*?)<\/head>/si',
            '',
            $content
        );

        $content = preg_replace('/.*<.B>(.*)/', '\1', $content);
        $content = strip_tags($content, '<br><p>');

        // Trim leading and trailing whitespace
        $content = trim($content);

        // Trim any leading and trailing empty paragraphs (repeating and possibly containing NBSP in UTF-8)
        $content = preg_replace('{^(<p>\s*</p>\s*)*}u', '', $content);
        $content = preg_replace('{(\s*<p>\s*</p>)*$}u', '', $content);

        // Replace line breaks with <br>
        $content = preg_replace(
            '/(\r\n|\n|\r){3,}/',
            '<br><br>',
            $content
        );

        return trim($content);
    }
}
