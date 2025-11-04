<?php

/**
 * AJAX handler for getting organisation info.
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
 * @package  AJAX
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\AjaxHandler;

use Finna\OrganisationInfo\OrganisationInfo;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Renderer\RendererInterface;
use VuFind\Cache\Manager as CacheManager;
use VuFind\I18n\Sorter;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Session\Settings as SessionSettings;

use function count;
use function in_array;

/**
 * AJAX handler for getting organisation info.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetOrganisationInfo extends \VuFind\AjaxHandler\AbstractBase implements
    TranslatorAwareInterface,
    \Psr\Log\LoggerAwareInterface,
    \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Organisation info
     *
     * @var OrganisationInfo
     */
    protected $organisationInfo;

    /**
     * Cache manager
     *
     * @var CacheManager
     */
    protected $cacheManager;

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * Facet configuration
     *
     * @var array
     */
    protected $facetConfig;

    /**
     * Sorter
     *
     * @var Sorter
     */
    protected $sorter;

    /**
     * Constructor
     *
     * @param SessionSettings   $ss               Session settings
     * @param OrganisationInfo  $organisationInfo Organisation info
     * @param CacheManager      $cacheManager     Cache manager
     * @param RendererInterface $renderer         View renderer
     * @param Sorter            $sorter           Sorter
     * @param array             $facetConfig      Facet configuration
     */
    public function __construct(
        SessionSettings $ss,
        OrganisationInfo $organisationInfo,
        CacheManager $cacheManager,
        RendererInterface $renderer,
        Sorter $sorter,
        array $facetConfig
    ) {
        $this->sessionSettings = $ss;
        $this->organisationInfo = $organisationInfo;
        $this->cacheManager = $cacheManager;
        $this->renderer = $renderer;
        $this->sorter = $sorter;
        $this->facetConfig = $facetConfig;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites();  // avoid session write timing bug

        $element = $params->fromQuery('element');
        $sectors = array_filter((array)$params->fromQuery('sectors', []));
        $buildings = array_filter(explode(',', $params->fromQuery('buildings', '')));
        $id = $params->fromQuery('id');
        if (!$id && 'organisation-page-link' !== $element) {
            return $this->handleError('getOrganisationInfo: missing id');
        }

        // Back-compatibility; allow e.g. lib/pub:
        $sectors = array_map(
            function ($s) {
                [$sector] = explode('/', $s, 2);
                return $sector;
            },
            $sectors
        );

        switch ($element) {
            case 'info-location-selection':
                $result = $this->getInfoAndLocationSelection(
                    $id,
                    $params->fromQuery('locationId'),
                    $sectors,
                    $buildings,
                    (bool)$params->fromQuery('consortiumInfo', false)
                );
                break;
            case 'location-search':
                if (null !== ($lat = $params->fromQuery('lat'))) {
                    $lat = (float)$lat;
                }
                if (null !== ($lon = $params->fromQuery('lon'))) {
                    $lon = (float)$lon;
                }
                $result = $this->getLocationSearchResults(
                    $id,
                    $sectors,
                    $params->fromQuery('service_type'),
                    $params->fromQuery('service_location'),
                    $lat,
                    $lon,
                    $params->fromQuery('service_open') === '1'
                );
                break;
            case 'location-details':
                if (!($locationId = $params->fromQuery('locationId'))) {
                    return $this->handleError('getOrganisationInfo: missing location id');
                }
                $result = $this->getLocationDetails($id, $locationId, $sectors);
                break;
            case 'schedule':
                if (!($locationId = $params->fromQuery('locationId'))) {
                    return $this->handleError('getOrganisationInfo: missing location id');
                }
                if (!($startDate = $params->fromQuery('date'))) {
                    return $this->handleError('getOrganisationInfo: missing start date');
                }
                $result = $this->getSchedule($id, $locationId, $sectors, $startDate);
                break;
            case 'widget':
                $result = $this->getWidget(
                    $id,
                    $params->fromQuery('locationId'),
                    $buildings,
                    $sectors,
                    (bool)$params->fromQuery('details', true),
                );
                break;
            case 'widget-location':
                if (!($locationId = $params->fromQuery('locationId') ?: null)) {
                    return $this->handleError('getOrganisationInfo: missing location id');
                }

                $result = $this->getWidgetLocationData(
                    $id,
                    $locationId,
                    $buildings,
                    $sectors,
                    (bool)$params->fromQuery('details', true),
                );
                break;
            case 'organisation-page-link':
                $parentName = $params->fromQuery('parentName', '');
                $renderLinks = (bool)$params->fromQuery('renderLinks', false);
                if (null === $id) {
                    // Multiple organisations
                    if (!($organisations = $params->fromQuery('organisations'))) {
                        return $this->handleError('getOrganisationInfo: missing organisation id or organisations');
                    }
                    try {
                        $organisationList = json_decode($organisations, true, 512, JSON_THROW_ON_ERROR);
                    } catch (\Exception $e) {
                        return $this->handleError('getOrganisationInfo: invalid organisations parameter');
                    }
                    $result = [];
                    foreach ($organisationList as $organisation) {
                        if (!($id = $organisation['id'] ?? null)) {
                            return $this->handleError('getOrganisationInfo: invalid organisations parameter');
                        }
                        $sectors = array_filter((array)$organisation['sector']);
                        $linkData = $this->getOrganisationPageLink($id, $sectors, $parentName, $renderLinks);
                        $result[$id] = $renderLinks ? $linkData : $linkData['url'];
                    }
                } else {
                    // Single location
                    $result = $this->getOrganisationPageLink($id, $sectors, $parentName, $renderLinks);
                }
                break;
            default:
                return $this->handleError('getOrganisationInfo: invalid element (' . ($element ?? '(none)') . ')');
        }

        return $this->formatResponse($result);
    }

    /**
     * Get consortium info and location selection snippet
     *
     * @param string  $id             Organisation id
     * @param ?string $locationId     Selected location id, if any
     * @param array   $sectors        Sectors
     * @param array   $buildings      Buildings
     * @param bool    $consortiumInfo Whether to request information about all locations
     *
     * @return array
     */
    protected function getInfoAndLocationSelection(
        string $id,
        ?string $locationId,
        array $sectors,
        array $buildings,
        bool $consortiumInfo
    ): array {
        $orgInfo = $this->organisationInfo->getConsortiumInfo($sectors, $id, $buildings);

        $buildingFacetOperator = '';
        if ($orFacetSetting = $this->facetConfig['Results_Settings']['orFacets'] ?? null) {
            $orFacets = array_map('trim', explode(',', $orFacetSetting));
            if (
                !empty($orFacets[0])
                && ($orFacets[0] == '*' || in_array('building', $orFacets))
            ) {
                $buildingFacetOperator = '~';
            }
        }

        $consortiumInfo = $consortiumInfo ? $this->renderer->render(
            'organisationinfo/elements/consortium-info.phtml',
            compact('id', 'orgInfo', 'buildingFacetOperator', 'buildings')
        ) : '';
        $locationCount = count($orgInfo['list'] ?? []);
        $locationIdValid = false;
        $locationData = [];
        $serviceList = [];
        $cityList = [];
        foreach ($orgInfo['list'] ?? [] as $org) {
            if ((string)$org['id'] === $locationId) {
                $locationIdValid = true;
            }
            $coordinates = $org['address']['coordinates'] ?? null;
            $locationData[$org['id']] = [
                'id' => $org['id'],
                'name' => $org['name'],
                'openNow' => $org['openNow'],
                'hasSchedules' => !empty($org['openTimes']['schedules']),
                'lat' => $coordinates['lat'] ?? null,
                'lon' => $coordinates['lon'] ?? null,
                'address' => $org['address'],
                'services' => $org['allServices'] ?? [],
            ];
            foreach ($org['allServices'] ?? [] as $services) {
                foreach ($services as $service) {
                    $serviceList[] = $service['standardName'];
                }
            }
            if ($city = $org['address']['city'] ?? '') {
                $cityList[] = $city;
            }
        }
        if (!$locationIdValid) {
            $locationId = null;
        }
        $defaultLocationId = $locationId
            ?? $orgInfo['consortium']['finna']['servicePoint']
            ?? null;
        $defaultLocationName = null;
        if (null !== $defaultLocationId) {
            foreach ($orgInfo['list'] ?? [] as $org) {
                if ((string)$org['id'] === $defaultLocationId) {
                    $defaultLocationName = $org['name'];
                    break;
                }
            }
        }

        $cityList = array_unique($cityList, SORT_REGULAR);
        $this->sorter->sort($cityList);
        $serviceList = array_unique($serviceList);
        $this->sorter->sort($serviceList);
        $locationSelection = $locationData ? $this->renderer->render(
            'organisationinfo/elements/location-selection.phtml',
            compact('id', 'orgInfo', 'locationData', 'serviceList', 'cityList')
        ) : '';

        return compact(
            'consortiumInfo',
            'locationSelection',
            'locationCount',
            'defaultLocationId',
            'defaultLocationName',
            'locationData'
        );
    }

    /**
     * Get location search results snippet
     *
     * @param string $id       Organisation id
     * @param array  $sectors  Sectors
     * @param string $service  Standard name of service
     * @param string $city     City
     * @param ?float $lat      Latitude for sorting
     * @param ?float $lon      Longitude for sorting
     * @param bool   $openOnly Include only open locations
     *
     * @return array
     */
    protected function getLocationSearchResults(
        string $id,
        array $sectors,
        string $service,
        string $city,
        ?float $lat,
        ?float $lon,
        bool $openOnly
    ): array {
        $orgInfo = $this->organisationInfo->getConsortiumInfo($sectors, $id);

        $results = [];
        foreach ($orgInfo['list'] as $location) {
            if ('' !== $service && !in_array($service, $location['serviceStandardNames'])) {
                continue;
            }
            if ('' !== $city && $city !== $location['address']['city']) {
                continue;
            }
            if ($openOnly && !$location['openNow']) {
                continue;
            }
            // Calculate distance if we know user's location:
            if (null !== $lon && null !== $lat) {
                $locLat = $location['address']['coordinates']['lat'] ?? null;
                $locLon = $location['address']['coordinates']['lon'] ?? null;
                if (null !== $locLat && null !== $locLon) {
                    $location['distance'] = $this->getDistance($lat, $lon, $locLat, $locLon);
                } else {
                    $location['distance'] = null;
                }
            }
            $results[] = $location;
        }

        if (null !== $lon && null !== $lat) {
            // Sort by distance from user
            usort(
                $results,
                function ($a, $b) {
                    $result = ($a['distance'] ?? PHP_FLOAT_MAX) <=> $b['distance'] ?? PHP_FLOAT_MAX;
                    if (0 === $result) {
                        $result = $this->sorter->compare($a['name'], $b['name']);
                    }
                    return $result;
                }
            );
        }

        return [
            'results' => $this->renderer->render(
                'organisationinfo/elements/location-search-results.phtml',
                compact('id', 'orgInfo', 'service', 'city', 'results')
            ),
        ];
    }

    /**
     * Get location details snippet
     *
     * @param string $id         Organisation id
     * @param string $locationId Location id
     * @param array  $sectors    Sectors
     *
     * @return array
     */
    protected function getLocationDetails(string $id, string $locationId, array $sectors): array
    {
        $orgInfo = $this->organisationInfo->getDetails($sectors, $id, $locationId);
        $found = !empty($orgInfo);
        if ($found) {
            $info = $this->renderer->render(
                'organisationinfo/elements/location-quick-info.phtml',
                compact('id', 'orgInfo')
            );
            $details = $this->renderer->render(
                'organisationinfo/elements/location-details.phtml',
                compact('id', 'orgInfo')
            );
        } else {
            $details = '';
            $info = '';
        }
        return compact('details', 'found', 'info');
    }

    /**
     * Get schedule snippet
     *
     * @param string $id         Organisation id
     * @param string $locationId Location id
     * @param array  $sectors    Sectors
     * @param string $startDate  Start date
     *
     * @return array
     */
    protected function getSchedule(
        string $id,
        string $locationId,
        array $sectors,
        string $startDate
    ): array {
        $orgInfo = $this->organisationInfo->getDetails($sectors, $id, $locationId, $startDate);

        $widget = $this->renderer->render(
            'organisationinfo/elements/location/schedule-week.phtml',
            compact('orgInfo')
        );
        $weekNum = date('W', strtotime($startDate));
        $currentWeek = date('W') === $weekNum;
        return compact('widget', 'weekNum', 'currentWeek');
    }

    /**
     * Get widget
     *
     * @param string  $id          Organisation id
     * @param ?string $locationId  Location id
     * @param array   $buildings   Buildings
     * @param array   $sectors     Sectors
     * @param bool    $showDetails Whether details are shown
     *
     * @return array
     */
    protected function getWidget(
        string $id,
        ?string $locationId,
        array $buildings,
        array $sectors,
        bool $showDetails
    ): array {
        $consortiumInfo = $this->organisationInfo->getConsortiumInfo($sectors, $id, $buildings);
        $defaultLocationId = $consortiumInfo['consortium']['finna']['servicePoint'] ?? '';
        if (null === $locationId) {
            $locationId = $defaultLocationId;
        }

        $orgInfo = $locationId ? $this->organisationInfo->getDetails($sectors, $id, $locationId) : [];
        if (!$orgInfo) {
            // Reset invalid location id and try with default one if possible:
            if ($locationId !== $defaultLocationId) {
                $locationId = $defaultLocationId;
                $orgInfo = $this->organisationInfo->getDetails($sectors, $id, $locationId);
            } else {
                $locationId = null;
            }
        }
        $orgInfo['list'] = $consortiumInfo['list'];

        $locationName = $this->getLocationName($locationId, $orgInfo);

        $widget = $this->renderer->render(
            'organisationinfo/elements/widget.phtml',
            compact('id', 'orgInfo', 'locationId', 'locationName', 'showDetails')
        );
        return compact('widget', 'locationId', 'locationName');
    }

    /**
     * Get widget data for a location
     *
     * @param string $id          Organisation id
     * @param string $locationId  Location id
     * @param array  $buildings   Buildings
     * @param array  $sectors     Sectors
     * @param bool   $showDetails Whether details are shown
     *
     * @return array
     */
    protected function getWidgetLocationData(
        string $id,
        string $locationId,
        array $buildings,
        array $sectors,
        bool $showDetails
    ): array {
        $consortiumInfo = $this->organisationInfo->getConsortiumInfo($sectors, $id, $buildings);
        $defaultLocationId = $consortiumInfo['consortium']['finna']['servicePoint'] ?? null;
        if (null === $locationId) {
            $locationId = $defaultLocationId;
        }

        $orgInfo = $this->organisationInfo->getDetails($sectors, $id, $locationId);
        if (!$orgInfo) {
            return [];
        }
        $orgInfo['list'] = $consortiumInfo['list'];

        $locationName = $this->getLocationName($locationId, $orgInfo);

        $openStatus = $this->renderer->render(
            'organisationinfo/elements/location/open-status.phtml',
            compact('orgInfo')
        );
        $schedule = $this->renderer->render(
            'organisationinfo/elements/location/schedule.phtml',
            compact('orgInfo')
        );
        $details = $showDetails
            ? $this->renderer->render(
                'organisationinfo/elements/location/widget-details.phtml',
                compact('id', 'orgInfo')
            ) : '';
        return compact('openStatus', 'schedule', 'details', 'locationId', 'locationName');
    }

    /**
     * Get organisation page image and link
     *
     * @param string $id         Organisation id
     * @param array  $sectors    Sectors
     * @param string $parentName Parent organisation name
     * @param bool   $renderLink Whether to return rendered link as well
     *
     * @return array
     */
    protected function getOrganisationPageLink(string $id, array $sectors, string $parentName, bool $renderLink): array
    {
        $orgInfo = $this->organisationInfo->lookup($sectors, $id);
        $found = !empty($orgInfo);
        $html = '';
        $url = null;
        if ($found) {
            $urlPlugin = $this->renderer->plugin('url');
            $url = $urlPlugin(
                'organisationinfo-home',
                [],
                [
                    'query' => ['id' => $orgInfo['id'], 'sector' => implode(',', $sectors) ?: null],
                ]
            );

            $html = $renderLink ? $this->renderer->render(
                'organisationinfo/elements/organisation-page-link.phtml',
                compact('orgInfo', 'url', 'parentName', 'sectors')
            ) : '';
        }
        return compact('found', 'html', 'url');
    }

    /**
     * Get location name from organisation list
     *
     * @param ?string $locationId Location ID
     * @param array   $orgInfo    Organisation info
     *
     * @return string
     */
    protected function getLocationName(?string $locationId, array $orgInfo): string
    {
        if (null !== $locationId) {
            foreach ($orgInfo['list'] ?? [] as $location) {
                if ((string)$location['id'] === $locationId) {
                    return $location['name'];
                }
            }
        }
        return '';
    }

    /**
     * Return an error response in JSON format and log the error message.
     *
     * @param string $outputMsg  Message to include in the JSON response.
     * @param string $logMsg     Message to output to the error log.
     * @param int    $httpStatus HTTPs status of the JSOn response.
     *
     * @return \Laminas\Http\Response
     */
    protected function handleError($outputMsg, $logMsg = '', $httpStatus = 400)
    {
        $this->logError(
            $outputMsg . ($logMsg ? " ({$logMsg})" : null)
        );

        return $this->formatResponse($outputMsg, $httpStatus);
    }

    /**
     * Get distance between two points in meters
     *
     * @param float $lat1 Latitude of first point
     * @param float $lon1 Longitude of first point
     * @param float $lat2 Latitude of second point
     * @param float $lon2 Longitude of second point
     *
     * @return float
     *
     * @see https://en.wikipedia.org/wiki/Great-circle_distance#Formulas
     */
    protected function getDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        if ($lat1 === $lat2 && $lon1 === $lon2) {
            return 0;
        }

        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);

        $dist = sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos(deg2rad($lon1 - $lon2));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        return $dist * 60 * 1.853;
    }
}
