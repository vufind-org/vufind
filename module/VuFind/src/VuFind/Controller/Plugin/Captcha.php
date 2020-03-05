<?php

namespace VuFind\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class Captcha extends AbstractPlugin
{
    
    protected $captcha;
    
    /**
     * String array of forms where ReCaptcha is active
     */
    protected $domains = [];

    protected $active = false;

    /**
     * Flash message or throw Exception
     */
    protected $errorMode = 'flash';

    public function __construct(?\VuFind\Captcha\AbstractBase $captcha, $config)
    {
        $this->captcha = $captcha;
        if (isset($captcha) && isset($config->Captcha->forms)) {
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
    
    public function verify()
    {
        if (!$this->active()) {
            return true;
        }
        try {
            $captchaPassed = $this->captcha->verify($this->getController()->params());
        } catch (\Exception $e) {
            $captchaPassed = false;
        }
        if (!$captchaPassed && $this->errorMode != 'none') {
            if ($this->errorMode == 'flash') {
                $this->getController()->flashMessenger()
                    ->addMessage('captcha_not_passed', 'error');
            } else {
                throw new \Exception('captcha_not_passed');
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
