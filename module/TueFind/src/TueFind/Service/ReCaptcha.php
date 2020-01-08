<?php

namespace TueFind\Service;

/* see https://docs.zendframework.com/zend-captcha/adapters/ */
class ReCaptcha extends \Zend\Captcha\Image
{
    // remove "i", so users can't mistake it for an "l" due to noise
    public static $V  = ["a", "e", "o", "u", "y"];
    public static $VN = ["a", "e", "o", "u", "y", "2", "3", "4", "5", "6", "7", "8", "9"];

    public function getHtml($name = null)
    {
        $this->setFont('/usr/local/var/lib/tuelib/captcha.ttf');
        $this->setImgDir(getenv('VUFIND_LOCAL_DIR') . '/cache/public');

        //$this->setWordlen(8); // default: 8
        //$this->setFontSize(20); // default: 24
        //$this->setWidth(200); // default: 200
        $this->setHeight(75); // default: 50
        $this->setDotNoiseLevel(30); // default: 100
        $this->setLineNoiseLevel(1); // default: 5

        $id = $this->generate();
        $imgUrl = '/cache/' . basename(getenv('VUFIND_LOCAL_DIR')) . '/' . $id . $this->getSuffix();
        $html = '<img src="'.$imgUrl.'">';
        $html .= '<br/><br/>';
        $html .= '<input name="captcha-input"required="required">';
        $html .= '<input type="hidden" name="captcha-id" value="'.$id.'">';
        return $html;
    }
}
