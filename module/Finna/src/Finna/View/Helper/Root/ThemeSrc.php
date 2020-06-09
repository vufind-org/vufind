<?php

/**
 * Resolve path to theme resource.
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
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
 * @category VuFind
 * @package  View_Helpers
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class ThemeSrc extends \Laminas\View\Helper\AbstractHelper
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
     * @param string $relPath        File relative path
     * @param bool   $returnAbsolute Whether to return absolute file system path
     *
     * @return mixed
     */
    protected function fileFromCurrentTheme($relPath, $returnAbsolute = false)
    {
        $currentTheme = $this->themeInfo->getTheme();
        $basePath = $this->themeInfo->getBaseDir();

        $file = $basePath . '/' . $currentTheme . '/' . $relPath;
        if (file_exists($file)) {
            if ($returnAbsolute) {
                return $file;
            }
            $urlHelper = $this->getView()->plugin('url');
            return $urlHelper('home') . 'themes/' . $currentTheme . '/' . $relPath;
        }
        return null;
    }
}
