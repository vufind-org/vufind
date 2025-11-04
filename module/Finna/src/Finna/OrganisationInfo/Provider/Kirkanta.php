<?php

/**
 * Service for querying kirjastohakemisto (Kirkanta)
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

use function in_array;

/**
 * Service for querying Kirjastohakemisto (Kirkanta)
 * See: https://api.kirjastot.fi/
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
class Kirkanta extends AbstractProvider
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
        $params = [
            'finna:id' => $id,
            'lang' => $language,
        ];
        $response = $this->fetchData('finna_organisation', $params);

        if (!($item = $response['items'][0] ?? null)) {
            return [];
        }

        $id = $item['finnaId'];
        $logo = '';
        foreach (['small', 'medium'] as $size) {
            if ($logo = $item['logo'][$size]['url'] ?? '') {
                break;
            }
        }
        $name = '';

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
        $params = [
            'finna:id' => $id,
            'with' => 'links',
            'lang' => $language,
        ];

        $response = $this->fetchData('finna_organisation', $params);
        if (!isset($response['items'][0]['id'])) {
            $this->logError('Error reading consortium info: ' . var_export($params, true));
            return [];
        }
        $response = $response['items'][0];

        $consortium = [
            'id' => $response['id'],
            'name' => $response['name'],
            'description' => $response['description'],
            'homepage' => '',
            'homepageLabel' => '',
            'logo' => [
                'small' => null,
            ],
            'finna' => [
                'usageInfo' => $response['usageInfo'],
                'notification' => $response['notification'],
                'finnaCoverage' => (float)$response['finnaCoverage'],
                'links' => $response['links'] ?? [],
                'servicePoint' => $response['servicePoint'],
            ],
        ];

        if (isset($response['homepage'])) {
            $parts = parse_url($response['homepage']);
            $consortium['homepage'] = $response['homepage'];
            $consortium['homepageLabel'] = $parts['host'] ?? $consortium['homepage'];
        }
        if (!empty($response['logo'])) {
            $consortium['logo']['small'] = $response['logo']['small']['url']
                ?? $response['logo']['medium']['url']
                ?? '';
        }

        // Organisation list for a consortium with schedules for today
        $params = [
            'consortium' => $response['id'],
            'with' => 'schedules,primaryContactInfo,mailAddress,services',
            'period.start' => date('Y-m-d'),
            'period.end' => date('Y-m-d'),
            'status' => '',
            'lang' => $language,
        ];

        if (!empty($locationFilter)) {
            foreach ($locationFilter as $location) {
                if (!ctype_digit((string)$location)) {
                    throw new \Exception('Invalid location in filter: ' . $location);
                }
            }
            $params['id'] = implode(',', $locationFilter);
        }

        $servicePointResponse = $this->fetchData('service_point', $params);
        if (!$servicePointResponse) {
            return [];
        }
        return [
            'consortium' => $consortium,
            'list' => $this->parseList($servicePointResponse),
        ];
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
        if (!$locationId) {
            $this->logError('Missing id');
            return [];
        }

        if (null !== $startDate) {
            $periodStart = $startDate;
        } else {
            // Start from today if today is Monday, otherwise start from last Monday:
            $periodStart = date('Y-m-d', date('N') === '1' ? null : strtotime('last monday'));
        }
        // If end date is not specified, use next Sunday relative to start date:
        $periodEnd = $endDate ?: date('Y-m-d', strtotime('next sunday', strtotime($periodStart)));

        $params = [
            'id' => $locationId,
            'with' => 'schedules,primaryContactInfo,phoneNumbers,emailAddresses,mailAddress,pictures,links,services'
                . ',customData,persons',
            'period.start' => $periodStart,
            'period.end' => $periodEnd,
            'status' => '',
            'lang' => $language,
            'refs' => 'period',
        ];

        $response = $this->fetchData('service_point', $params);
        if (!($response['total'] ?? null)) {
            return [];
        }

        // Parse refs that are outside of items:
        $scheduleDescriptions = [];
        foreach ($response['refs']['period'] ?? [] as $period) {
            if ($period['description']) {
                $scheduleDescriptions[] = $period['description'];
            }
        }

        // Details
        $result = $this->parseDetails($language, $id, $locationId, $response['items'][0]);
        $result['id'] = $locationId;
        $result['periodStart'] = $startDate;
        $result['periodEnd'] = $endDate;
        $result['scheduleDescriptions'] = $scheduleDescriptions;

        return $result;
    }

    /**
     * Fetch data from cache or Kirkanta
     *
     * @param string $action Action
     * @param array  $params Query parameters
     *
     * @return array|bool Array of results or false on error
     */
    protected function fetchData($action, $params)
    {
        $params['limit'] = 1000;
        $apiUrl = $this->config->General->url;
        if (!strpos($apiUrl, 'v4')) {
            $apiUrl .= 'v4';
        }
        $url = $apiUrl . '/' . $action . '?' . http_build_query($params);

        return $this->fetchJson($url) ?? false;
    }

    /**
     * Parse organisation list
     *
     * @param array $response JSON array
     *
     * @return array
     */
    protected function parseList(array $response): array
    {
        $mapUrls = ['routeUrl', 'mapUrl'];
        $mapUrlConf = [];
        foreach ($mapUrls as $url) {
            if (!isset($this->config->General[$url])) {
                continue;
            }
            $base = $this->config->General[$url];
            $conf = ['base' => $base];

            if (preg_match_all('/{([^}]*)}/', $base, $matches)) {
                $conf['params'] = $matches[1];
            }
            $mapUrlConf[$url] = $conf;
        }

        $result = [];
        foreach ($response['items'] as $item) {
            if (empty($item['name'])) {
                continue;
            }

            $data = [
                'id' => $item['id'],
                'name' => $item['name'],
                'shortName' => $item['shortName'],
                'slug' => $item['slug'],
                'type' => $item['type'],
                'mobile' => $item['type'] == 'mobile' ? 1 : 0,
                'email' => $item['primaryContactInfo']['email']['email'] ?? null,
                'emailName' => $item['primaryContactInfo']['email']['name'] ?? null,
                'homepage' => $item['primaryContactInfo']['homepage']['url'] ?? null,
            ];

            if (!empty($item['mailAddress'])) {
                $mailAddress = [
                    'boxNumber' => $item['mailAddress']['boxNumber'] ?? '',
                    'street' => $item['mailAddress']['street'] ?? '',
                    'zipcode' => $item['mailAddress']['zipcode'] ?? '',
                    'area' => $item['mailAddress']['area'] ?? '',
                    'city' => $item['mailAddress']['city'] ?? '',
                ];
            }
            if (!empty($mailAddress)) {
                $data['mailAddress'] = $mailAddress;
            }

            $address = [];
            if (!empty($item['address'])) {
                $address = $item['address'];
                $address['area'] ??= '';
                $address['coordinates']['lat'] ??= null;
                $address['coordinates']['lon'] ??= null;
            }

            if (!empty($item['coordinates'])) {
                $address['coordinates']['lat'] = $item['coordinates']['lat'] ?? null;
                $address['coordinates']['lon'] = $item['coordinates']['lon'] ?? null;
            }
            $data['address'] = $address;

            $schedules = [
                'schedule' => $item['schedules'],
                'status' => $item['liveStatus'],
            ];
            $data['openTimes'] = $this->parseSchedules($schedules);
            $data['openNow'] = $data['openTimes']['openNow'];

            $data += $this->parseServices($item);

            $result[] = $data;
        }
        usort(
            $result,
            function ($a, $b) {
                return $this->sorter->compare($a['name'], $b['name']);
            }
        );

        return $result;
    }

    /**
     * Parse schedules of a location
     *
     * @param object $data JSON data for a location
     *
     * @return array
     */
    protected function parseSchedules($data)
    {
        $schedules = [];
        $periodStart = null;

        $dayNames = [
            'monday', 'tuesday', 'wednesday', 'thursday',
            'friday', 'saturday', 'sunday',
        ];

        $openNow = $data['schedule'] ? false : null;
        $openToday = false;
        $currentWeek = false;
        $currentDateTime = new \DateTime();
        foreach ($data['schedule'] as $day) {
            if (!$periodStart) {
                $periodStart = $day['date'];
            }

            $dateTime = new \DateTime($day['date']);

            // Compare dates:
            $today = $currentDateTime->format('Y-m-d') === $dateTime->format('Y-m-d');

            $dayTime = strtotime($day['date']);
            if ($dayTime === false) {
                $this->logError('Error parsing date: ' . $day['date']);
                continue;
            }

            $weekDay = strtolower(date('l', $dayTime));

            $times = [];
            $closed = $day['closed'];

            // Open times
            foreach ($day['times'] as $time) {
                $result['opens'] = $this->parseDateTime($dateTime, $time['from']);
                $result['closes'] = $this->parseDateTime($dateTime, $time['to']);
                $result['selfservice'] = $time['status'] === 2;
                $result['closed'] = 0 === $time['status'];
                $times[] = $result;

                if ($today) {
                    if (!$result['closed']) {
                        $openToday = true;
                    }
                    if (
                        $result['opens'] <= $currentDateTime->getTimestamp()
                        && $result['closes'] >= $currentDateTime->getTimestamp()
                    ) {
                        $openNow = true;
                    }
                }
            }

            $scheduleData = [
               'date' => $dayTime,
               'times' => $times,
               'day' => $weekDay,
            ];
            $scheduleData['info'] = $day['info'] ?? null;
            $scheduleData['closed'] = $closed;
            $scheduleData['today'] = $today;

            $schedules[] = $scheduleData;

            if ($today) {
                $currentWeek = true;
            }
        }

        return compact('schedules', 'openToday', 'currentWeek', 'openNow');
    }

    /**
     * Parse organisation details.
     *
     * @param string $language   Language
     * @param string $id         Parent organisation ID
     * @param string $locationId Location ID
     * @param array  $response   JSON array
     *
     * @return array
     */
    protected function parseDetails(string $language, string $id, string $locationId, array $response): array
    {
        $result = [
            'id' => $response['id'],
            'name' => $response['name'] ?? '',
            'shortName' => $response['shortName'],
            'slug' => $response['slug'],
            'type' => $response['type'],
            'mobile' => $response['type'] == 'mobile' ? 1 : 0,
            'email' => $response['primaryContactInfo']['email']['email'] ?? null,
            'emailName' => $response['primaryContactInfo']['email']['name'] ?? null,
            'homepage' => $response['primaryContactInfo']['homepage']['url'] ?? null,
            'slogan' => $response['slogan'] ?? '',
            'description' => $response['description'] ?? '',
        ];

        $address = [];
        if (!empty($response['address'])) {
            $address = $response['address'];
            $address['area'] ??= '';
            $address['coordinates']['lat'] ??= null;
            $address['coordinates']['lon'] ??= null;
        }
        $result['address'] = $address;

        if (!empty($response['mailAddress'])) {
            $mailAddress = [
                'boxNumber' => $response['mailAddress']['boxNumber'],
                'street' => $response['mailAddress']['street'],
                'zipcode' => $response['mailAddress']['zipcode'],
                'area' => $response['mailAddress']['area'],
                'city' => $response['mailAddress']['city'] ?? '',
            ];
        }
        if (!empty($mailAddress)) {
            $result['mailAddress'] = $mailAddress;
        }

        $mapUrls = ['routeUrl', 'mapUrl'];
        if (!empty($response['address'])) {
            $mapUrlConf = [];
            foreach ($mapUrls as $url) {
                if (!isset($this->config->General[$url])) {
                    continue;
                }
                $base = $this->config->General[$url];
                $conf = ['base' => $base];

                if (preg_match_all('/{([^}]*)}/', $base, $matches)) {
                    $conf['params'] = $matches[1];
                }
                $mapUrlConf[$url] = $conf;
            }
            foreach ($mapUrlConf as $map => $mapConf) {
                $mapUrl = $mapConf['base'];
                $replace = [];
                foreach ($mapConf['params'] ?? [] as $param) {
                    if ($val = $response['address'][$param] ?? null) {
                        $replace[$param] = $val;
                    }
                }
                foreach ($replace as $param => $val) {
                    $mapUrl = str_replace(
                        '{' . $param . '}',
                        rawurlencode($val),
                        $mapUrl
                    );
                }
                $result[$map] = $mapUrl;
            }
        } else {
            foreach ($mapUrls as $url) {
                $result[$url] = null;
            }
        }
        $schedules = [
            'schedule' => $response['schedules'],
            'status' => $response['liveStatus'],
        ];
        $result['openTimes'] = $this->parseSchedules($schedules);
        $result['openNow'] = $result['openTimes']['openNow'];

        $phones = [];
        foreach ($response['phoneNumbers'] ?? [] as $phone) {
            // Check for email data in phone numbers
            if (str_contains($phone['number'], '@')) {
                continue;
            }
            if ($name = $phone['name'] ?? null) {
                $phones[] = [
                    'name' => $name,
                    'number' => $phone['number'],
                ];
            }
        }
        $result['phones'] = $phones;

        $emails = [];
        $dedupEmails = array_unique($response['emailAddresses'] ?? [], SORT_REGULAR);
        foreach ($dedupEmails as $address) {
            $emails[] = [
                'name' => $address['name'] ?? '',
                'email' => $address['email'],
            ];
        }
        $result['emails'] = $emails;

        $pics = [];
        foreach ($response['pictures'] ?? [] as $pic) {
            $pics[] = $pic['files']['medium'];
        }
        $result['pictures'] = $pics;

        $links = [];
        foreach ($response['links'] ?? [] as $link) {
            $name = $link['name'] ?? null;
            $url = $link['url'] ?? null;
            if ($name && $url) {
                $links[] = ['name' => $name, 'url' => $url];
            }
        }
        $result['links'] = $links;

        $result += $this->parseServices($response);

        $personnel = [];
        foreach ($response['persons'] ?? [] as $person) {
            $personnel[] = [
                'firstName' => $person['firstName'] ?? '',
                'lastName' => $person['lastName'] ?? '',
                'jobTitle' => $person['jobTitle'] ?? '',
                'email' => $person['email'] ?? '',
                'phone' => $person['phone'] ?? '',
            ];
        }
        usort(
            $personnel,
            function ($person1, $person2) {
                return $this->sorter->compare(
                    $person1['lastName'] . ', ' . $person1['firstName'],
                    $person2['lastName'] . ', ' . $person2['firstName']
                );
            }
        );
        $result['personnel'] = $personnel;

        $rssLinks = [];
        foreach ($response['customData'] ?? [] as $link) {
            if (in_array($link['id'], ['news', 'events'])) {
                $rssLinks[] = [
                    'parent' => $id,
                    'id' => $locationId,
                    'orgType' => 'library',
                    'feedType' => $link['id'],
                    'url' => $link['value'],
                ];
            }
        }
        $result['rss'] = $rssLinks;

        foreach ($this->config->Enrichment->$id ?? [] as $enrichment) {
            $this->enrich(
                $language,
                $id,
                $locationId,
                $response,
                $result,
                $enrichment
            );
        }

        return $result;
    }

    /**
     * Parse the services from response
     *
     * @param array $response JSON array for a single location
     *
     * @return array Associative array with the following keys:
     *               - services             List of services mapped for the widget
     *               - allServices          Full service information
     *               - serviceStandardNames List of available service standard names
     */
    protected function parseServices(array $response): array
    {
        $services = $allServices = $serviceStandardNames = [];
        if (!empty($response['services'])) {
            $servicesMap = [];
            foreach ($this->config->OpeningTimesWidget?->services?->toArray() ?? [] as $key => $ids) {
                $servicesMap[$key] = explode(',', $ids);
            }
            foreach ($response['services'] as $service) {
                foreach ($servicesMap as $key => $ids) {
                    if (in_array($service['id'], $ids)) {
                        $services[] = $key;
                    }
                }
                $data = [
                    'name' => ($service['name'] ?? null) ?: ($service['standardName'] ?? ''),
                    'standardName' => ($service['standardName'] ?? null) ?: ($service['name'] ?? ''),
                    'shortDescription' => $service['shortDescription'] ?? '',
                    'description' => $service['description'] ?? '',
                ];
                $allServices[$service['type'] ?? 'other'][] = $data;
                $serviceStandardNames[] = $data['standardName'];
            }
            foreach ($allServices as &$serviceType) {
                usort(
                    $serviceType,
                    function ($service1, $service2) {
                        return $this->sorter->compare($service1['name'], $service2['name']);
                    }
                );
            }
            unset($serviceType);
        }
        $services = array_values(array_unique($services));
        $serviceStandardNames = array_values(array_unique($serviceStandardNames));
        return compact('services', 'allServices', 'serviceStandardNames');
    }
}
