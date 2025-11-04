<?php

/**
 * Resolve path to a resource in theme 'files' directory.
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
 * Resolve path to a resource within theme 'files' directory.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class FileSrc extends ThemeSrc
{
    /**
     * Check if resource is found in theme 'files' directory.
     *
     * @param string $path           Path (starting from 'files' directory)
     * @param bool   $returnAbsolute Whether to return absolute file system path
     *
     * @return string
     */
    public function __invoke($path, $returnAbsolute = false)
    {
        if ($url = $this->fileFromCurrentTheme('files/' . $path, $returnAbsolute)) {
            return $url;
        }

        return '';
    }
}
