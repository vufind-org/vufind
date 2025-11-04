<?php

/**
 * Record field Markdown extension
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
 * @package  CommonMark
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace Finna\CommonMark\Extension;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Parser\Inline\EntityParser;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\NewlineParser;
use League\CommonMark\Renderer\Block\DocumentRenderer;
use League\CommonMark\Renderer\Block\ParagraphRenderer;
use League\CommonMark\Renderer\Inline\NewlineRenderer;
use League\CommonMark\Renderer\Inline\TextRenderer;

/**
 * Record field Markdown extension
 *
 * @category VuFind
 * @package  VuFind\Service
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
final class RecordFieldMarkdownExtension implements ExtensionInterface
{
    /**
     * Register the extension
     *
     * @param EnvironmentBuilderInterface $environment Enviroment
     *
     * @return void
     */
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addInlineParser(new NewlineParser(), 200)
            ->addInlineParser(new EntityParser(), 70)

            ->addRenderer(Document::class, new DocumentRenderer(), 0)
            ->addRenderer(Paragraph::class, new ParagraphRenderer(), 0)
            ->addRenderer(Newline::class, new NewlineRenderer(), 0)
            ->addRenderer(Text::class, new TextRenderer(), 0);
    }
}
