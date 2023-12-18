<?php

/**
 * Confirm Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use function is_array;

/**
 * Redirects the user to the appropriate VuFind action.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ConfirmController extends AbstractBase
{
    /**
     * Determines what elements are displayed on the home page based on whether
     * the user is logged in.
     *
     * @return mixed
     */
    public function confirmAction()
    {
        // Get Data from the route
        $data = $this->params()->fromRoute('data');

        // Assign Flash Messages
        if (isset($data['messages'])) {
            foreach ($data['messages'] as $message) {
                $flash = (true === is_array($message))
                    ? [
                        'msg' => $message['msg'],
                        'tokens' => $message['tokens'] ?? [],
                    ]
                    : $message;
                $this->flashMessenger()->addMessage($flash, 'info');
            }
        }

        // Assign Data
        return $this->createViewModel($data);
    }
}
