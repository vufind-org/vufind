<?php

/**
 * Default ILS driver base class.
 *
 * PHP version 8
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
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use VuFind\Exception\ILS as ILSException;

use function is_string;

/**
 * Default ILS driver base class.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 *
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
abstract class AbstractBase implements DriverInterface
{
    /**
     * Driver configuration
     *
     * @var array
     */
    protected $config = [];

    /**
     * Set configuration.
     *
     * Set the configuration for the driver.
     *
     * @param array $config Configuration array (usually loaded from a VuFind .ini
     * file whose name corresponds with the driver class name).
     *
     * @return void
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * Create a patron array. Ensures that all the keys are present in the resulting array.
     *
     * @param string  $id           Patron id in catalog
     * @param string  $cat_username Catalog username, usually the same as barcode
     * @param string  $cat_password Catalog password
     * @param ?string $email        Patron email in catalog
     * @param string  $firstname    Patron first name
     * @param string  $lastname     Patron last name
     * @param string  $barcode      Obtained barcode, if empty default to cat_username
     * @param ?string $major        Major or null
     * @param ?string $college      College or null
     *
     * @return array Associative array containing:
     * - 'id',
     * - 'cat_username',
     * - 'cat_password',
     * - 'email',
     * - 'firstname',
     * - 'lastname',
     * - 'barcode',
     * - 'major',
     * - 'college',
     */
    public function createPatronArray(
        string $id,
        string $cat_username = '',
        string $cat_password = '',
        ?string $email = null,
        string $firstname = '',
        string $lastname = '',
        string $barcode = '',
        ?string $major = null,
        ?string $college = null
    ): array {
        $barcode = $barcode ?: $cat_username;
        $patron = compact(
            'id',
            'cat_username',
            'cat_password',
            'email',
            'firstname',
            'lastname',
            'barcode',
            'major',
            'college'
        );
        return array_map(fn ($val) => is_string($val) ? trim($val) : $val, $patron);
    }

    /**
     * Rethrow the provided exception as an ILS exception.
     *
     * @param \Throwable $exception Exception to rethrow
     * @param ?string    $msg       Override exception message (optional)
     *
     * @throws ILSException
     * @return never
     */
    protected function throwAsIlsException(
        \Throwable $exception,
        ?string $msg = null
    ): void {
        throw new ILSException($msg ?? $exception->getMessage(), 0, $exception);
    }
}
