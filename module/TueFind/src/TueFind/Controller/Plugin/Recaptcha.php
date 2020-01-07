<?php

namespace TueFind\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * unfortunately there is no AbstractBase for Captcha in VuFind,
 * so some methods are copy/pasted from \VuFind\Controller\Plugin\Recaptcha */
class Recaptcha extends AbstractPlugin
{
    /**
     * \TueFind\Service\ReCaptcha
     */
    protected $recaptcha;

    /**
     * String array of forms where ReCaptcha is active
     */
    protected $domains = [];

    /**
     * Captcha activated in config
     */
    protected $active = false;

    /**
     * Flash message or throw Exception
     */
    protected $errorMode = 'flash';

    /**
     * Constructor
     *
     * @param \TueFind\Service\ReCaptcha       $r      Customed reCAPTCHA object
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
     * Flash messages ('flash') or throw exceptions ('throw')
     *
     * @param string $mode 'flash' or 'throw'
     *
     * @return bool
     */
    public function setErrorMode($mode)
    {
        if (in_array($mode, ['flash', 'throw', 'none'])) {
            $this->errorMode = $mode;
            return true;
        }
        return false;
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
     * @return bool
     */
    public function validate()
    {
        if (!$this->active()) {
            return true;
        }

        $responseId = $this->getController()->params()
            ->fromPost('captcha-id');
        $responseInput = $this->getController()->params()
            ->fromPost('captcha-input');
        try {
            $captchaPassed = $this->recaptcha->isValid(['input' => $responseInput,
                                                        'id' => $responseId]);
        } catch (\Exception $e) {
            $captchaPassed = false;
        }
        if (!$captchaPassed && $this->errorMode != 'none') {
            if ($this->errorMode == 'flash') {
                $this->getController()->flashMessenger()
                    ->addMessage('recaptcha_not_passed', 'error');
            } else {
                throw new \Exception('recaptcha_not_passed');
            }
        }
        return $captchaPassed;
    }

    /**
     * Return whether a specific form is set for Captcha in the config
     *
     * @param string $domain The specific config term are we checking; ie. "sms"
     *
     * @return bool
     */
    public function active($domain = false)
    {
        return $this->active
        && ($domain == false || $this->domains === true
        || in_array($domain, $this->domains));
    }
}
