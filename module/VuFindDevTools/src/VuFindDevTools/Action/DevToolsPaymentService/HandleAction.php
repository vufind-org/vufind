<?php

/**
 * Payment service handle action.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025-2026.
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

namespace VuFindDevTools\Action\DevToolsPaymentService;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\ActionHelper\ResponseHelper;

/**
 * Payment service handle action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HandleAction extends AbstractPaymentServiceAction
{
    /**
     * Handle a payment request (UI).
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
        $requestId = $this->getQueryParam('requestId');
        $session = $this->getCachedData($requestId);
        if (!$session) {
            return $this->getHelper(ResponseHelper::class)->getAjaxResponse(
                $response,
                'text/html',
                'Invalid request ID',
                400
            );
        }

        foreach (['success', 'failure', 'cancel', 'notify'] as $status) {
            if ($this->getPostParam($status)) {
                $session['status'] = 'notify' === $status ? 'success' : $status;
                $this->putCachedData($requestId, $session);
                $this->callNotifyHandler($session['notifyUrl']);
                if ('notify' === $status) {
                    return $this->getHelper(ResponseHelper::class)->getAjaxResponse(
                        $response,
                        'text/plain',
                        'Notify done'
                    );
                }
                return $this->getHelper(RedirectHelper::class)
                    ->redirectToUrl($response, $this->addSignature($session['returnUrl']));
            }
        }

        $returnUrl = $session['returnUrl'];
        return $this->renderTemplate(
            $request,
            $response,
            compact('requestId', 'returnUrl'),
            '/devtools/payment/handle.phtml'
        );
    }

    /**
     * Call client's notify handler.
     *
     * @param string $notifyUrl URL to call
     *
     * @return void
     */
    protected function callNotifyHandler(string $notifyUrl): void
    {
        $response = $this->httpService->get($this->addSignature($notifyUrl));
        if (!$response->isSuccess()) {
            throw new \Exception(
                "Failed to call notify handler '$notifyUrl': " . $response->getStatusCode()
                . ' - ' . $response->getBody()
            );
        }
    }
}
