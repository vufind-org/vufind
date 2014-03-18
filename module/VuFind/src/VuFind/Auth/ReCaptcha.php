<?php
/**
 * ReCaptcha wrapper to allow custom layouts
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2014.
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
 * @package  Auth
 * @author   Chris Hallebrg <crhallberg@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Auth;

/**
 * ReCaptcha wrapper to allow custom layouts
 *
 * @category VuFind2
 * @package  Auth
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class ReCaptcha extends \ZendService\ReCaptcha\ReCaptcha
{
    /**
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;
    
    /**
     * Class constructor
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function __construct($pubKey, $priKey, $serviceLocator)
    {
        parent::__construct($pubKey, $priKey);
        $this->serviceLocator = $serviceLocator;
        $config = $this->serviceLocator->get('VuFind\Config')->get('config');
        if (isset($config->Captcha->theme)) {
            $this->setOption('theme', $config->Captcha->theme);
            $this->setOption('custom_theme_widget', $config->Captcha->elementID);
        }
    }


    /**
     * Get the HTML code for the captcha
     *
     * This method uses the public key to fetch a recaptcha form.
     *
     * @param null|string $name Base name for recaptcha form elements
     * @return string
     * @throws \ZendService\ReCaptcha\Exception
     */
    public function getHtml($name = null)
    {
        if ($this->publicKey === null) {
            throw new Exception('Missing public key');
        }

        $host = self::API_SERVER;

        if ((bool) $this->params['ssl'] === true) {
            $host = self::API_SECURE_SERVER;
        }

        $errorPart = '';
        if (!empty($this->params['error'])) {
            $errorPart = '&error=' . urlencode($this->params['error']);
        }

        $reCaptchaOptions = '';

        if (!empty($this->options)) {
            $encoded = \Zend\Json\Json::encode($this->options);
            $reCaptchaOptions = <<<SCRIPT
<script type="text/javascript">
    var RecaptchaOptions = {$encoded};
</script>
SCRIPT;
        }
        $challengeField = 'recaptcha_challenge_field';
        $responseField  = 'recaptcha_response_field';
        if (!empty($name)) {
            $challengeField = $name . '[' . $challengeField . ']';
            $responseField  = $name . '[' . $responseField . ']';
        }
        
        $renderer = $this->serviceLocator->get('viewmanager')->getRenderer();
        $config = $this->serviceLocator->get('VuFind\Config')->get('config');
        return $renderer->render(
            'Auth/recaptcha.phtml',
            array(
                'challengeField'   => $challengeField,
                'errorPart'        => $errorPart,
                'host'             => $host,
                'publicKey'        => $this->publicKey,
                'reCaptchaOptions' => $reCaptchaOptions,
                'responseField'    => $responseField,
                'theme'            => $config->Captcha->theme,
            )
        );
    }
}