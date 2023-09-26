<?php

/**
 * Demo (fake data) DOI linker
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @package  DOI
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:doi_linkers Wiki
 */

namespace VuFind\DoiLinker;

use function count;

/**
 * Demo (fake data) DOI linker
 *
 * @category VuFind
 * @package  DOI
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:doi_linkers Wiki
 */
class Demo implements DoiLinkerInterface
{
    /**
     * Possible icon values
     *
     * @var array
     */
    protected $icons = ['browzine-issue', 'browzine-pdf', null];

    /**
     * Given an array of DOIs, perform a lookup and return an associative array
     * of arrays, keyed by DOI. Each array contains one or more associative arrays
     * with required 'link' (URL to related resource) and 'label' (display text)
     * keys and an optional 'icon' (URL to icon graphic) or localIcon (name of
     * configured icon in theme) key.
     *
     * @param array $doiArray DOIs to look up
     *
     * @return array
     */
    public function getLinks(array $doiArray)
    {
        $response = [];
        foreach ($doiArray as $doi) {
            $icon = $this->icons[rand(0, count($this->icons) - 1)];
            $response[$doi][] = [
                'link' => 'https://vufind.org',
                'label' => "Demonstrating DOI link for $doi with icon "
                    . ($icon ?? '[null]'),
                'localIcon' => $icon,
            ];
        }
        return $response;
    }
}
