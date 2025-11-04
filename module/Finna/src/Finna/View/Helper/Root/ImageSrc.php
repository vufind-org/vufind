<?php

/**
 * Get the svg and png images source address.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2019.
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
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

/**
 * Get the svg and png images source address.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ImageSrc extends ThemeSrc
{
    /**
     * Backwards-compatible __invoke that returns self or calls getSourceAddress
     *
     * @param string $arg Image filename without extension
     *
     * @return mixed
     */
    public function __invoke($arg = null)
    {
        if (null !== $arg) {
            return $this->getSourceAddress($arg);
        }
        return $this;
    }

    /**
     * Return image source address. First check if svg image is found and
     * if not, check for png image.
     *
     * @param string $source            Image filename without extension
     * @param bool   $allowParentThemes If file can be searched from parent themes
     *
     * @return string
     */
    public function getSourceAddress($source, $allowParentThemes = false)
    {
        $variations = [
            'images/' . $source . '.svg',
            'images/' . $source . '.png',
            'images/' . $source,
        ];
        foreach ($variations as $file) {
            $url = $this->fileFromCurrentTheme(
                $file,
                false,
                $allowParentThemes
            );
            if (!empty($url)) {
                $filepath = $this->fileFromCurrentTheme(
                    $file,
                    true,
                    $allowParentThemes
                );
                $mtime = filemtime($filepath);
                return $url . '?_=' . $mtime;
            }
        }

        return '';
    }

    /**
     * Returns data string to generate a pixel placeholder used for lazyloading
     *
     * @return string
     */
    public function getDataPixel()
    {
        return 'data:image/gif;base64,' .
            'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
    }
}
