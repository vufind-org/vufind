<?php

/**
 * Class TemplateBased
 *
 * PHP version 7
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
     * @return array
     */
    public function getContext()
    {
        $page = $this->templateName;
        $pathPrefix = "templates/ContentBlock/TemplateBased/";
        $data = $this->pageLocator
            ->determineTemplateAndRenderer($pathPrefix, $page);

        $method = isset($data) ? 'getContextFor' . ucwords($data['renderer'])
            : false;

        return $method && is_callable([$this, $method])
            ? $this->$method($data['page'], $data['path'])
            : [];
    }

    /**
     * Return context array for markdown
     *
     * @param string $page Page name
     * @param string $path Full path of file
     *
     * @return array
     */
    protected function getContextForMd(string $page, string $path): array
    {
        return [
            'templateName' => 'markdown',
            'data' => file_get_contents($path),
        ];
    }

    /**
     * Return context array of phtml
     *
     * @param string $page Page name
     * @param string $path Full path of fie
     *
     * @return array
     */
    protected function getContextForPhtml(string $page, string $path): array
    {
        return [
            'templateName' => $page,
        ];
    }
}
