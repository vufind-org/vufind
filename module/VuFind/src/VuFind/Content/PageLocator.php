<?php

/**
 * Class PageLocator.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Content
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Content;

use VuFind\ServiceManager\Factory\Autowire;

/**
 * Class PageLocator.
 *
 * @category VuFind
 * @package  Content
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @deprecated Use \VuFind\Content\ContentPageManager instead
 */
class PageLocator
{
    /**
     * Page constructor.
     *
     * @param ContentPageManager $contentPageManager Content page manager
     */
    #[Autowire]
    public function __construct(
        protected ContentPageManager $contentPageManager,
    ) {
    }

    /**
     * Try to find template information about desired page.
     *
     * @param string $pathPrefix Subdirectory where the template should be located
     * @param string $pageName   Template name
     * @param string $pattern    Optional filesystem pattern
     *
     * @return array|null Null if template is not found or array with keys renderer
     * (type of template), path (full path of template), relativePath (relative
     * path within the templates directory), page (page name), theme,
     * matchType (see getTemplateOptionsFromPattern)
     */
    public function determineTemplateAndRenderer(
        $pathPrefix,
        $pageName,
        $pattern = null
    ) {
        return $this->contentPageManager->determineTemplateAndRenderer($pathPrefix, $pageName, $pattern);
    }
}
