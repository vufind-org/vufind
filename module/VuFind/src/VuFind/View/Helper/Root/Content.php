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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;
use VuFind\ContentBlock\TemplateBased;

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
class Content extends AbstractHelper
{
    /**
     * TemplateBased instance to resolve translated pages.
     *
     * @var TemplateBased
     */
    protected $templateBasedBlock;

    /**
     * Context View Helper instance to resolve translated pages.
     *
     * @var Context
     */
    protected $contextHelper;

    /**
     * Constructor
     *
     * @param TemplateBased $block         TemplateBased ContentBlock
     * @param Context       $contextHelper Context view helper
     */
    public function __construct(
        TemplateBased $block,
        Context $contextHelper
    ) {
        $this->templateBasedBlock = $block;
        $this->contextHelper = $contextHelper;
    }

    /**
     * Search for a translated template and render it using a temporary context.
     *
     * @param string $pageName    Name of the page
     * @param string $pathPrefix  Path where the template should be located
     * @param array  $context     Optional array of context variables
     * @param array  $pageDetails Optional output variable for additional info
     * @param string $pattern     Optional file system pattern to search page
     *
     * @return string            Rendered template output
     */
    public function renderTranslated(
        string $pageName,
        string $pathPrefix = 'content',
        array $context = [],
        ?array &$pageDetails = [],
        ?string $pattern = null
    ) {
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
}
