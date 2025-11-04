<?php

/**
 * Common functionality for ILS drivers
 *
 * PHP version 8
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
 * @package  ILS_Drivers
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace Finna\ILS\Driver\Feature;

use Stringable;

/**
 * Common functionality for ILS drivers.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
trait FinnaCommonILSTrait
{
    /**
     * Create messaging settings array containing information about services and options
     *
     * @param array  $userSettings Users defined settings
     * @param int    $nofDaysMin   Minimum value for day selection
     * @param int    $nofDaysMax   Maximum inclusive value for day selection
     * @param string $selectType   Type of the selection html element. Default is multiselect.
     *
     * @return array
     */
    protected function createMessagingSettingsArray(
        array $userSettings,
        int $nofDaysMin = 0,
        int $nofDaysMax = 30,
        string $selectType = 'multiselect'
    ): array {
        $parsedMessagingSettings = [];
        foreach ($userSettings as $type => $prefs) {
            $typeName = $this->messagingPrefTypeMap[$type] ?? $type;
            if (!$typeName) {
                continue;
            }
            $settings = [
                'type' => $prefs['type'] ?? $typeName,
            ];
            if (isset($prefs['transport_types'])) {
                $settings['settings']['transport_types'] = [
                    'type' => $prefs['selectType'] ?? $selectType,
                ];
                // Only give the name for setting if it has been declared in the ILS driver.
                // The name will be formed in the front end by using the setting key by default.
                if ($name = $prefs['transport_types']['name'] ?? false) {
                    $settings['settings']['transport_types']['name'] = $name;
                }
                foreach ($prefs['transport_types'] as $key => $active) {
                    $settings['settings']['transport_types']['options'][$key] = [
                        'active' => $active,
                    ];
                    if ($active && $selectType === 'select') {
                        $settings['settings']['transport_types']['value'] = $key;
                    }
                }
                if ($selectType === 'select') {
                    $settings['settings']['transport_types']['value'] ??= '';
                }
            }

            if (isset($prefs['digest'])) {
                $settings['settings']['digest'] = [
                    'type' => 'boolean',
                    'active' => $prefs['digest']['value'],
                    'readonly' => !$prefs['digest']['configurable'],
                ];
                if ($settingName = $prefs['digest']['name'] ?? false) {
                    $settings['settings']['digest']['name'] = $settingName;
                }
            }
            // Create options for days in advance select box.
            if (
                isset($prefs['days_in_advance'])
                && ($prefs['days_in_advance']['configurable']
                || null !== $prefs['days_in_advance']['value'])
            ) {
                $options = [];
                for ($i = $nofDaysMin; $i <= $nofDaysMax; $i++) {
                    $options[$i] = [
                        'name' => $this->translate(
                            1 === $i ? 'messaging_settings_num_of_days'
                            : 'messaging_settings_num_of_days_plural',
                            ['%%days%%' => $i]
                        ),
                        'active' => $i == $prefs['days_in_advance']['value'],
                    ];
                }
                $settings['settings']['days_in_advance'] = [
                    'type' => 'select',
                    'value' => $prefs['days_in_advance']['value'],
                    'options' => $options,
                    'readonly' => !$prefs['days_in_advance']['configurable'],
                ];
            }
            if (isset($prefs['active'])) {
                $settings['active'] = $prefs['active'];
            }
            $parsedMessagingSettings[$type] = $settings;
        }
        return $parsedMessagingSettings;
    }

    /**
     * Create a profile array according to getMyProfile specs defined in the documentation.
     * Each value is trimmed if they are not null.
     *
     * @param Stringable|string|null $firstname         Profile first name
     * @param Stringable|string|null $lastname          Profile last name
     * @param string                 $birthdate         Y-m-d or an empty string
     * @param Stringable|string|null $address1          Address 1
     * @param Stringable|string|null $address2          Address 2
     * @param Stringable|string|null $city              City
     * @param Stringable|string|null $country           Country
     * @param Stringable|string|null $zip               Postal code
     * @param Stringable|string|null $phone             Phone number
     * @param Stringable|string|null $mobile_phone      Mobile phone number
     * @param Stringable|string|null $expiration_date   Profile expiration date
     * @param Stringable|string|null $group             Group i.e. Student, Staff, Faculty, etc
     * @param Stringable|string|null $home_library      The locationID value of a pick-up location
     *                                                  (see getPickUpLocations) that should be
     *                                                  used as the patron's default
     * @param array                  $nonDefaultFields  Non default fields not documented in the documentation.
     *                                                  Merges into the resulting profile array.
     * @param array                  $messagingServices [Finna] Array containing information about
     *                                                  users messaging services.
     *                                                  See $defaultDriverMessagingServices,
     *                                                  $defaultEmailMessagingServices
     * @param ?string                $loan_history      [Finna] Does the user have loan history enabled in the ILS?
     * @param Stringable|string|null $email             [Finna] The profile's email address (null if unavailable)
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
        array $nonDefaultFields = [],
        array $messagingServices = [],
        ?string $loan_history = null,
        Stringable|string|null $email = null
    ): array {
        $nonDefaultFields = array_merge(
            $nonDefaultFields,
            compact('messagingServices', 'loan_history', 'email')
        );
        return parent::createProfileArray(
            $firstname,
            $lastname,
            $birthdate,
            $address1,
            $address2,
            $city,
            $country,
            $zip,
            $phone,
            $mobile_phone,
            $expiration_date,
            $group,
            $home_library,
            $nonDefaultFields
        );
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
        string|null $firstname = '',
        string|null $lastname = '',
        Stringable|string|null $major = null,
        Stringable|string|null $college = null,
        array $nonDefaultFields = []
    ): array {
        return parent::createPatronArray(
            id: $id,
            cat_username: $cat_username,
            cat_password: $cat_password,
            email: $email,
            firstname: (string)$firstname,
            lastname: (string)$lastname,
            major: $major,
            college: $college,
            nonDefaultFields: $nonDefaultFields
        );
    }
}
