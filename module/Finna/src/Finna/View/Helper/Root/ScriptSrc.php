<?php

/**
 * Return path to JavaScript file in current theme.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

/**
 * Return path to JavaScript file in current theme.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ScriptSrc extends ThemeSrc
{
    /**
     * Return path to JavaScript file in current theme.
     *
     * @param string $source filename
     *
     * @return string
     */
    public function __invoke($source)
    {
        if ($url = $this->fileFromCurrentTheme("js/$source")) {
            return $url;
        }
        return '';
    }
}
