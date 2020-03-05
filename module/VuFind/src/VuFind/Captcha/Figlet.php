<?php

namespace VuFind\Captcha;

use Laminas\Mvc\Controller\Plugin\Params;

class Figlet extends AbstractBase {
    
    protected $captcha;
    
    public function __construct() {
        $this->captcha = new \Laminas\Captcha\Figlet([
            'name' => 'figlet_captcha',
            'wordLen' => 6,
            'timeout' => 300,
        ]);
    }
    
    public function getHtml(): string {
        $id = $this->captcha->generate();
        $html = '<pre>' .  $this->captcha->getFiglet()->render($this->captcha->getWord()) . '</pre>';
        $html .= '<p>Please enter what you see: <input name="figlet_captcha_input"></p>';
        $html .= '<input type="hidden" name="figlet_captcha_id" value="'.$id.'">';
        return $html;
    }
    
    public function verify(Params $params): bool {
        $validateParams = [
            'id' => $params->fromPost('figlet_captcha_id'),
            'input' => $params->fromPost('figlet_captcha_input'),
        ];
        return $this->captcha->isValid($validateParams);
    }
}
