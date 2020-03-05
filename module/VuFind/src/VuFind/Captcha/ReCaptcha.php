<?php

namespace VuFind\Captcha;

use Laminas\Mvc\Controller\Plugin\Params;

class ReCaptcha extends AbstractBase {
    protected $recaptcha;
    
    protected $layout;
    
    public function __construct(\VuFind\Service\ReCaptcha $recaptcha, 
                                $layout) {
        $this->recaptcha = $recaptcha;
        $this->layout = $layout;
    }
    
    public function getJsIncludes(): array {
        return ['https://www.google.com/recaptcha/api.js?onload=recaptchaOnLoad&render=explicit&hl=' . $this->layout->userLang];
    }
    
    public function getHtml(): string {
        return $this->recaptcha->getHtml();
    }
    
    public function verify(Params $params): bool {
        $responseField = $params->fromPost('g-recaptcha-response');
        return $this->recaptcha->verify($responseField)->isValid();
    }
}
