<?php

/**
 * Abstract base class for Turnstile actions.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
 * Copyright (C) The National Library of Finland 2026.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Turnstile;

use Exception;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\Crypt\HMAC;
use VuFind\RateLimiter\RateLimiterManager;
use VuFind\RateLimiter\Turnstile\Turnstile;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\GlobalsContainer;

/**
 * Abstract base class for Turnstile actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractTurnstileAction extends AbstractTemplateRenderingAction
{
    /**
     * Request properties to be securely hashed, to avoid manipulation.
     *
     * @var array
     */
    protected $hashKeys = ['siteKey', 'policyId', 'destination'];

    /**
     * Constructor.
     *
     * @param Turnstile          $turnstile          Turnstile service
     * @param RateLimiterManager $rateLimiterManager Rate Limiter Manager instance
     * @param array              $rateLimiterConfig  Rate Limiter configuration
     * @param HMAC               $hmac               HMAC service
     * @param GlobalsContainer   $globalsContainer   Global data container
     */
    public function __construct(
        protected Turnstile $turnstile,
        protected RateLimiterManager $rateLimiterManager,
        #[Autowire(config: 'RateLimiter', configType: 'yaml')]
        protected array $rateLimiterConfig,
        protected HMAC $hmac,
        protected GlobalsContainer $globalsContainer,
    ) {
        parent::__construct();
    }

    /**
     * Get the siteKey setting.
     *
     * @return string
     */
    protected function getSiteKey(): string
    {
        if (null === ($siteKey = $this->rateLimiterConfig['Turnstile']['siteKey'] ?? null)) {
            throw new Exception('siteKey setting missing');
        }
        return $siteKey;
    }
}
