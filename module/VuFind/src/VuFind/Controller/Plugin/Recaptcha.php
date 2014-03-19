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
    protected $recaptcha;

    /**
     * Constructor
     *
     * @param \VuFind\Crypt\HMAC $hmac HMAC generator
     */
    public function __invoke()
    {
        return $this;
    }
    
    /**
     * Constructor
     *
     * @param VuFind\Service\Recaptcha $r Service object
     *
     * @param \VuFind\Crypt\HMAC $hmac HMAC generator
     */
    public function __construct($r)
    {
        $this->recaptcha = $r;
    }
    
    /**
     *
     *
     *
     */
    public function getObject()
    {
        return $this->recaptcha;
    }
    
    /**
     *
     *
     *
     */
    public function validate()
    {
        $captchaPassed = true;
        $recaptchaChallenge = $this->getController()->params()
            ->fromPost('recaptcha_challenge_field');
        $recaptchaResponse = $this->getController()->params()
            ->fromPost('recaptcha_response_field', 'manual_challenge');
        if (empty($recaptchaResponse)) {
            $recaptchaResponse = 'manual_challenge';
        }
        if (!empty($recaptchaChallenge)) {
            $result = $this->recaptcha->verify($recaptchaChallenge, $recaptchaResponse);
            $captchaPassed = $result->isValid();
            if (!$captchaPassed) {
                $this->getController()->flashMessenger()
                    ->setNamespace('error')->addMessage('CAPTCHA not passed');
            }
        }
        return $captchaPassed;
    }
    
    /**
     *
     *
     *
     */
    public function active($domain)
    {
        $config = $this->getController()->getConfig();
        return isset($config->Captcha)
        && in_array($domain, $config->Captcha->forms->toArray());
    }
}