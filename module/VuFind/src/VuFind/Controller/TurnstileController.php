<?php

/**
 * Turnstile Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller;

use Laminas\Log\LoggerAwareInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFind\Crypt\HMAC;
use VuFind\Log\LoggerAwareTrait;
use VuFind\RateLimiter\RateLimiterManager;
use VuFind\RateLimiter\Turnstile\Turnstile;

/**
 * Controller Cloudflare Turnstile access checks.
 *
 * @category VuFind
 * @package  Controller
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class TurnstileController extends AbstractBase implements
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Request properties to be securely hashed, to avoid manipulation
     *
     * @var array
     */
    protected $hashKeys = ['siteKey', 'policyId', 'destination'];

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm                 Service locator
     * @param Turnstile               $turnstile          Turnstile service
     * @param RateLimiterManager      $rateLimiterManager Rate Limiter Manager instance
     * @param array                   $config             Rate Limiter configuration
     * @param HMAC                    $hmac               HMAC service
     */
    public function __construct(
        ServiceLocatorInterface $sm,
        protected Turnstile $turnstile,
        protected RateLimiterManager $rateLimiterManager,
        protected array $config,
        protected HMAC $hmac
    ) {
        parent::__construct($sm);
    }

    /**
     * Present the Turnstile challenge to the user
     *
     * @return mixed
     */
    public function challengeAction()
    {
        $context = json_decode(base64_decode($this->params()->fromQuery('context')), true);
        $context['siteKey'] = $this->config['Turnstile']['siteKey'];
        $context['jsLibraryUrl'] = $this->config['Turnstile']['jsLibraryUrl']
            ?? 'https://challenges.cloudflare.com/turnstile/v0/api.js';
        $context['hash'] = $this->hmac->generate($this->hashKeys, $context);

        $this->layout()->searchbox = false;
        return $this->createViewModel($context);
    }

    /**
     * Verify the Turnstile widget result against the Turnstile backend
     *
     * @return mixed
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function verifyAction()
    {
        $token = $this->params()->fromPost('token');
        $policyId = $this->params()->fromPost('policyId');
        $destination = $this->params()->fromPost('destination');
        $priorHash = $this->params()->fromPost('hash');

        $siteKey = $this->config['Turnstile']['siteKey'];
        $newHash = $this->hmac->generate($this->hashKeys, compact($this->hashKeys));
        if ($newHash != $priorHash) {
            throw new \Exception('Wrong hash value used in Turnstile verification.');
        }

        $ipAddress = $this->event->getRequest()->getServer('REMOTE_ADDR');
        $this->turnstile->validateAndCacheResult($token, $policyId, $ipAddress);

        // Either way, return an http redirect to the referrer page.
        return $this->redirect()->toUrl($destination);
    }
}
