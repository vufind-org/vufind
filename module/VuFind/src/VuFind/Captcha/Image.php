<?php

/**
 * Image CAPTCHA.
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

use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use Laminas\Session\Container;

/**
 * Image CAPTCHA.
 *
 * @category VuFind
 * @package  CAPTCHA
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Image extends AbstractInputCaptcha
{
    /**
     * Has the captcha image been built?
     *
     * @var bool
     */
    protected bool $built = false;

    /**
     * Constructor.
     *
     * @param CaptchaBuilder $captchaBuilder   Captcha builder
     * @param Container      $sessionContainer Session container for captcha string
     * @param int            $imageWidth       Image width
     * @param int            $imageHeight      Image height
     */
    public function __construct(
        protected CaptchaBuilder $captchaBuilder,
        protected Container $sessionContainer,
        protected int $imageWidth,
        protected int $imageHeight,
    ) {
        parent::__construct();
        $this->sessionContainer->setExpirationHops(1, null);
        $this->sessionContainer->setExpirationSeconds($this->timeout);
    }

    /**
     * Return a captcha image as a Base64 encoded string.
     *
     * @return string
     */
    public function generateImage(): string
    {
        if (!$this->built) {
            $this->captchaBuilder->build($this->imageWidth, $this->imageHeight);
            $this->sessionContainer->phrase = $this->captchaBuilder->getPhrase();
            $this->built = true;
        }
        return $this->captchaBuilder->inline();
    }

    /**
     * Pull the captcha field from request params and check it.
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
        $param = $this->getHtmlInputId();
        $str = $postParams[$param] ?? $queryParams[$param] ?? null;
        return $str && PhraseBuilder::comparePhrases($this->sessionContainer->phrase, $str);
    }
}
