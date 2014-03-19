<?php
/**
 * Recaptcha view helper
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
 * @package  View_Helpers
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;
use Zend\View\Helper\AbstractHelper, Zend\Mvc\Controller\Plugin\FlashMessenger;

/**
 * Recaptcha view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Recaptcha extends AbstractHelper
{
    /**
     * Recaptcha controller helper
     *
     * @var Recaptcha
     */
    protected $recaptcha;

    /**
     * Constructor
     *
     * @param \ZendService\Recaptcha\Recaptcha $recaptcha Custom formatted Recaptcha object
     */
    public function __construct($rc)
    {
        $this->recaptcha = $rc;
    }

    /**
     * Generate flash message <div>'s with appropriate classes based on message type.
     *
     * @return string $html
     */
    public function __invoke()
    {
        if ($this->recaptcha->getPublicKey() === null) {
            throw new Exception('Missing public key');
        }

        $host = \ZendService\Recaptcha\Recaptcha::API_SERVER;

        $params = $this->recaptcha->getParams();
        if ((bool) $params['ssl'] === true) {
            $host = \ZendService\Recaptcha\Recaptcha::API_SECURE_SERVER;
        }

        $errorPart = '';
        if (!empty($params['error'])) {
            $errorPart = '&error=' . urlencode($params['error']);
        }

        $reCaptchaOptions = '';
        
        $options = $this->recaptcha->getOptions();
        if (!empty($options)) {
            $encoded = \Zend\Json\Json::encode($options);
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
        
        return $this->view->render(
            'Service/recaptcha.phtml',
            array(
                'challengeField'   => $challengeField,
                'errorPart'        => $errorPart,
                'host'             => $host,
                'publicKey'        => $this->recaptcha->getPublicKey(),
                'reCaptchaOptions' => $reCaptchaOptions,
                'responseField'    => $responseField,
                'theme'            => $options['theme'],
            )
        );
    }
}