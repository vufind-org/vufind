<?php

/**
 * Relais: Order an item.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;

/**
 * Relais: Order an item.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RelaisOrder extends AbstractRelaisAction
{
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
        $oclcNumber = $params->fromQuery('oclcNumber');

        $lin = $this->user?->getCatUsername();

        // Authenticate
        $authorizationId = $this->relais->authenticatePatron($lin);
        if ($authorizationId === null) {
            return $this->formatResponse(
                $this->translate('Failed'),
                self::STATUS_HTTP_FORBIDDEN
            );
        }

        // Place order
        $result = $this->relais
            ->placeRequest($oclcNumber, $authorizationId, $lin);
        if (str_contains($result, 'error')) {
            return $this->formatResponse($result, self::STATUS_HTTP_ERROR);
        }
        return $this->formatResponse(compact('result'));
    }
}
