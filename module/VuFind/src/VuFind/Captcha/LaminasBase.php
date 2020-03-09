<?php

namespace VuFind\Captcha;

use Laminas\Mvc\Controller\Plugin\Params;

abstract class LaminasBase extends AbstractBase {
    
    protected $captcha;
    
    protected $captchaHtmlInternalId = 'captcha-id';
    protected $captchaHtmlInputId = 'captcha-input';
    
    public function __construct(\Laminas\Captcha\AbstractWord $captcha) {
        $this->captcha = $captcha;
    }
    
    public function verify(Params $params): bool {
        $validateParams = [
            'id' => $params->fromPost($captchaHtmlInternalId),
            'input' => $params->fromPost($captchaHtmlInputId),
        ];
        return $this->captcha->isValid($validateParams);
    }
}
