<?php
/**
 * Make tag view helper
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2020.
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
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\View\Helper\Root;

/**
 * Make tag view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class MakeTag extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * List of all valid body tags
     *
     * Source: https://html.spec.whatwg.org/multipage/dom.html#flow-content-2
     *
     * @var string[]
     */
    protected $validBodyTags = [
        'a',
        'abbr',
        'address',
        'area',
        'article',
        'aside',
        'audio',
        'b',
        'bdi',
        'bdo',
        'blockquote',
        'br',
        'button',
        'canvas',
        'cite',
        'code',
        'data',
        'datalist',
        'del',
        'details',
        'dfn',
        'dialog',
        'div',
        'dl',
        'em',
        'embed',
        'fieldset',
        'figure',
        'footer',
        'form',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'header',
        'hgroup',
        'hr',
        'i',
        'iframe',
        'img',
        'input',
        'ins',
        'kbd',
        'label',
        'main',
        'map',
        'mark',
        'math',
        'menu',
        'meta',
        'meter',
        'nav',
        'noscript',
        'object',
        'ol',
        'output',
        'p',
        'picture',
        'pre',
        'progress',
        'q',
        'ruby',
        's',
        'samp',
        'script',
        'section',
        'select',
        'slot',
        'small',
        'span',
        'strong',
        'sub',
        'sup',
        'svg',
        'template',
        'textarea',
        'time',
        'u',
        'ul',
        'var',
        'video',
        'wbr',
    ];

    /**
     * List of all void tags (tags that access no innerHTML)
     *
     * Source: https://html.spec.whatwg.org/multipage/syntax.html#void-elements
     *
     * @var string[]
     */
    protected $voidElements = [
        'area',
        'base',
        'br',
        'col',
        'embed',
        'hr',
        'img',
        'input',
        'link',
        'meta',
        'param',
        'source',
        'track',
        'wbr'
    ];

    /**
     * Render an HTML tag
     *
     * A string passed into $attrs will be treated like a class.
     * These two are equivalent:
     * > MakeTag('div', 'Success!', 'alert alert-success')
     * > MakeTag('div', 'Success!', ['class => 'alert alert-success'])
     *
     * Additional options
     * - escapeContent: Default true, set to false to skip escaping (like for HTML).
     *
     * @param string       $tagName  Element tag name
     * @param string       $contents Element contents (must be properly-formed HTML)
     * @param string|array $attrs    Tag attributes (associative array or class name)
     * @param array        $options  Additional options
     *
     * @return string HTML for an anchor tag
     */
    public function __invoke(
        string $tagName,
        string $contents,
        $attrs = [],
        $options = []
    ) {
        // $attrs not an object, interpret as class name
        if (!is_array($attrs)) {
            $attrs = !empty($attrs) ? ['class' => $attrs] : [];
        }

        // Compile attributes
        return $this->compileTag($tagName, $contents, $attrs, $options);
    }

    /**
     * Verify HTML tag matches HTML spec
     *
     * @param string $tagName Element tag name
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function verifyTagName(string $tagName)
    {
        // Simplify check by making tag lowercase
        $lowerTagName = mb_strtolower($tagName, 'UTF-8');

        // Existing tag?
        if (in_array($lowerTagName, $this->validBodyTags)) {
            return;
        }

        // Check if it's a valid custom element
        // Spec: https://html.spec.whatwg.org/#autonomous-custom-element

        // All valid characters for a Potential Custom Element Name
        // Concated for clarity (space not a valid character)
        $PCENChar = '[' .
            '\-\.0-9_a-z' .
            '\x{B7}' .
            '\x{C0}-\x{D6}' .
            '\x{D8}-\x{F6}' .
            '\x{F8}-\x{37D}' .
            '\x{37F}-\x{1FFF}' .
            '\x{200C}-\x{200D}' .
            '\x{203F}-\x{2040}' .
            '\x{2070}-\x{218F}' .
            '\x{2C00}-\x{2FEF}' .
            '\x{3001}-\x{D7FF}' .
            '\x{F900}-\x{FDCF}' .
            '\x{FDF0}-\x{FFFD}' .
            '\x{10000}-\x{EFFFF}' .
            ']*';

        // First character must be a letter (uppercase or lowercase)
        // Needs one hyphen to designate custom element, more groups valid
        $validCustomTagPattern = '/^[a-z]' . $PCENChar . '(\-' . $PCENChar . ')+$/u';

        // Is valid custom tag?
        if (!preg_match($validCustomTagPattern, $lowerTagName)) {
            throw new \InvalidArgumentException('Invalid tag name: ' . $tagName);
        }
    }

    /**
     * Turn associative array into a string of attributes in an anchor
     *
     * Additional options
     * - escapeContent: Default true, set to false to skip escaping (like for HTML).
     *
     * @param string $tagName  HTML tag name
     * @param string $contents InnerHTML
     * @param array  $attrs    Tag attributes (associative array)
     * @param array  $options  Additional options
     *
     * @return string
     * @throws InvalidArgumentException
     */
    protected function compileTag(
        string $tagName,
        string $contents,
        $attrs = [],
        $options = []
    ) {
        $this->verifyTagName($tagName);

        $htmlAttrs = $this->getView()->plugin('htmlAttributes')($attrs);

        if (empty($contents) && in_array($tagName, $this->voidElements)) {
            return '<' . $tagName . $htmlAttrs . ' />';
        }

        // Special option: escape content
        if ($options['escapeContent'] ?? true) {
            $contents = $this->getView()->plugin('escapeHtml')($contents);
        }

        return '<' . $tagName . $htmlAttrs . '>' . $contents . '</' . $tagName . '>';
    }
}
