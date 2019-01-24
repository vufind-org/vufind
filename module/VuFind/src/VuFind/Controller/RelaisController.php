<?php
/**
 * Relais Controller
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Controller;

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
            . urlencode($patron['cat_username']);
        if (!empty($q)) {
            $url .= '&query=' . rawurlencode($q);
        }
        return $this->redirect()->toUrl($url);
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
