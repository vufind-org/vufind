<?php

/**
 * Custom element Markdown extension
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
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\CommonMark\Extension;

use Finna\CommonMark\Node\Block\CustomElement;
use Finna\CommonMark\Node\Block\CustomElementClosingTag;
use Finna\CommonMark\Parser\Block\CustomElementClosingTagStartParser;
use Finna\CommonMark\Parser\Block\CustomElementStartParser;
use Finna\CommonMark\Renderer\Block\CustomElementClosingTagRenderer;
use Finna\CommonMark\Renderer\Block\CustomElementRenderer;
use Finna\View\CustomElement\AbstractCustomElementEnabledBase;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;

/**
 * Custom element Markdown extension
 *
 * The implementation uses a separate block parser for custom element closing tags
 * to enable regular Markdown processing for element contents.
 *
 * Limitations:
 * - Custom element opening and closing tags must be on their own lines with nothing
 *   else on the same line.
 * - The only exception is an empty custom element, which can have its opening and
 *   closing tags on the same line.
 *
 * @category VuFind
 * @package  CommonMark
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CustomElementExtension extends AbstractCustomElementEnabledBase implements ExtensionInterface
{
    /**
     * Register the extension.
     *
     * @param EnvironmentBuilderInterface $environment Environment
     *
     * @return void
     */
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addBlockStartParser(
            new CustomElementStartParser($this->customElements),
            100
        );
        $environment->addBlockStartParser(
            new CustomElementClosingTagStartParser(),
            100
        );
        $environment->addRenderer(
            CustomElement::class,
            new CustomElementRenderer(
                $this->customElements,
                $this->customElementRenderer
            )
        );
        $environment->addRenderer(
            CustomElementClosingTag::class,
            new CustomElementClosingTagRenderer()
        );
    }
}
