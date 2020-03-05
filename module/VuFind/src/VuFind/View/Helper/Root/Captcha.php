<?php

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;

class Captcha extends AbstractHelper
{
    protected $captcha;
    protected $config;
    
    /**
     * HTML prefix for ReCaptcha output.
     *
     * @var string
     */
    protected $prefixHtml = '';

    /**
     * HTML suffix for ReCaptcha output.
     *
     * @var string
     */
    protected $suffixHtml = '';
    
    public function __construct(?\VuFind\Captcha\AbstractBase $captcha, $config)
    {
        $this->captcha = $captcha;
        $this->config = $config;
    }

    public function __invoke()
    {
        return $this;
    }

    public function html($useCaptcha = true, $wrapHtml = true): string
    {
        if (!isset($this->captcha) || !$useCaptcha) {
            return false;
        }
        if (!$wrapHtml) {
            return $this->captcha->getHtml();
        }
        return $this->prefixHtml . $this->captcha->getHtml() . $this->suffixHtml;
    }
    
    public function js(): array {
        if (isset($this->captcha)) {
            return $this->captcha->getJsIncludes();
        } else {
            return [];
        }
    }

    protected function active(): bool
    {
        return isset($this->captcha) && isset($config->Captcha->forms);
    }
}
