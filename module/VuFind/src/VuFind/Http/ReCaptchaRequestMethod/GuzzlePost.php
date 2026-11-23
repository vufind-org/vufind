<?php

/**
 * Recaptcha request method that uses VuFind's Guzzle client.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library 2026.
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
 * @package  Http
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Http\ReCaptchaRequestMethod;

use Psr\Log\LoggerAwareInterface;
use ReCaptcha\ReCaptcha;
use ReCaptcha\RequestMethod;
use ReCaptcha\RequestParameters;
use VuFind\Http\GuzzleService;
use VuFind\Log\LoggerAwareTrait;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Recaptcha request method that uses VuFind's Guzzle client.
 *
 * @category VuFind
 * @package  Http
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GuzzlePost implements RequestMethod, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Constructor.
     *
     * @param GuzzleService $guzzleService Guzzle service
     * @param string        $siteVerifyUrl Site verify URL
     */
    public function __construct(
        protected GuzzleService $guzzleService,
        #[Autowire(config: 'config', path: 'Captcha/recaptcha_siteVerifyUrl', default: ReCaptcha::SITE_VERIFY_URL)]
        protected string $siteVerifyUrl,
    ) {
    }

    /**
     * Submit the request with the specified parameters.
     *
     * @param RequestParameters $params Request parameters
     *
     * @return string Body of the reCAPTCHA response
     */
    public function submit(RequestParameters $params)
    {
        try {
            $response = $this->guzzleService->post(
                $this->siteVerifyUrl,
                $params->toQueryString(),
                'application/x-www-form-urlencoded'
            );
            return $response->getBody()->getContents();
        } catch (\Exception $e) {
            $this->logError("ReCaptcha request with URL {$this->siteVerifyUrl} failed: " . (string)$e);
            return '{"success": false, "error-codes": ["' . ReCaptcha::E_CONNECTION_FAILED . '"]}';
        }
    }
}
