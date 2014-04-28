<?php
/**
 * VuFind Action Helper - Recaptcha handler
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller_Plugins
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Controller\Plugin;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Zend action helper to manage Recaptcha fields
 *
 * @category VuFind2
 * @package  Controller_Plugins
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Recaptcha extends AbstractPlugin
{
    /**
     * \ZendService\ReCaptcha\ReCaptcha
     */
    protected $recaptcha;

    /**
     * String array of forms where ReCaptcha is active
     */
    protected $domains = array();

    /**
     * Captcha activated in config
     */
    protected $active = false;

    /**
     * Constructor
     *
     * @param \ZendService\ReCaptcha\ReCaptcha $r      Customed reCAPTCHA object
     * @param \VuFind\Config                   $config Config file
     *
     * @return void
     */
    public function __construct($r, $config)
    {
        $this->recaptcha = $r;
        if (isset($config->Captcha->forms)) {
            $this->active = true;
            $this->domains = '*' == trim($config->Captcha->forms)
                ? true
                : array_map(
                    'trim',
                    explode(',', $config->Captcha->forms)
                );
        }
    }

    /**
     * Return the raw service object
     *
     * @return VuFind\Service\Recaptcha
     */
    public function getObject()
    {
        return $this->recaptcha;
    }

    /**
     * Pull the captcha field from POST and check them for accuracy
     *
     * @return boolean
     */
    public function validate()
    {
        if (!$this->active()) {
            return true;
        }
        $captchaPassed = true;
        $recaptchaChallenge = $this->getController()->params()
            ->fromPost('recaptcha_challenge_field');
        $recaptchaResponse = $this->getController()->params()
            ->fromPost('recaptcha_response_field', 'manual_challenge');
        if (empty($recaptchaResponse)) {
            $recaptchaResponse = 'manual_challenge';
        }
        if (!empty($recaptchaChallenge)) {
            $result = $this->recaptcha->verify(
                $recaptchaChallenge,
                $recaptchaResponse
            );
            $captchaPassed = $result->isValid();
            if (!$captchaPassed) {
                $this->getController()->flashMessenger()->setNamespace('error')
                    ->addMessage('recaptcha_not_passed');
            }
        }
        return $captchaPassed;
    }

    /**
     * Return whether a specific form is set for Captcha in the config
     *
     * @return boolean
     */
    public function active($domain = false)
    {
        return $this->active
        && ($domain == false || $this->domains === true
        || in_array($domain, $this->domains));
    }
}