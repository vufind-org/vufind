<?php

/**
 * Custom element renderer
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

use Exception;
use Finna\CommonMark\Node\Block\CustomElement;
use Finna\View\CustomElement\AbstractCustomElementEnabledBase;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

/**
 * Custom element renderer
 *
 * @category VuFind
 * @package  CommonMark
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CustomElementRenderer extends AbstractCustomElementEnabledBase implements NodeRendererInterface
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
        if (!($node instanceof CustomElement)) {
            throw new \InvalidArgumentException(
                'Incompatible block type: ' . $node::class
            );
        }

        // Get child nodes. The last child node should always be the closing tag.
        $children = $node->children();
        $closingTag = array_pop($children);

        // Render the child nodes and if there is any content, add newlines to
        // separate it from the opening and closing tags.
        $childContent = $childRenderer->renderNodes($children);
        if ('' !== $childContent) {
            $childContent = "\n" . $childContent . "\n";
        }

        // Render the closing tag. If there is more than one line, there has been
        // additional content after the tag.
        $closingTagContent = $childRenderer->renderNodes([$closingTag]);
        $closingTagContent = explode("\n", $closingTagContent, 2);

        $elementContent = $node->getOpeningTag()
            . $childContent
            . $closingTagContent[0];

        try {
            $elementContent = $this->customElementRenderer->render(
                $node->getName(),
                ['outerHTML' => $elementContent]
            );
        } catch (Exception $e) {
            // If server-side rendering fails for some reason, just return the
            // element as is.
        }

        return $elementContent . ($closingTagContent[1] ?? '');
    }
}
