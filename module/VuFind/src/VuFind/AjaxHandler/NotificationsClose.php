<?php
    /**
     * AJAX handler to close a broadcast for the current browser session.
     *
     * PHP version 8
     *
     * Copyright (C) effective WEBWORK GmbH 2023.
     *
     * This program is free software; you can redistribute it and/or modify
     * it under the terms of the GNU General Public License version 2,
     * as published by the Free Software Foundation.
     *
     * This program is distributed in the hope that it will be useful,
     * but WITHOUT ANY WARRANTY; without even the implied warranty of
     * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
     * GNU General Public License for more details.
     *
     * You should have received a copy of the GNU General Public License
     * along with this program; if not, write to the Free Software
     * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
     *
     * @category VuFind
     * @package  Controller
     * @author   Demian Katz <demian.katz@villanova.edu>
     * @author   Johannes Schultze <schultze@effective-webwork.de>
     * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
     * @link     https://vufind.org Main Page
     */

    namespace VuFind\AjaxHandler;

    use Laminas\Mvc\Controller\Plugin\Params;
    use Solarium\Exception\HttpException;
    use VuFind\AjaxHandler\AbstractBase;

    /**
     * AJAX handler to change the visibility of a notification.
     *
     * @category VuFind
     * @package  AJAX
     * @author   Demian Katz <demian.katz@villanova.edu>
     * @author   Johannes Schultze <schultze@effective-webwork.de>
     * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
     * @link     https://vufind.org/wiki/development Wiki
     */
    class NotificationsClose extends AbstractBase
    {
        /**
         * Session containing Notification status information
         *
         * @var \Laminas\Session\Container
         */
        protected $session;

        /**
         * SessionManager
         *
         * @var \Laminas\Session\SessionManager
         */
        protected $sessionManager;

        /**
         * Constructor
         *
         */
        public function __construct(\Laminas\Session\SessionManager $sessionManager)
        {
            $this->sessionManager = $sessionManager;
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
            $broadcast_id = $params->fromPost('broadcast-id', $params->fromQuery('broadcast-id', null));

            $session = $this->getSession();
            if (!isset($session->closedBrodcasts)) {
                $session->closedBrodcasts = [];
            }
            $session->closedBrodcasts[] = $broadcast_id;
            return $this->formatResponse(['Closed brodcasts' => $session->closedBrodcasts ? 1 : 0]);
        }

        /**
         * Get the session container (constructing it on demand if not already present)
         *
         * @return SessionContainer
         */
        protected function getSession()
        {
            // SessionContainer not defined yet? Build it now:
            if (null === $this->session) {
                $this->session = new \Laminas\Session\Container(
                    'Notifications',
                    $this->sessionManager
                );
            }
            return $this->session;
        }
    }
