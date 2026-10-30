<?php

/**
 * ReCaptcha CAPTCHA.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @package  CAPTCHA
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Captcha;

/**
 * ReCaptcha CAPTCHA.
 *
 * @category VuFind
 * @package  CAPTCHA
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ReCaptcha extends AbstractBase
{
    /**
     * Constructor.
     *
     * @param string               $siteKey   Site key
     * @param string               $language  Language
     * @param array                $options   ReCaptcha options
     * @param \ReCaptcha\ReCaptcha $reCaptcha ReCaptcha Service
     */
    public function __construct(
        protected string $siteKey,
        protected string $language,
        protected array $options,
        protected \ReCaptcha\ReCaptcha $reCaptcha,
    ) {
    }

    /**
     * Get list of URLs with JS dependencies to load for the active CAPTCHA type.
     *
     * @return array
     */
    public function getJsIncludes(): array
    {
        return ['https://www.google.com/recaptcha/api.js'
              . '?onload=recaptchaOnLoad&render=explicit&hl=' . $this->language];
    }

    /**
     * Get ReCaptcha site key.
     *
     * @return string
     */
    public function getSiteKey(): string
    {
        return $this->siteKey;
    }

    /**
     * Get ReCaptcha options.
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Pull the captcha fields from request params and check them for accuracy.
     *
     * @param array $postParams  POST params
     * @param array $queryParams Query params
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function verify(array $postParams, array $queryParams): bool
    {
        $responseField = $postParams['g-recaptcha-response'] ?? null;
        return $responseField && $this->reCaptcha->verify($responseField)->isSuccess();
    }
}
