<?php

/**
 * Demo (fake data) identifier linker
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023-2025.
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
 * @package  IdentifierLinker
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:identifier_linkers Wiki
 */

namespace VuFind\IdentifierLinker;

use function count;

/**
 * Demo (fake data) identifier linker
 *
 * @category VuFind
 * @package  IdentifierLinker
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:identifier_linkers Wiki
 */
class Demo implements IdentifierLinkerInterface
{
    /**
     * Possible icon values
     *
     * @var array
     */
    protected $icons = ['browzine-issue', 'browzine-pdf', null];

    /**
     * Given an array of identifier arrays, perform a lookup and return an associative array
     * of arrays, matching the keys of the input array. Each output array contains one or more
     * associative arrays with required 'link' (URL to related resource) and 'label' (display text)
     * keys and an optional 'icon' (URL to icon graphic) or localIcon (name of configured icon in
     * theme) key.
     *
     * @param array[] $idArray Identifiers to look up
     *
     * @return array
     */
    public function getLinks(array $idArray): array
    {
        $response = [];
        $supportedIdTypes = ['doi', 'isbn', 'issn'];
        foreach ($idArray as $key => $ids) {
            foreach ($supportedIdTypes as $type) {
                if ($id = $ids[$type] ?? null) {
                    $icon = $this->icons[rand(0, count($this->icons) - 1)];
                    $response[$key][] = [
                        'link' => 'https://vufind.org',
                        'label' => 'Demonstrating ' . strtoupper($type) . " link for $id with icon "
                            . ($icon ?? '[null]'),
                        'localIcon' => $icon,
                        'linkType' => rand(0, 1) ? 'foo-link-type' : 'bar-link-type',
                    ];
                }
            }
        }
        return $response;
    }
}
