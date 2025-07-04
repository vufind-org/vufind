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

use function is_callable;
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
     * Create a patron array according to patronLogin specs defined in the documentation.
     * Each value is trimmed if they are type of string.
     *
     * @param string  $id               The patron's ID in the ILS
     * @param string  $cat_username     The username used to log in
     * @param string  $cat_password     The password used to log in
     * @param ?string $email            The patron's email address (null if unavailable)
     * @param string  $firstname        The patron's first name
     * @param string  $lastname         The patron's last name
     * @param ?string $major            The patron's major (null if unavailable)
     * @param ?string $college          The patron's college (null if unavailable)
     * @param array   $nonDefaultFields Non default fields not documented in the documentation.
     *                                  Merges into the resulting patron array.
     *
     * @see https://vufind.org/wiki/development:plugins:ils_drivers#patronlogin
     *
     * @return array
     */
    public function createPatronArray(
        string $id,
        string $cat_username = '',
        string $cat_password = '',
        ?string $email = null,
        string $firstname = '',
        string $lastname = '',
        ?string $major = null,
        ?string $college = null,
        array $nonDefaultFields = []
    ): array {
        $patron = compact(
            'id',
            'cat_username',
            'cat_password',
            'email',
            'firstname',
            'lastname',
            'major',
            'college'
        );
        // Merge non default fields into the resulting patron array
        if ($nonDefaultFields) {
            $patron = array_merge($patron, $nonDefaultFields);
        }
        if (is_callable([$this, 'debug'])) {
            $this->debug(json_encode($patron));
        }
        return array_map(fn ($val) => is_string($val) ? trim($val) : $val, $patron);
    }

    /**
     * Create a profile array according to getMyProfile specs defined in the documentation.
     * Each value is trimmed if they are type of string.
     *
     * @param ?string $firstname        Profile first name
     * @param ?string $lastname         Profile last name
     * @param string  $birthdate        Y-m-d or an empty string
     * @param ?string $address1         Address 1
     * @param ?string $address2         Address 2
     * @param ?string $city             City
     * @param ?string $country          Country
     * @param ?string $zip              Postal code
     * @param ?string $phone            Phone number
     * @param ?string $mobile_phone     Mobile phone number
     * @param ?string $expiration_date  Profile expiration date
     * @param ?string $group            Group i.e. Student, Staff, Faculty, etc
     * @param array   $nonDefaultFields Non default fields not documented in the documentation.
     *                                  Merges into the resulting profile array.
     *
     * @see https://vufind.org/wiki/development:plugins:ils_drivers#getmyprofile
     *
     * @return array
     */
    public function createProfileArray(
        ?string $firstname = null,
        ?string $lastname = null,
        ?string $birthdate = null,
        ?string $address1 = null,
        ?string $address2 = null,
        ?string $city = null,
        ?string $country = null,
        ?string $zip = null,
        ?string $phone = null,
        ?string $mobile_phone = null,
        ?string $expiration_date = null,
        ?string $group = null,
        array $nonDefaultFields = []
    ): array {
        $profile = compact(
            'firstname',
            'lastname',
            'birthdate',
            'address1',
            'address2',
            'city',
            'country',
            'zip',
            'phone',
            'mobile_phone',
            'expiration_date',
            'group'
        );
        if ($nonDefaultFields) {
            $profile = array_merge($profile, $nonDefaultFields);
        }
        if (is_callable([$this, 'debug'])) {
            $this->debug(json_encode($profile));
        }
        return array_map(fn ($value) => is_string($value) ? trim($value) : $value, $profile);
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
