<?php
declare(strict_types=1);

/**
 * Trait SorterAwareTrait
 *
 * PHP version 7
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
 * @package  VuFind\Service
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */
namespace VuFind\I18n;

/**
 * Trait SorterAwareTrait
 *
 * @category VuFind
 * @package  VuFind\Service
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */
trait SorterAwareTrait
{
    /**
     * Sorter
     *
     * @var ?Sorter
     */
    protected $sorter = null;

    /**
     * Set the sorter
     *
     * @param Sorter $sorter Sorter service
     *
     * @return void
     */
    public function setSorter(Sorter $sorter): void
    {
        $this->sorter = $sorter;
    }

    /**
     * Get the sorter
     *
     * @return Sorter
     */
    public function getSorter(): Sorter
    {
        return $this->sorter ?? new Sorter();
    }
}
