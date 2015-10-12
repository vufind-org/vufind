<?php

/**
 * Resolve path to theme resource.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * Resolve path to theme resource.
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class ThemeSrc extends \Zend\View\Helper\AbstractHelper
{
    protected $themeInfo;

    /**
     * Constructor
     *
     * @param \VuFindTheme\ThemeInfo $themeInfo Theme information service
     */
    public function __construct(\VuFindTheme\ThemeInfo $themeInfo)
    {
        $this->themeInfo = $themeInfo;
    }

    /**
     * Check if file is found in the current theme.
     *
     * @param string $relPath File relative path
     *
     * @return mixed
     */
    protected function fileFromCurrentTheme($relPath)
    {
        $currentTheme = $this->themeInfo->getTheme();
        $basePath = $this->themeInfo->getBaseDir();

        $file = $basePath . '/' . $currentTheme . '/' . $relPath;
        if (file_exists($file)) {
            $urlHelper = $this->getView()->plugin('url');
            return $urlHelper('home') . 'themes/' . $currentTheme . '/' . $relPath;
        }
        return null;
    }
}
