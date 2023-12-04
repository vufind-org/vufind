<?php

/**
 * Grid cover background layer
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
 * Grid cover background layer
 *
 * @category VuFind
 * @package  Cover_Generator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class GridBackground extends AbstractBackgroundLayer
{
    /**
     * Render the layer
     *
     * @param resource $im       Image resource to draw on
     * @param array    $details  Cover details array (with title/author/call_number)
     * @param object   $settings Settings object
     *
     * @return void
     */
    public function render($im, $details, $settings)
    {
        // Generate a grid of colors as primary feature
        $seed = $this->createSeed($details['title'], $details['callnumber']);
        $pattern = $this->createPattern($seed);
        $accentColor = $this->getAccentColor($im, $seed, $settings);
        $this->renderGrid($im, $pattern, $accentColor, $settings);
    }

    /**
     * Turn number into pattern
     *
     * @param int $seed Seed used to generate the pattern
     *
     * @return string binary string describing a quarter of the pattern
     */
    protected function createPattern($seed)
    {
        // Convert to binary
        $bc = decbin($seed);
        // If we have less that a half of a quarter
        if (strlen($bc) < 8) {
            // Rotate square of the first 4 into a 4x2
            // Simulate matrix rotation on string
            $bc = substr($bc, 0, 3)
                . substr($bc, 0, 1)
                . substr($bc, 2, 2)
                . substr($bc, 3, 1)
                . substr($bc, 1, 1);
        }
        // If we have less than a quarter
        if (strlen($bc) < 16) {
            // Rotate the first 8 as a 4x2 into a 4x4
            $bc .= strrev($bc);
        }
        return $bc;
    }

    /**
     * Convert 16 long binary string to 8x8 color grid
     * Reflects vertically and horizontally
     *
     * @param resource $im       Active image resource
     * @param string   $pattern  Binary string of pattern
     * @param int      $color    Fill color
     * @param object   $settings Generator settings object
     *
     * @return void
     */
    protected function renderGrid($im, $pattern, $color, $settings)
    {
        imagefilledrectangle(
            $im,
            0,
            0,
            $settings->width,
            $settings->height,
            $this->getColor($im, $settings->baseColor)
        );
        $halfWidth = (int)($settings->width / 2);
        $halfHeight = (int)($settings->height / 2);
        $boxWidth  = (int)($settings->width / 8);
        $boxHeight = (int)($settings->height / 8);

        $bc = str_split($pattern);
        for ($k = 0; $k < 4; $k++) {
            $x = $k % 2 ? $halfWidth : $halfWidth - $boxWidth;
            $y = $k / 2 < 1 ? $halfHeight : $halfHeight - $boxHeight;
            $u = $k % 2 ? $boxWidth : -$boxWidth;
            $v = $k / 2 < 1 ? $boxHeight : -$boxHeight;
            for ($i = 0; $i < 16; $i++) {
                if ($bc[$i] == '1') {
                    imagefilledrectangle(
                        $im,
                        $x,
                        $y,
                        $x + $boxWidth - 1,
                        $y + $boxHeight - 1,
                        $color
                    );
                }
                $x += $u;
                if ($x >= $settings->width || $x < 0) {
                    $x = $k % 2 ? $halfWidth : $halfWidth - $boxWidth;
                    $y += $v;
                }
            }
        }
    }
}
