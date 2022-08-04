<?php
/**
 * Trait for ILS drivers using cache.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2007.
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
 * @category   VuFind
 * @package    ILS_Drivers
 * @author     Demian Katz <demian.katz@villanova.edu>
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link       https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 * @deprecated Use \VuFind\Cache\CacheTrait
 */
namespace VuFind\ILS\Driver;

/**
 * Trait for ILS drivers using cache.
 *
 * @category   VuFind
 * @package    ILS_Drivers
 * @author     Demian Katz <demian.katz@villanova.edu>
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link       https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 * @deprecated Use \VuFind\Cache\CacheTrait
 */
trait CacheTrait
{
    use \VuFind\Cache\CacheTrait;
}
