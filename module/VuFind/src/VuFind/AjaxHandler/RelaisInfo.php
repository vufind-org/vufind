<?php
/**
 * Relais: Check if logged-in patron can order an item.
 *
 * PHP version 7
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

use Zend\Mvc\Controller\Plugin\Params;

/**
 * Relais: Check if logged-in patron can order an item.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RelaisInfo extends AbstractRelaisAction
{
    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, internal status code, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $oclcNumber = $params->fromQuery('oclcNumber');
        $lin = $this->user['cat_username'] ?? null;

        // Authenticate
        $jsonReturnObject = $this->relais->authenticatePatron($lin);
        $authorizationId = $jsonReturnObject->AuthorizationId ?? null;
        if ($authorizationId === null) {
            return $this->formatResponse(
                $this->translate('Failed'), self::STATUS_ERROR
            );
        }

        $allowLoan = $jsonReturnObject->AllowLoanAddRequest ?? false;
        if ($allowLoan == false) {
            return $this->formatResponse('AllowLoan was false', self::STATUS_ERROR);
        }

        $response = $this->relais->search($oclcNumber, $authorizationId, $lin);
        return $this->formatResponse($response);
    }
}
