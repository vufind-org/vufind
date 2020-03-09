<?php

namespace VuFind\Captcha;

class Figlet extends LaminasBase {
    public function getHtml(): string {
        $id = $this->captcha->generate();
        $html = '<pre>' .  $this->captcha->getFiglet()->render($this->captcha->getWord()) . '</pre>';
        $html .= '<p>Please enter what you see: <input name="'.$this->captchaHtmlInputId.'" required="required"></p>';
        $html .= '<input type="hidden" name="'.$this->captchaHtmlInternalId.'" value="'.$id.'">';
        return $html;
    }
}
