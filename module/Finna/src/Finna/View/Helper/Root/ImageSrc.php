<?php

/**
 * Get the svg and png images source address.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * Get the svg and png images source address.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class ImageSrc extends ThemeSrc
{
    /**
     * Return image source address. First check if svg image is found and
     * if not, check for png image.
     *
     * @param string $source Image filename without extension
     *
     * @return string
     */
    public function __invoke($source)
    {
        $variations = [
            'images/' . $source . '.svg',
            'images/' . $source . '.png',
            'images/' . $source
        ];
        foreach ($variations as $file) {
            if ($url = $this->fileFromCurrentTheme($file)) {
                $filepath = $this->fileFromCurrentTheme($file, true);
                $mtime = filemtime($filepath);
                return $url . '?_=' . $mtime;
            }
        }

        return '';
    }
}
