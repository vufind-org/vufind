<?php

/**
 * Record render context enum.
 *
 * PHP Version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  RecordDriver
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\RecordDriver;

/**
 * Record render context enum.
 *
 * @category VuFind
 * @package  RecordDriver
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
enum RenderContext: string
{
    case SEARCH = 'search';
    case RECORD = 'record';

    /**
     * Get render context from view type
     *
     * @param string $view View type i.e list, list-grid
     *
     * @return static
     */
    public static function fromView(string $view): static
    {
        return match ($view) {
            'list', 'list grid' => self::SEARCH,
            default => self::RECORD
        };
    }
}
