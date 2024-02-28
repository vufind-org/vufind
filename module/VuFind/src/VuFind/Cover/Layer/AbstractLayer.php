<?php

/**
 * Abstract cover layer
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Cover_Generator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Cover\Layer;

use function strlen;

/**
 * Abstract cover layer
 *
 * @category VuFind
 * @package  Cover_Generator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
abstract class AbstractLayer implements LayerInterface
{
    /**
     * Mapping of color names to RGB values.
     *
     * @var array
     */
    protected $colorMap = [
        'black' => [0, 0, 0],
        'silver' => [192, 192, 192],
        'gray' => [128, 128, 128],
        'white' => [255, 255, 255],
        'maroon' => [128, 0, 0],
        'red' => [255, 0, 0],
        'purple' => [128, 0, 128],
        'fuchsia' => [255, 0, 255],
        'green' => [0, 128, 0],
        'lime' => [0, 255, 0],
        'olive' => [128, 128, 0],
        'yellow' => [255, 255, 0],
        'navy' => [0, 0, 128],
        'blue' => [0, 0, 255],
        'teal' => [0, 128, 128],
        'aqua' => [0, 255, 255],
    ];

    /**
     * Check and allocates color
     *
     * @param resource $im    Image resource being updated
     * @param string   $color Legal color name from HTML4
     *
     * @return int|false allocated color
     */
    protected function getColor($im, $color)
    {
        // Case one: named color found in map
        $key = strtolower($color);
        if (isset($this->colorMap[$key])) {
            return imagecolorallocate($im, ...$this->colorMap[$key]);
        }
        // Case two: hex color
        if (str_starts_with($color, '#') && strlen($color) == 7) {
            $r = hexdec(substr($color, 1, 2));
            $g = hexdec(substr($color, 3, 2));
            $b = hexdec(substr($color, 5, 2));
            return imagecolorallocate($im, $r, $g, $b);
        }
        // Default case: unsupported color
        return false;
    }

    /**
     * Using HSB allows us to control the contrast while allowing randomness
     *
     * @param resource $im Active image resource
     * @param int      $h  Hue (0-255)
     * @param int      $s  Saturation (0-255)
     * @param int      $v  Lightness (0-255)
     *
     * @return int
     */
    protected function getHSBColor($im, $h, $s, $v)
    {
        $s /= 256.0;
        if ($s == 0.0) {
            return imagecolorallocate($im, $v, $v, $v);
        }
        $h /= (256.0 / 6.0);
        $i = floor($h);
        $f = $h - $i;
        $p = (int)($v * (1.0 - $s));
        $q = (int)($v * (1.0 - $s * $f));
        $t = (int)($v * (1.0 - $s * (1.0 - $f)));
        switch ($i) {
            case 0:
                return imagecolorallocate($im, $v, $t, $p);
            case 1:
                return imagecolorallocate($im, $q, $v, $p);
            case 2:
                return imagecolorallocate($im, $p, $v, $t);
            case 3:
                return imagecolorallocate($im, $p, $q, $v);
            case 4:
                return imagecolorallocate($im, $t, $p, $v);
            default:
                return imagecolorallocate($im, $v, $p, $q);
        }
    }
}
