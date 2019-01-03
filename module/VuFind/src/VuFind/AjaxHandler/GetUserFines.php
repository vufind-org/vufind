<?php
/**
 * "Get User Fines" AJAX handler
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

use VuFind\View\Helper\Root\SafeMoneyFormat;
use Zend\Mvc\Controller\Plugin\Params;
use Zend\View\Renderer\PhpRenderer;

/**
 * "Get User Fines" AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetUserFines extends AbstractIlsAndUserAction
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
        $this->disableSessionWrites();  // avoid session write timing bug
        $patron = $this->ilsAuthenticator->storedCatalogLogin();
        if (!$patron) {
            return $this->formatResponse('', self::STATUS_NEED_AUTH, 401);
        }
        if (!$this->ils->checkCapability('getMyFines')) {
            return $this->formatResponse('', self::STATUS_ERROR, 405);
        }
        $fines = $this->ils->getMyFines($patron);
        if (count($fines) === 0) {
            return $this->formatResponse(0);
        }
        $sum = 0;
        foreach ($fines as $fine) {
            $sum += $fine['balance'];
        }
        $smf = new SafeMoneyFormat();
        $smf->setView(new PhpRenderer());
        return $this->formatResponse($smf->__invoke($sum / 100));
    }
}
