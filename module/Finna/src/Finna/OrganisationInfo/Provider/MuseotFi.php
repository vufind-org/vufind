<?php

/**
 * Service for querying museot.fi API
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2023.
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
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\OrganisationInfo\Provider;

use function strlen;

/**
 * Service for querying museot.fi API
 *
 * @category VuFind
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class MuseotFi extends AbstractProvider
{
    /**
     * Check if a consortium is found in organisation info and return basic information (provider-specific part)
     *
     * @param string $language Language
     * @param string $id       Parent organisation ID
     *
     * @return array Associative array with 'id', 'logo' and 'name', or empty array if not found
     */
    protected function doLookup(string $language, string $id): array
    {
        $response = $this->fetchData($id);

        if (!$response || empty($response['museot'])) {
            return [];
        }

        $item = $response['museot'][0];
        if (!$item['finna_publish']) {
            return [];
        }
        $id = $item['finna_org_id'];
        $logo = $item['image'] ?? '';
        $name = $item['name'][$language] ?? $this->translator->translate("source_{$id}");
        return compact('id', 'logo', 'name');
    }

    /**
     * Get consortium information (includes list of locations) (provider-specific part)
     *
     * @param string $language       Language
     * @param string $id             Parent organisation ID
     * @param array  $locationFilter Optional list of locations to include
     *
     * @return array
     */
    protected function doGetConsortiumInfo(string $language, string $id, array $locationFilter = []): array
    {
        return $this->getLocationInfo($language, $id, true);
    }

    /**
     * Get location details (provider-specific part)
     *
     * @param string  $language   Language
     * @param string  $id         Parent organisation ID
     * @param string  $locationId Location ID
     * @param ?string $startDate  Start date (YYYY-MM-DD) of opening times (default is Monday of current week)
     * @param ?string $endDate    End date (YYYY-MM-DD) of opening times (default is Sunday of start date week)
     *
     * @return array
     */
    protected function doGetDetails(
        string $language,
        string $id,
        string $locationId,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        return $this->getLocationInfo($language, $id, false);
    }

    /**
     * Get consortium information (includes list of locations)
     *
     * @param string $language       Language
     * @param string $id             Parent organisation ID
     * @param bool   $consortiumInfo Whether to return consortium info
     *
     * @return array
     */
    protected function getLocationInfo(string $language, string $id, bool $consortiumInfo): array
    {
        $response = $this->fetchData($id);
        if (empty($response['museot'])) {
            return [];
        }
        $json = $response['museot'][0];
        $publish = $json['finna_publish'];
        if (!$publish) {
            return [];
        }

        // Details info
        $details = [
            'id' => $id,
            'type' => 'museum',
            'name' => $json['name'][$language],
            'homepage' => '',
            'slogan' => '',
            'description' => '',
            'links' => [],
            'openNow' => false,
            'openTimes' => [
                'museum' => true,
                'currentWeek' => true,
            ],
            'phones' => [],
            'allServices' => [],
            'personnel' => [],
            'rss' => [],
            'mailAddress' => [],
            'address' => [
                'coordinates' => [
                    'lat' => !empty($json['latitude']) ? $json['latitude'] : '',
                    'lon' => !empty($json['longitude']) ? $json['longitude'] : '',
                ],
                'street' => !empty($json['address']) ? $json['address'] : '',
                'zipcode' => '',
                'area' => '',
                'city' => '',
            ],
            'emails' => array_map(
                function ($email) {
                    return compact('email');
                },
                array_filter(explode(', ', $json['email'] ?? ''))
            ),
            'services' => [],
        ];
        // Use first email as the primary one:
        $details['email'] = $details['emails'][0]['email'] ?? null;

        // Date handling
        $days = [
            0 => 'monday', 1 => 'tuesday', 2 => 'wednesday',
            3 => 'thursday', 4 => 'friday', 5 => 'saturday', 6 => 'sunday',
        ];
        foreach ($days as $day => $key) {
            $details['openTimes']['schedules'][$day] = $this->getScheduleForDay($key, $json);
            if ($details['openTimes']['schedules'][$day]['openNow'] ?? false) {
                $details['openNow'] = true;
                $details['openTimes']['openNow'] = true;
            }
        }
        // Address handling
        if (!empty($details['address']['street'])) {
            $mapUrl = $this->config->General->mapUrl;
            $routeUrl = $this->config->General->routeUrl;
            $replace['street'] = $details['address']['street'];
            $replace['city'] = preg_replace(
                '/[0-9,]+/',
                '',
                $json['post_office']
            );
            foreach ($replace as $param => $val) {
                $mapUrl = str_replace(
                    '{' . $param . '}',
                    rawurlencode($val),
                    $mapUrl
                );
                $routeUrl = str_replace(
                    '{' . $param . '}',
                    rawurlencode($val),
                    $routeUrl
                );
            }
            $details['mapUrl'] = $mapUrl;
            $details['routeUrl'] = $routeUrl;
            $details['address']['zipcode'] = preg_replace('/\D/', '', $json['post_office']);
            $details['address']['city'] = $replace['city'];
        }
        // Contact info handling
        $contactInfo = [];
        foreach ($json['contact_infos'] as $field => $key) {
            $contactInfo[] = [
                'name' => $key['contact_info']["place_$language"],
                'contact' => $key['contact_info']["phone_email_$language"],
            ];
        }
        $details['contactInfo'] = $contactInfo;

        $details['pictures'] = [];
        for ($i = 2; $i <= 4; $i++) {
            $key = "image$i";
            if ($url = $json["image$i"] ?? null) {
                if (strlen($url) > 30) {
                    $details['pictures'][] = compact('url');
                }
            }
        }

        $details['scheduleDescriptions'] = !empty($json['opening_info'][$language])
            ? [$json['opening_info'][$language]]
            : [];

        if (!$consortiumInfo) {
            return $details;
        }

        // Consortium info
        $consortium = [
            'museum' => true,
            'name' =>  $json['name'][$language],
            'description' => $json['description'][$language],
            'homepage' => '',
            'finna' => [
                'servicePoint' => $id,
                'finnaCoverage' => (float)$json['coverage'],
                'usageInfo' => $json['usage_rights'][$language],
            ],
        ];
        foreach ($json['links'] as $field => $key) {
            $consortium['finna']['links'][$field] = [
                'name' => $key['link_info']['link_text_' . $language],
                'url' => $key['link_info']['link_url_' . $language],
            ];
        }
        if (!empty($json['image'])) {
            $consortium['logo']['small'] = $json['image'];
        }

        // Consortium wrapper
        $result = [
            'id' => $id,
            'list' => [
                $details,
            ],
            'consortium' => $consortium,
        ];

        return $result;
    }

    /**
     * Fetch data from cache or external API
     *
     * @param string $locationId Location ID
     *
     * @return array|false array of results or false on error
     */
    protected function fetchData($locationId)
    {
        if (empty($this->config->MuseumAPI->url)) {
            return false;
        }
        $url = $this->config->MuseumAPI->url . '/finna_org_perustiedot.php'
            . '?finna_org_id=' . urlencode($locationId);

        return $this->fetchJson($url) ?? false;
    }

    /**
     * Get open times for a date
     *
     * @param string $day  Weekday
     * @param array  $json Data from museum api
     *
     * @return array
     */
    protected function getScheduleForDay(string $day, array $json): array
    {
        $today = date('Y-m-d');
        $currentHour = date('H:i');
        $result = [
            'times' => [],
            'closed' => false,
            'openNow' => false,
            'info' => '',
        ];
        $dayShortcode = substr($day, 0, 3);
        $dayDate = new \DateTime("$day this week");
        $schedule = $json['opening_time'] ?? [];
        if (
            empty($schedule["{$dayShortcode}_start"])
            || 'NULL' === $schedule["{$dayShortcode}_start"]
            || empty($schedule["{$dayShortcode}_end"])
            || 'NULL' === $schedule["{$dayShortcode}_end"]
        ) {
            $result['closed'] = true;
        } else {
            $result['times'][] = [
                'opens' => $this->parseDateTime($dayDate, $schedule["{$dayShortcode}_start"]),
                'closes' => $this->parseDateTime($dayDate, $schedule["{$dayShortcode}_end"]),
                'selfservice' => false,
            ];
        }
        $result['day'] = $day;
        $result['date'] = $dayDate->getTimestamp();
        if ($today === date('Y-m-d', $result['date'])) {
            $result['today'] = true;
            if (
                $currentHour >= $json['opening_time']["{$dayShortcode}_start"]
                && $currentHour <= $json['opening_time']["{$dayShortcode}_end"]
            ) {
                $result['openNow'] = true;
            }
        } else {
            $result['today'] = false;
        }
        return $result;
    }
}
