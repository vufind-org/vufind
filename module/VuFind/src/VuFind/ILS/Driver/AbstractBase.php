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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use Stringable;
use VuFind\Exception\ILS as ILSException;
use VuFind\I18n\TranslatableString;

use function is_array;
use function is_bool;
use function is_callable;

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
     * Each value is trimmed if they are string-typed.
     *
     * @param string                 $id               The patron's ID in the ILS
     * @param string                 $cat_username     The username used to log in
     * @param ?string                $cat_password     The password used to log in or null.
     * @param Stringable|string|null $email            The patron's email address (null if unavailable)
     * @param ?string                $firstname        The patron's first name
     * @param ?string                $lastname         The patron's last name
     * @param Stringable|string|null $major            The patron's major (null if unavailable)
     * @param Stringable|string|null $college          The patron's college (null if unavailable)
     * @param array                  $nonDefaultFields Non default fields not documented in the documentation.
     *                                                 Merges into the resulting patron array.
     *
     * @see https://vufind.org/wiki/development:plugins:ils_drivers#patronlogin
     *
     * @return array
     */
    protected function createPatronArray(
        string $id,
        string $cat_username = '',
        ?string $cat_password = null,
        Stringable|string|null $email = null,
        ?string $firstname = '',
        ?string $lastname = '',
        Stringable|string|null $major = null,
        Stringable|string|null $college = null,
        array $nonDefaultFields = []
    ): array {
        $patron = compact(
            'id',
            'email',
            'firstname',
            'lastname',
            'major',
            'college'
        );
        $this->debugDriverResult(__FUNCTION__, $patron, $nonDefaultFields);
        // Merge non default fields into the resulting patron array
        if ($nonDefaultFields) {
            $patron = [...$nonDefaultFields, ...$patron];
        }
        // Add cat_username and cat_password after debugging to avoid logging these values into a log file
        $patron['cat_username'] = $cat_username;
        $patron['cat_password'] = $cat_password;
        return array_map([$this, 'stringNullCastFunc'], $patron);
    }

    /**
     * Create a profile array according to getMyProfile specs defined in the documentation.
     * Each value is trimmed if they are not null.
     *
     * @param Stringable|string|null $firstname        Profile first name
     * @param Stringable|string|null $lastname         Profile last name
     * @param string                 $birthdate        Y-m-d or an empty string
     * @param Stringable|string|null $address1         Address 1
     * @param Stringable|string|null $address2         Address 2
     * @param Stringable|string|null $city             City
     * @param Stringable|string|null $country          Country
     * @param Stringable|string|null $zip              Postal code
     * @param Stringable|string|null $phone            Phone number
     * @param Stringable|string|null $mobile_phone     Mobile phone number
     * @param Stringable|string|null $expiration_date  Profile expiration date
     * @param Stringable|string|null $group            Group i.e. Student, Staff, Faculty, etc
     * @param Stringable|string|null $home_library     The locationID value of a pick-up location
     *                                                 (see getPickUpLocations) that should be
     *                                                 used as the patron's default
     * @param array                  $nonDefaultFields Non default fields not documented in the documentation.
     *                                                 Merges into the resulting profile array.
     *
     * @see https://vufind.org/wiki/development:plugins:ils_drivers#getmyprofile
     *
     * @return array
     */
    protected function createProfileArray(
        Stringable|string|null $firstname = null,
        Stringable|string|null $lastname = null,
        string $birthdate = '',
        Stringable|string|null $address1 = null,
        Stringable|string|null $address2 = null,
        Stringable|string|null $city = null,
        Stringable|string|null $country = null,
        Stringable|string|null $zip = null,
        Stringable|string|null $phone = null,
        Stringable|string|null $mobile_phone = null,
        Stringable|string|null $expiration_date = null,
        Stringable|string|null $group = null,
        Stringable|string|null $home_library = null,
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
            'group',
            'home_library'
        );
        $this->debugDriverResult(__FUNCTION__, $profile, $nonDefaultFields);

        if ($nonDefaultFields) {
            $profile = [...$nonDefaultFields, ...$profile];
        }
        return array_map([$this, 'stringNullCastFunc'], $profile);
    }

    /**
     * Trim and cast value to string if it is not null, instance of TranslatableString or an array.
     *
     * @param mixed $value Value to cast.
     *
     * @return null|string|bool|TranslatableString|array
     */
    protected function stringNullCastFunc(mixed $value): null|string|bool|TranslatableString|array
    {
        if (is_array($value) || is_bool($value) || $value === null || $value instanceof TranslatableString) {
            return $value;
        }
        return trim((string)$value);
    }

    /**
     * Get array containing last and first name from string. This function expects that
     * the template of name is: "lastName,firstName"
     *
     * @param string $fullname Name to parse
     *
     * @return array An array containing:
     * - 0 => last name
     * - 1 => first name
     */
    protected function getLastAndFirstName(string $fullname): array
    {
        if (!str_contains($fullname, ',')) {
            // Append a comma if it does not exist to ensure that last name and first name are found
            // and in proper order
            $fullname .= ',';
        }
        [$lastName, $firstName] = explode(',', $fullname, 2);
        return array_map('trim', [$lastName, $firstName]);
    }

    /**
     * Debug an array into an log file.
     *
     * @param string $function         Will be logged into the start of debugging result
     * @param array  $defaultFields    Default fields to be logged into the debug level log file
     * @param array  $nonDefaultFields Contains non default fields from which keys are only logged into the log file.
     *
     * @return void
     */
    protected function debugDriverResult(string $function, array $defaultFields, array $nonDefaultFields = []): void
    {
        if (!is_callable([$this, 'debug']) || empty($this->config['Debug']['log_function_result'][$function])) {
            return;
        }
        $debugContext = [
            'default fields' => $defaultFields,
            'non default field keys' => array_keys($nonDefaultFields),
        ];
        $this->debug($function . ' result:', $debugContext);
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
