<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */
namespace LosReCaptcha\Captcha;

use Traversable;
use LosReCaptcha\Service\ReCaptcha as ReCaptchaService;
use Zend\Captcha\ReCaptcha as ZendReCaptcha;
use Zend\Stdlib\ArrayUtils;

/**
 * ReCaptcha adapter
 *
 * Allows to insert captchas driven by ReCaptcha service
 *
 * @see http://recaptcha.net/apidocs/captcha/
 */
class ReCaptcha extends ZendReCaptcha
{

    /**
     * Error messages
     *
     * @var array
     */
    protected $messageTemplates = array(
        self::MISSING_VALUE => 'Missing captcha field',
        self::ERR_CAPTCHA => 'Failed to validate captcha',
        self::BAD_CAPTCHA => 'Captcha value is wrong: %value%'
    );

    /**
     * Constructor
     *
     * @param null|array|Traversable $options
     */
    public function __construct($options = null)
    {
        $this->service = new ReCaptchaService();
        $this->serviceParams = $this->getService()->getParams();
        $this->serviceOptions = $this->getService()->getOptions();

        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        }

        if (isset($this->messageTemplates)) {
            $this->abstractOptions['messageTemplates'] = $this->messageTemplates;
        }

        if (isset($this->messageVariables)) {
            $this->abstractOptions['messageVariables'] = $this->messageVariables;
        }

        if (is_array($options)) {
            $this->setOptions($options);
        }

        if (! empty($options)) {
            if (array_key_exists('secret_key', $options)) {
                $this->getService()->setSecretKey($options['secret_key']);
            } elseif (array_key_exists('private_key', $options)) {
                $this->getService()->setPrivateKey($options['private_key']);
            }
            if (array_key_exists('site_key', $options)) {
                $this->getService()->setSiteKey($options['site_key']);
            } elseif (array_key_exists('public_key', $options)) {
                $this->getService()->setPublicKey($options['public_key']);
            }
            $this->setOptions($options);
        }
    }

    /**
     * Validate captcha
     *
     * @see \Zend\Validator\ValidatorInterface::isValid()
     * @param mixed $value
     * @param mixed $context
     * @return bool
     */
    public function isValid($value, $context = null)
    {
        if (! is_array($value) && ! is_array($context)) {
            $this->error(self::MISSING_VALUE);
            return false;
        }

        if (! is_array($value) && is_array($context)) {
            $value = $context;
        }

        if (empty($value[$this->RESPONSE])) {
            $this->error(self::MISSING_VALUE);
            return false;
        }

        $service = $this->getService();

        $res = $service->verify($value[$this->RESPONSE]);
        if (! $res) {
            $this->error(self::ERR_CAPTCHA);
            return false;
        }

        if (! $res->isValid()) {
            $this->error(self::BAD_CAPTCHA, $res->getErrorCode());
            $service->setParam('error', $res->getErrorCode());
            return false;
        }

        return true;
    }

    /**
     * Get helper name used to render captcha
     *
     * @return string
     */
    public function getHelperName()
    {
        return "losrecaptcha/recaptcha";
    }
}
