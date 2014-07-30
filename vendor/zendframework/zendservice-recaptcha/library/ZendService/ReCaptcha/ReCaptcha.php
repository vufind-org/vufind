<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Service
 */

namespace ZendService\ReCaptcha;

use Traversable;
use Zend\Http\Client as HttpClient;
use Zend\Http\Request as HttpRequest;
use Zend\Stdlib\ArrayUtils;


/**
 * Zend_Service_ReCaptcha
 *
 * @category   Zend
 * @package    Zend_Service
 * @subpackage ReCaptcha
 */
class ReCaptcha
{
    /**
     * URI to the regular API
     *
     * @var string
     */
    const API_SERVER = 'http://www.google.com/recaptcha/api';

    /**
     * URI to the secure API
     *
     * @var string
     */
    const API_SECURE_SERVER = 'https://www.google.com/recaptcha/api';

    /**
     * URI to the verify server
     *
     * @var string
     */
    const VERIFY_SERVER = 'http://www.google.com/recaptcha/api/verify';

    /**
     * Public key used when displaying the captcha
     *
     * @var string
     */
    protected $publicKey = null;

    /**
     * Private key used when verifying user input
     *
     * @var string
     */
    protected $privateKey = null;

    /**
     * Ip address used when verifying user input
     *
     * @var string
     */
    protected $ip = null;

    /**
     * Parameters for the object
     *
     * @var array
     */
    protected $params = array(
        'ssl' => false, /* Use SSL or not when generating the recaptcha */
        'error' => null, /* The error message to display in the recaptcha */
        'xhtml' => false /* Enable XHTML output (this will not be XHTML Strict
                            compliant since the IFRAME is necessary when
                            Javascript is disabled) */
    );

    /**
     * Options for tailoring reCaptcha
     *
     * See the different options on http://recaptcha.net/apidocs/captcha/client.html
     *
     * @var array
     */
    protected $options = array(
        'theme' => 'red',
        'lang' => 'en',
    );

    /**
     * @var HttpClient
     */
    protected $httpClient = null;

    /**
     * Response from the verify server
     *
     * @var \ZendService\ReCaptcha\Response
     */
    protected $_response = null;

    /**
     * Class constructor
     *
     * @param string $publicKey
     * @param string $privateKey
     * @param array|Traversable $params
     * @param array|Traversable $options
     * @param string $ip
     */
    public function __construct($publicKey = null, $privateKey = null, $params = null, $options = null, $ip = null, HttpClient $httpClient = null)
    {
        if ($publicKey !== null) {
            $this->setPublicKey($publicKey);
        }

        if ($privateKey !== null) {
            $this->setPrivateKey($privateKey);
        }

        if ($ip !== null) {
            $this->setIp($ip);
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $this->setIp($_SERVER['REMOTE_ADDR']);
        }

        if ($params !== null) {
            $this->setParams($params);
        }

        if ($options !== null) {
            $this->setOptions($options);
        }

        $this->setHttpClient($httpClient ?: new HttpClient);
    }

    public function setHttpClient(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    public function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * Serialize as string
     *
     * When the instance is used as a string it will display the recaptcha.
     * Since we can't throw exceptions within this method we will trigger
     * a user warning instead.
     *
     * @return string
     */
    public function __toString()
    {
        try {
            $return = $this->getHtml();
        } catch (\Exception $e) {
            $return = '';
            trigger_error($e->getMessage(), E_USER_WARNING);
        }

        return $return;
    }

    /**
     * Set the ip property
     *
     * @param string $ip
     * @return \ZendService\ReCaptcha\ReCaptcha
     */
    public function setIp($ip)
    {
        $this->ip = $ip;

        return $this;
    }

    /**
     * Get the ip property
     *
     * @return string
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * Set a single parameter
     *
     * @param string $key
     * @param string $value
     * @return \ZendService\ReCaptcha\ReCaptcha
     */
    public function setParam($key, $value)
    {
        $this->params[$key] = $value;

        return $this;
    }

    /**
     * Set parameters
     *
     * @param  array|Traversable $params
     * @return \ZendService\ReCaptcha\ReCaptcha
     * @throws \ZendService\ReCaptcha\Exception
     */
    public function setParams($params)
    {
        if ($params instanceof Traversable) {
            $params = ArrayUtils::iteratorToArray($params);
        }

        if (!is_array($params)) {
            throw new Exception(sprintf(
                '%s expects an array or Traversable set of params; received "%s"',
                __METHOD__,
                (is_object($params) ? get_class($params) : gettype($params))
            ));
        }

        foreach ($params as $k => $v) {
            $this->setParam($k, $v);
        }

        return $this;
    }

    /**
     * Get the parameter array
     *
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Get a single parameter
     *
     * @param string $key
     * @return mixed
     */
    public function getParam($key)
    {
        return $this->params[$key];
    }

    /**
     * Set a single option
     *
     * @param string $key
     * @param string $value
     * @return \ZendService\ReCaptcha\ReCaptcha
     */
    public function setOption($key, $value)
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Set options
     *
     * @param  array|Traversable $options
     * @return \ZendService\ReCaptcha\ReCaptcha
     * @throws \ZendService\ReCaptcha\Exception
     */
    public function setOptions($options)
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        }

        if (is_array($options)) {
            foreach ($options as $k => $v) {
                $this->setOption($k, $v);
            }
        } else {
            throw new Exception(
                'Expected array or Traversable object'
            );
        }

        return $this;
    }

    /**
     * Get the options array
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Get a single option
     *
     * @param string $key
     * @return mixed
     */
    public function getOption($key)
    {
        return $this->options[$key];
    }

    /**
     * Get the public key
     *
     * @return string
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }

    /**
     * Set the public key
     *
     * @param string $publicKey
     * @return \ZendService\ReCaptcha\ReCaptcha
     */
    public function setPublicKey($publicKey)
    {
        $this->publicKey = $publicKey;

        return $this;
    }

    /**
     * Get the private key
     *
     * @return string
     */
    public function getPrivateKey()
    {
        return $this->privateKey;
    }

    /**
     * Set the private key
     *
     * @param string $privateKey
     * @return \ZendService\ReCaptcha\ReCaptcha
     */
    public function setPrivateKey($privateKey)
    {
        $this->privateKey = $privateKey;

        return $this;
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

        $htmlBreak = '<br>';
        $htmlInputClosing = '>';

        if ((bool) $this->params['xhtml'] === true) {
            $htmlBreak = '<br />';
            $htmlInputClosing = '/>';
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

        $return = $reCaptchaOptions;
        $return .= <<<HTML
<script type="text/javascript"
   src="{$host}/challenge?k={$this->publicKey}{$errorPart}">
</script>
HTML;
        $return .= <<<HTML
<noscript>
   <iframe src="{$host}/noscript?k={$this->publicKey}{$errorPart}"
       height="300" width="500" frameborder="0"></iframe>{$htmlBreak}
   <textarea name="{$challengeField}" rows="3" cols="40">
   </textarea>
   <input type="hidden" name="{$responseField}"
       value="manual_challenge"{$htmlInputClosing}
</noscript>
HTML;

        return $return;
    }

    /**
     * Post a solution to the verify server
     *
     * @param string $challengeField
     * @param string $responseField
     * @return \Zend\Http\Response
     * @throws \ZendService\ReCaptcha\Exception
     */
    protected function post($challengeField, $responseField)
    {
        if ($this->privateKey === null) {
            throw new Exception('Missing private key');
        }

        if ($this->ip === null) {
            throw new Exception('Missing ip address');
        }

        if (empty($challengeField)) {
            throw new Exception('Missing challenge field');
        }

        if (empty($responseField)) {
            throw new Exception('Missing response field');
        }

        /* Fetch an instance of the http client */
        $httpClient = $this->getHttpClient();

        $postParams = array('privatekey' => $this->privateKey,
                            'remoteip'   => $this->ip,
                            'challenge'  => $challengeField,
                            'response'   => $responseField);

        $request = new HttpRequest;
        $request->setUri(self::VERIFY_SERVER);
        $request->getPost()->fromArray($postParams);
        $request->setMethod(HttpRequest::METHOD_POST);
        $httpClient->setEncType($httpClient::ENC_URLENCODED);

        return $httpClient->send($request);
    }

    /**
     * Verify the user input
     *
     * This method calls up the post method and returns a
     * Zend_Service_ReCaptcha_Response object.
     *
     * @param string $challengeField
     * @param string $responseField
     * @return \ZendService\ReCaptcha\Response
     */
    public function verify($challengeField, $responseField)
    {
        $response = $this->post($challengeField, $responseField);
        return new Response(null, null, $response);
    }
}
