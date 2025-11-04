<?php

/**
 * Custom element closing tag renderer
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

namespace Finna\CommonMark\Renderer\Block;

use Finna\CommonMark\Node\Block\CustomElementClosingTag;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

/**
 * Custom element closing tag renderer
 *
 * @category VuFind
 * @package  CommonMark
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CustomElementClosingTagRenderer implements NodeRendererInterface
{
    /**
     * Render the node.
     *
     * @param Node                       $node          Node
     * @param ChildNodeRendererInterface $childRenderer Child node renderer
     *
     * @return string|\Stringable|null
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer)
    {
        if (!($node instanceof CustomElementClosingTag)) {
            throw new \InvalidArgumentException(
                'Incompatible block type: ' . $node::class
            );
        }

        $remainingContent = $childRenderer->renderNodes($node->children());
        if ('' !== $remainingContent) {
            $remainingContent = "\n" . $remainingContent;
        }

        return $node->getClosingTag() . $remainingContent;
    }
}
