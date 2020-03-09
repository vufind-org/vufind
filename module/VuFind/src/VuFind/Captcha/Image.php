<?php

namespace VuFind\Captcha;

class Image extends LaminasBase {
    public function getHtml(): string {
        $id = $this->captcha->generate();
        $imgUrl = '/vufind/cache/' . $id . $this->captcha->getSuffix();
        $html = '<img src="'.$imgUrl.'">';
        $html .= '<br/><br/>';
        $html .= '<input name="'.$this->captchaHtmlInputId.'" required="required">';
        $html .= '<input type="hidden" name="'.$this->captchaHtmlInternalId.'" value="'.$id.'">';
        return $html;
    }
}
