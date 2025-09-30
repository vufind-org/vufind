<?php

/**
 * Payment Service Simulator Controller
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindDevTools\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Uri\Uri;
use VuFind\Cache\CacheTrait;
use VuFind\Controller\AjaxResponseTrait;

/**
 * Payment Service Simulator Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class PaymentServiceController extends \VuFind\Controller\AbstractBase
{
    use AjaxResponseTrait;
    use CacheTrait;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        parent::__construct($sm);

        $this->setCacheStorage(
            $sm->get(\VuFind\Cache\Manager::class)->getCache('object')
        );
        $this->cacheLifetime = 300;
    }

    /**
     * Initialize a payment request
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function initAction()
    {
        if ($this->params()->fromPost('signature') !== $this->calculateSignature($this->params()->fromPost())) {
            return $this->getAjaxResponse(
                'application/json',
                ['error' => 'Bad signature'],
                400
            );
        }
        $params = ['returnUrl', 'notifyUrl'];
        $session = [];
        foreach ($params as $param) {
            if (!($session[$param] = $this->params()->fromPost($param))) {
                return $this->getAjaxResponse(
                    'application/json',
                    ['error' => "Missing parameter: $param"],
                    400
                );
            }
        }
        $session['status'] = 'pending';
        $requestId = md5(microtime());
        $this->putCachedData($requestId, $session);
        $paymentUrl = str_replace(
            '/devtools/payment/init',
            '/devtools/payment/handle?requestId=' . urlencode($requestId),
            $this->getRequest()->getUriString()
        );
        return $this->getAjaxResponse(
            'application/json',
            compact('requestId', 'paymentUrl')
        );
    }

    /**
     * Handle a payment request (UI)
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function handleAction()
    {
        $requestId = $this->params()->fromQuery('requestId');
        $session = $this->getCachedData($requestId);
        if (!$session) {
            return $this->getAjaxResponse(
                'text/html',
                'Invalid request ID',
                400
            );
        }

        foreach (['success', 'failure', 'cancel', 'notify'] as $status) {
            if ($this->params()->fromPost($status)) {
                $session['status'] = 'notify' === $status ? 'success' : $status;
                $this->putCachedData($requestId, $session);
                $this->callNotifyHandler($session['notifyUrl']);
                if ('notify' === $status) {
                    return $this->getAjaxResponse('text/html', 'Notify done');
                }
                return $this->redirect()->toUrl($this->addSignature($session['returnUrl']));
            }
        }

        $returnUrl = $session['returnUrl'];
        $view = $this->createViewModel(compact('requestId', 'returnUrl'));
        $view->setTemplate('/devtools/payment/handle.phtml');
        return $view;
    }

    /**
     * Get payment status
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function statusAction()
    {
        $requestId = $this->params()->fromPost('requestId');
        $session = $this->getCachedData($requestId);
        if (!$session) {
            return $this->getAjaxResponse(
                'application/json',
                ['error' => 'Invalid request ID'],
                400
            );
        }

        return $this->getAjaxResponse(
            'application/json',
            ['status' => $session['status']],
        );
    }

    /**
     * Get a signature for the request params.
     *
     * Not secure, just for testing.
     *
     * @param array $params Request parameters
     *
     * @return string
     */
    protected function calculateSignature(array $params): string
    {
        unset($params['signature']);
        return md5('secret' . json_encode($params));
    }

    /**
     * Add signature to a URL
     *
     * @param string $url URL
     *
     * @return string
     */
    protected function addSignature(string $url): string
    {
        $uri = new Uri($url);
        $params = $uri->getQueryAsArray();
        $params['signature'] = $this->calculateSignature($params);
        $uri->setQuery($params);
        return (string)$uri;
    }

    /**
     * Call client's notify handler
     *
     * @param string $notifyUrl URL to call
     *
     * @return void
     */
    protected function callNotifyHandler(string $notifyUrl): void
    {
        $httpService = $this->serviceLocator->get(\VuFindHttp\HttpService::class);
        $response = $httpService->get($this->addSignature($notifyUrl));
        if (!$response->isSuccess()) {
            throw new \Exception(
                "Failed to call notify handler '$notifyUrl': " . $response->getStatusCode()
                . ' - ' . $response->getBody()
            );
        }
    }
}
