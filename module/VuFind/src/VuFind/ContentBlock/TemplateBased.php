<?php

/**
 * Class TemplateBased
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2020.
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
 * @package  ContentBlock
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ContentBlock;

use function is_callable;

/**
 * Class TemplateBased
 *
 * @category VuFind
 * @package  VuFind\ContentBlock
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class TemplateBased implements ContentBlockInterface
{
    /**
     * Name of template for rendering
     *
     * @var string
     */
    protected $templateName;

    /**
     * Page content
     *
     * @var \VuFind\Content\PageLocator
     */
    protected $pageLocator;

    /**
     * TemplateBased constructor.
     *
     * @param \VuFind\Content\PageLocator $pageLocator Content page locator service
     */
    public function __construct(\VuFind\Content\PageLocator $pageLocator)
    {
        $this->pageLocator = $pageLocator;
    }

    /**
     * Store the configuration of the content block.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        $this->templateName = $settings;
    }

    /**
     * Return context variables used for rendering the block's template.
     *
     * @param string $pathPrefix Subdirectory where the template should be located
     * @param string $page       Template name (defaults to config value if unset)
     * @param string $pattern    Filesystem pattern (see PageLocator)
     *
     * @return array
     */
    public function getContext(
        $pathPrefix = 'templates/ContentBlock/TemplateBased/',
        $page = null,
        $pattern = null
    ) {
        $data = $this->pageLocator->determineTemplateAndRenderer(
            $pathPrefix,
            $page ?? $this->templateName,
            $pattern
        );

        $method = isset($data) ? 'getContextFor' . ucwords($data['renderer'])
            : false;

        $context = $method && is_callable([$this, $method])
            ? $this->$method($data['relativePath'], $data['path'])
            : [];
        $context['pageLocatorDetails'] = $data;
        return $context;
    }

    /**
     * Return context array for markdown
     *
     * @param string $relativePath Relative path to template
     * @param string $path         Full path of template file
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function getContextForMd(string $relativePath, string $path): array
    {
        return [
            'template' => 'ContentBlock/TemplateBased/markdown',
            'data' => file_get_contents($path),
        ];
    }

    /**
     * Return context array of phtml
     *
     * @param string $relativePath Relative path to template
     * @param string $path         Full path of template file
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function getContextForPhtml(string $relativePath, string $path): array
    {
        return ['template' => $relativePath];
    }
}
