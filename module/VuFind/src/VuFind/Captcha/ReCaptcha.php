<?php

/**
 * ReCaptcha CAPTCHA.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @package  CAPTCHA
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Captcha;

use Laminas\Mvc\Controller\Plugin\Params;

/**
 * ReCaptcha CAPTCHA.
 *
 * @category VuFind
 * @package  CAPTCHA
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ReCaptcha extends AbstractBase
{
    /**
     * ReCaptcha Service.
     *
     * @var \VuFind\Service\ReCaptcha
     */
    protected $recaptcha;

    /**
     * Language
     *
     * @var string
     */
    protected $language;

    /**
     * Constructor
     *
     * @param \VuFind\Service\ReCaptcha $recaptcha ReCaptcha Service
     * @param string                    $language  Translator locale
     */
    public function __construct(
        \VuFind\Service\ReCaptcha $recaptcha,
        string $language
    ) {
        $this->recaptcha = $recaptcha;
        $this->language = $language;
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
     * Generate HTML depending on CAPTCHA type.
     *
     * @return string
     */
    public function getHtml(): string
    {
        return $this->recaptcha->getHtml();
    }

    /**
     * Pull the captcha field from controller params and check them for accuracy
     *
     * @param Params $params Controller params
     *
     * @return bool
     */
    public function verify(Params $params): bool
    {
        $responseField = $params->fromPost('g-recaptcha-response');
        return $this->recaptcha->verify($responseField)->isValid();
    }
}
