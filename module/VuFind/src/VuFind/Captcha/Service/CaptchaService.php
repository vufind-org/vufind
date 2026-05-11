<?php

/**
 * VuFind Action Helper - Captcha handler.
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
 * @package  Captcha
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Captcha\Service;

use VuFind\Config\Feature\ExplodeSettingTrait;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\View\FlashMessenger\FlashMessenger;

use function in_array;

/**
 * Action helper to manage Captcha fields.
 *
 * @category VuFind
 * @package  Captcha
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CaptchaService implements TranslatorAwareInterface
{
    use ExplodeSettingTrait;
    use TranslatorAwareTrait;

    /**
     * Captcha services.
     *
     * @var array
     */
    protected $captchas = [];

    /**
     * String array of forms where Captcha is active.
     *
     * @var bool|string[]
     */
    protected $domains = [];

    /**
     * Captcha activated in config.
     *
     * @var bool
     */
    protected $active = false;

    /**
     * Flash message or throw Exception.
     *
     * @var string
     */
    protected $errorMode = 'flash';

    /**
     * Constructor.
     *
     * @param array          $config         Config file
     * @param array          $captchas       CAPTCHA objects
     * @param FlashMessenger $flashMessenger Flash messenger
     *
     * @return void
     */
    public function __construct(
        array $config,
        array $captchas,
        protected FlashMessenger $flashMessenger,
    ) {
        $this->captchas = $captchas;
        if ($captchas && null !== ($forms = $config['Captcha']['forms'] ?? null)) {
            $this->active = true;
            $this->domains = '*' == trim($forms)
                ? true
                : $this->explodeListSetting($forms);
        }
    }

    /**
     * Flash messages ('flash') or throw exceptions ('throw').
     *
     * @param string $mode 'flash' or 'throw'
     *
     * @return bool
     */
    public function setErrorMode($mode): bool
    {
        if (in_array($mode, ['flash', 'throw', 'none'])) {
            $this->errorMode = $mode;
            return true;
        }
        return false;
    }

    /**
     * Pull the captcha fields from request params and check them for accuracy.
     *
     * @param array $postParams  POST params
     * @param array $queryParams Query params
     *
     * @return bool
     */
    public function verify(array $postParams, array $queryParams): bool
    {
        if (!$this->active()) {
            return true;
        }
        $captchaPassed = false;
        $errorMessage = '';

        foreach ($this->captchas as $captcha) {
            try {
                $captchaPassed = $captcha->verify($postParams, $queryParams);
                if (!$captchaPassed) {
                    $errorMessage = $captcha->getErrorMessage();
                }
            } catch (\Exception $e) {
                $captchaPassed = false;
                $errorMessage = $this->translate('captcha_technical_difficulties');
            }

            if ($captchaPassed) {
                break;
            }
        }

        if (!empty($errorMessage)) {
            if ($this->errorMode == 'flash') {
                $this->flashMessenger->addErrorMessage($errorMessage);
            }
            if ($this->errorMode == 'throw') {
                throw new \Exception($errorMessage);
            }
        }
        return $captchaPassed;
    }

    /**
     * Return whether a specific form is set for Captcha in the config.
     *
     * @param bool|string $domain The specific config term are we checking; ie. "sms"
     *
     * @return bool
     */
    public function active($domain = false): bool
    {
        return $this->active
            && ($domain == false || $this->domains === true
            || in_array($domain, $this->domains));
    }
}
