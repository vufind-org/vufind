<?php

/**
 * Relais Controller
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use function is_array;

/**
 * Relais Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class RelaisController extends AbstractBase
{
    /**
     * Relais login action
     *
     * @return mixed
     */
    public function loginAction()
    {
        // Fatal error if not configured correctly:
        $config = $this->getConfig();
        $baseUrl = $config->Relais->loginUrl ?? null;
        if (empty($baseUrl)) {
            throw new \Exception('Relais login URL not set.');
        }

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // Send user credentials through to Relais:
        $symbol = $config->Relais->symbol ?? '';
        $q = $this->params()->fromQuery('query');
        $url = $baseUrl . '?LS=' . urlencode($symbol)
            . '&dest=discovery&group=patron&PI='
            . urlencode($this->getRelaisUserIdentifier($patron));
        if (!empty($q)) {
            $url .= '&query=' . rawurlencode($q);
        }
        return $this->redirect()->toUrl($url);
    }

    /**
     * Given patron data from the catalogLogin() method, return the appropriate
     * identifier for use with Relais.
     *
     * @param array $patron Patron details
     *
     * @return string
     */
    protected function getRelaisUserIdentifier($patron)
    {
        // By default we assume the cat_username field provides the appropriate
        // username... but if you have a more complex situation at your local
        // institution, you can extend the controller and override this method.
        return $patron['cat_username'];
    }

    /**
     * Relais request action.
     *
     * @return mixed
     */
    public function requestAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }
        return $this->createViewModel(
            [
                'oclc' => $this->params()->fromQuery('oclc'),
                'failLink' => $this->params()->fromQuery('failLink'),
            ]
        );
    }
}
