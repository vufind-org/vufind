<?php

/**
 * Trait SortingTrait
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2022.
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
 * @package  I18n
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

declare(strict_types=1);

namespace VuFind\I18n;

use VuFind\Exception\BadConfig as BadConfigException;

/**
 * Trait SortingTrait
 *
 * @category VuFind
 * @package  I18n
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait HasSorterTrait
{
    /**
     * Sorter
     *
     * @var ?SorterInterface
     */
    protected $sorter = null;

    /**
     * Set the sorter
     *
     * @param SorterInterface $sorter Sorter service
     *
     * @return void
     */
    public function setSorter(SorterInterface $sorter): void
    {
        $this->sorter = $sorter;
    }

    /**
     * Get the sorter
     *
     * @return SorterInterface
     */
    public function getSorter(): SorterInterface
    {
        if (null === $this->sorter) {
            throw new BadConfigException('Sorter class is not set.');
        }
        return $this->sorter;
    }
}
