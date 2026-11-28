<?php

/**
 * Abstract base class for CAPTCHAs with an input field.
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
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Captcha;

/**
 * Abstract base class for CAPTCHAs with an input field.
 *
 * @category VuFind
 * @package  CAPTCHA
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractInputCaptcha extends AbstractBase
{
    /**
     * HTML input name for generated captcha.
     *
     * @var string
     */
    protected string $captchaHtmlInternalId = 'captcha-id';

    /**
     * HTML input name for user input.
     *
     * @var string
     */
    protected string $captchaHtmlInputId = 'captcha-input';

    /**
     * Session lifetime for the captcha data.
     *
     * @var int
     */
    protected int $timeout = 300;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->captchaHtmlInputId .= '-' . $this->getId();
        $this->captchaHtmlInternalId .= '-' . $this->getId();
    }

    /**
     * Getter for template.
     *
     * @return string
     */
    public function getHtmlInternalId(): string
    {
        return $this->captchaHtmlInternalId;
    }

    /**
     * Getter for template.
     *
     * @return string
     */
    public function getHtmlInputId(): string
    {
        return $this->captchaHtmlInputId;
    }
}
