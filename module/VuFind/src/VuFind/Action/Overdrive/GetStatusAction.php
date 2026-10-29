<?php

/**
 * Overdrive "Get status" action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  Action
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Overdrive;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\ResponseHelper;

/**
 * Overdrive "Get status" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class GetStatusAction extends AbstractOverdriveAction
{
    /**
     * Get item status.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $ids = (array)$this->getPostOrQueryParam('id', []);
        $result = $this->overdriveConnector->getAvailabilityBulk($ids);

        $templateParams = compact('ids', 'result');
        return $this->getHelper(ResponseHelper::class)->getAjaxResponse(
            $response,
            'text/html',
            $this->getTemplateRenderer()->renderTemplateAsString(
                template: 'RecordDriver/SolrOverdrive/status-full',
                params: $templateParams
            )
        );
    }
}
