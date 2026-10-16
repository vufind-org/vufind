<?php

/**
 * POST method handler for Shibboleth logout notification.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2026.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\ShibbolethLogoutNotification;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function extension_loaded;

/**
 * POST method handler for Shibboleth logout notification.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class PostAction extends AbstractShibbolethLogoutNotificationAction
{
    /**
     * List holds and process cancellations and updates.
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
        $this->disableSessionWrites();

        if (!extension_loaded('soap')) {
            throw new \Exception('SOAP extension is not loaded.');
        }
        $soapServer = new \SoapServer(
            'data://text/plain;base64,' . base64_encode($this->getWsdl($request))
        );
        $soapServer->setObject($this);

        ob_start();
        try {
            $request = file_get_contents('php://input');
            $soapServer->handle($request);
            $soapResponse = ob_get_clean();
        } catch (\Exception $e) {
            ob_end_clean();
            $soapResponse = (string)$e;
        }

        $response = $response->withHeader('Content-Type', 'text/xml');
        $response->getBody()->write($soapResponse);
        return $response;
    }

    /**
     * Logout notification handler.
     *
     * This gets called by SoapServer in the action method.
     *
     * @param string $sessionId External session id
     *
     * @return void
     */
    public function logoutNotification($sessionId)
    {
        $rows = $this->externalSessionService->getAllByExternalSessionId(trim($sessionId));
        if ($rows) {
            $handler = $this->sessionManager->getSaveHandler();
            foreach ($rows as $row) {
                $handler->destroy($row->getSessionId());
            }
        }
    }
}
