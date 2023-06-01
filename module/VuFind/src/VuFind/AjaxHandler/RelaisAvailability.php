<?php

/**
 * Relais: Check item availability using a generic patron ID
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
 * Relais: Check item availability using a generic patron ID
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RelaisAvailability extends AbstractRelaisAction
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

        // Authenticate
        $authorizationId = $this->relais->authenticatePatron();
        if ($authorizationId === null) {
            return $this->formatResponse(
                $this->translate('Failed'),
                self::STATUS_HTTP_FORBIDDEN
            );
        }

        // Search
        $responseText = $this->relais->search($oclcNumber, $authorizationId);
        if (
            str_contains($responseText, 'error')
            || str_contains($responseText, 'ErrorMessage')
            || str_contains($responseText, 'false')
        ) {
            $result = 'no';
        } else {
            $result = 'ok';
        }
        return $this->formatResponse(compact('result'));
    }
}
