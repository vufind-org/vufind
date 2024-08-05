<?php

/**
 * Abstract cover background layer
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

use function ord;
use function strlen;

/**
 * Abstract cover background layer
 *
 * @category VuFind
 * @package  Cover_Generator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
abstract class AbstractBackgroundLayer extends AbstractLayer
{
    /**
     * Generates a dynamic cover image from elements of the book
     *
     * @param string $title      Title of the book
     * @param string $callnumber Callnumber of the book
     *
     * @return int unique number for this record
     */
    protected function createSeed($title, $callnumber)
    {
        // Turn callnumber into number
        if (null == $callnumber) {
            $callnumber = $title;
        }
        if (null !== $callnumber) {
            $cv = 0;
            for ($i = 0; $i < strlen($callnumber); $i++) {
                $cv += ord($callnumber[$i]);
            }
            return $cv;
        } else {
            // If no callnumber, random
            return ceil(rand(2 ** 4, 2 ** 32));
        }
    }

    /**
     * Generate an accent color from a seed value.
     *
     * @param resource $im       Active image resource
     * @param int      $seed     Seed value
     * @param object   $settings Generator settings object
     *
     * @return int
     */
    protected function getAccentColor($im, $seed, $settings)
    {
        // Number to color, hsb to control saturation and lightness
        return $settings->accentColor == 'random'
            ? $this->getHSBColor(
                $im,
                $seed % 256,
                $settings->saturation,
                $settings->lightness
            ) : $this->getColor($im, $settings->accentColor);
    }
}
