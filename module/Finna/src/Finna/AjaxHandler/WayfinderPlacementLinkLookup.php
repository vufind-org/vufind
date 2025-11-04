<?php

/**
 * AJAX handler to lookup Wayfinder placement link.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2024.
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
 * @category Wayfinder
 * @package  AJAX
 * @author   Inlead <support@inlead.dk>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://inlead.dk
 */

namespace Finna\AjaxHandler;

use Finna\Wayfinder\WayfinderService;
use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\AjaxHandler\AbstractBase;

/**
 * AJAX handler to lookup Wayfinder placement link.
 *
 * PHP version 8
 *
 * @category Wayfinder
 * @package  AJAX
 * @author   Inlead <support@inlead.dk>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://inlead.dk
 */
class WayfinderPlacementLinkLookup extends AbstractBase
{
    /**
     * Wayfinder service instance.
     *
     * @var WayfinderService
     */
    protected $wayfinderService;

    /**
     * Handler constructor
     *
     * @param WayfinderService $wayfinderService Wayfinder service instance
     */
    public function __construct(WayfinderService $wayfinderService)
    {
        $this->wayfinderService = $wayfinderService;
    }

    /**
     * Handled the incoming request.
     *
     * @param Params $params Request parameters.
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        if (!($locations = json_decode(file_get_contents('php://input'), true))) {
            return $this->formatResponse([]);
        }

        $result = [];
        foreach ($locations as $location) {
            $markerUrl = $this->wayfinderService->getMarker(
                json_decode(
                    $location,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                )
            );
            if (!empty($markerUrl)) {
                $result[$location] = compact('markerUrl');
            }
        }

        return $this->formatResponse(['locations' => $result]);
    }
}
