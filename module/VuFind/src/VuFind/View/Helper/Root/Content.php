<?php

/**
 * Content View Helper to resolve translated pages.
 * This is basically a wrapper around the PageLocator.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Exception\InvalidArgumentException;
use Laminas\View\Helper\EscapeHtml;
use VuFind\ContentBlock\TemplateBased;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Content View Helper to resolve translated pages.
 * This is basically a wrapper around the PageLocator.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Content
{
    /**
     * Constructor
     *
     * @param TemplateBased $templateBasedBlock TemplateBased instance to resolve translated pages.
     * @param Context       $contextHelper      Context View Helper instance to resolve translated pages.
     * @param EscapeHtml    $escapeHtmlHelper   Escape HTML view helper
     * @param Markdown      $markdownHelper     Markdown view helper
     */
    public function __construct(
        #[Autowire(container: \VuFind\ContentBlock\PluginManager::class)]
        protected TemplateBased $templateBasedBlock,
        #[Autowire(container: 'ViewHelperManager')]
        protected Context $contextHelper,
        #[Autowire(container: 'ViewHelperManager')]
        protected EscapeHtml $escapeHtmlHelper,
        #[Autowire(container: 'ViewHelperManager')]
        protected Markdown $markdownHelper
    ) {
    }

    /**
     * Make helper invokable.
     *
     * @return static
     */
    public function __invoke(): static
    {
        return $this;
    }

    /**
     * Search for a translated template and render it using a temporary context.
     *
     * @param string  $pageName    Name of the page
     * @param string  $pathPrefix  Path where the template should be located
     * @param array   $context     Optional array of context variables
     * @param ?array  $pageDetails Optional output variable for additional info
     * @param ?string $pattern     Optional file system pattern to search page
     *
     * @return string            Rendered template output
     */
    public function renderTranslated(
        string $pageName,
        string $pathPrefix = 'content',
        array $context = [],
        ?array &$pageDetails = [],
        ?string $pattern = null
    ): string {
        if (!str_ends_with($pathPrefix, '/')) {
            $pathPrefix .= '/';
        }
        $pathPrefix = 'templates/' . $pathPrefix;
        $pageDetails = $this->templateBasedBlock->getContext(
            $pathPrefix,
            $pageName,
            $pattern
        );
        return $this->contextHelper->renderInContext(
            'ContentBlock/TemplateBased.phtml',
            $context + $pageDetails
        );
    }

    /**
     * Apply encoding to the content based on the provided content type.
     *
     * @param string $contentType Content type
     * @param string $content     Content
     *
     * @return string
     */
    public function handleContentType(string $contentType, string $content): string
    {
        if (empty($content)) {
            return '';
        }
        return match ($contentType) {
            'text' => ($this->escapeHtmlHelper)($content),
            'html' => $content,
            'markdown' => ($this->markdownHelper)(($this->escapeHtmlHelper)($content))->getContent(),
            default => throw new InvalidArgumentException('Invalid content type: ' . $contentType),
        };
    }
}
