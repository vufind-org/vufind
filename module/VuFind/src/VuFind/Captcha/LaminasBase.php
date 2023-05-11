<?php

/**
 * Laminas base CAPTCHA
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
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Captcha;

use Laminas\Mvc\Controller\Plugin\Params;

/**
 * Laminas base CAPTCHA
 *
 * @category VuFind
 * @package  CAPTCHA
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class LaminasBase extends AbstractBase
{
    /**
     * Laminas CAPTCHA object
     *
     * @var \Laminas\Captcha\AbstractWord
     */
    protected $captcha;

    /**
     * HTML input name for generated captcha
     *
     * @var string
     */
    protected $captchaHtmlInternalId = 'captcha-id';

    /**
     * HTML input name for user input
     *
     * @var string
     */
    protected $captchaHtmlInputId = 'captcha-input';

    /**
     * Constructor
     *
     * @param \Laminas\Captcha\AbstractWord $captcha Laminas CAPTCHA object
     */
    public function __construct(\Laminas\Captcha\AbstractWord $captcha)
    {
        $this->captcha = $captcha;
        $this->captchaHtmlInputId .= '-' . $this->getId();
        $this->captchaHtmlInternalId .= '-' . $this->getId();
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
        $validateParams = [
            'id' => $params->fromPost($this->captchaHtmlInternalId),
            'input' => $params->fromPost($this->captchaHtmlInputId),
        ];
        return $this->captcha->isValid($validateParams);
    }

    /**
     * Laminas CAPTCHA object
     *
     * @return \Laminas\Captcha\AbstractWord
     */
    public function getCaptcha(): \Laminas\Captcha\AbstractWord
    {
        return $this->captcha;
    }

    /**
     * Getter for template
     *
     * @return string
     */
    public function getHtmlInternalId(): string
    {
        return $this->captchaHtmlInternalId;
    }

    /**
     * Getter for template
     *
     * @return string
     */
    public function getHtmlInputId(): string
    {
        return $this->captchaHtmlInputId;
    }
}
