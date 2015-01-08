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
    const VERIFY_SERVER = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * Public key used when displaying the captcha
     *
     * @var string
     */
    protected $siteKey = null;

    /**
     * Private key used when verifying user input
     *
     * @var string
     */
    protected $secretKey = null;

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
        'ssl'   => false, /* Use SSL or not when generating the recaptcha */
    );

    /**
     * Options for tailoring reCaptcha
     *
     * See the different options on http://recaptcha.net/apidocs/captcha/client.html
     *
     * @var array
     */
    protected $options = array(
        'callback' => 'recaptchaCallback',
        'lang'     => 'en',
        'render'   => 'onload',
        'theme'    => 'light',
        'type'     => 'image'
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
     * @param string $siteKey
     * @param string $secretKey
     * @param array|Traversable $params
     * @param array|Traversable $options
     * @param string $ip
     */
    public function __construct($siteKey = null, $secretKey = null, $params = null, $options = null, $ip = null, HttpClient $httpClient = null)
    {
        if ($siteKey !== null) {
            $this->setSiteKey($siteKey);
        }

        if ($secretKey !== null) {
            $this->setSecretKey($secretKey);
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
    public function getSiteKey()
    {
        return $this->siteKey;
    }

    /**
     * Set the public key
     *
     * @param string $siteKey
     * @return \ZendService\ReCaptcha\ReCaptcha
     */
    public function setSiteKey($siteKey)
    {
        $this->siteKey = $siteKey;

        return $this;
    }

    /**
     * Get the private key
     *
     * @return string
     */
    public function getSecretKey()
    {
        return $this->secretKey;
    }

    /**
     * Set the private key
     *
     * @param string $secretKey
     * @return \ZendService\ReCaptcha\ReCaptcha
     */
    public function setSecretKey($secretKey)
    {
        $this->secretKey = $secretKey;

        return $this;
    }

    /**
     * Get the HTML code for the captcha
     *
     * This method uses the public key to fetch a recaptcha form.
     *
     * @return string
     * @throws \ZendService\ReCaptcha\Exception
     */
    public function getHtml()
    {
        if ($this->siteKey === null) {
            throw new Exception('Missing site key');
        }

        $host = self::API_SERVER;

        if ((bool) $this->params['ssl'] === true) {
            $host = self::API_SECURE_SERVER;
        }

        $jsSrcOptions = '?render=' . $this->options['render']
                      . '&hl=' . $this->options['lang'];
        if ($this->options['render'] == 'explicit') {
            $jsSrcOptions .= '&onload=' . $this->options['callback'];
        }

        return <<<HTML
<div id="recaptcha_widget" class="g-recaptcha" data-sitekey="{$this->siteKey}" data-theme="{$this->options['theme']}"></div>
<noscript>
    <div style="width: 302px; height: 352px;">
        <div style="width: 302px; height: 352px; position: relative;">
            <div style="width: 302px; height: 352px; position: absolute;">
                <iframe src="{$host}/fallback?k={$this->siteKey}>" frameborder="0" scrolling="no" style="width: 302px; height:352px; border-style: none;"></iframe>
            </div>
            <div style="width: 250px; height: 80px; position: absolute; border-style: none; bottom: 21px; left: 25px; margin: 0px; padding: 0px; right: 25px;">
                <textarea id="g-recaptcha-response" name="g-recaptcha-response" class="g-recaptcha-response" style="width: 250px; height: 80px; border: 1px solid #c1c1c1; margin: 0px; padding: 0px; resize: none;" value=""></textarea>
            </div>
        </div>
    </div>
</noscript>
<script type="text/javascript" src="{$host}.js{$jsSrcOptions}" async defer></script>
HTML;
    }

    /**
     * Post a solution to the verify server
     *
     * @param string $challengeField
     * @param string $responseField
     * @return \Zend\Http\Response
     * @throws \ZendService\ReCaptcha\Exception
     */
    protected function post($responseField)
    {
        if ($this->secretKey === null) {
            throw new Exception('Missing secret key');
        }

        if (empty($responseField)) {
            throw new Exception('Missing response field');
        }

        /* Fetch an instance of the http client */
        $httpClient = new \Zend\Http\Client(
            self::VERIFY_SERVER,
            array(
                'adapter'   => 'Zend\Http\Client\Adapter\Socket',
                'sslcapath' => '/etc/ssl/certs'
            )
        );

        $postParams = array('secret' => $this->secretKey,
                            'response'   => $responseField);

        if ($this->ip !== null) {
            $postParams['remoteip'] = $this->ip;
        }

        $httpClient->setParameterPost($postParams);
        $httpClient->setMethod(HttpRequest::METHOD_POST);
        $httpClient->setEncType($httpClient::ENC_URLENCODED);

        return $httpClient->send();
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
    public function verify($responseField)
    {
        $response = $this->post($responseField);
        return new Response(null, null, $response);
    }
}
