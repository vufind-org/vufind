<?php

namespace TueFind\Service;

/* see https://docs.zendframework.com/zend-captcha/adapters/ */
class ReCaptcha extends \Zend\Captcha\Image
{
    public function getHtml($name = null)
    {
        $this->setFont('/usr/local/var/lib/tuelib/captcha.ttf');
        $this->setImgDir(getenv('VUFIND_LOCAL_DIR') . '/cache/public');

        //$this->setWordlen(8); // default: 8
        $this->setDotNoiseLevel(50); // default: 100
        $this->setLineNoiseLevel(3); // default: 5

        $id = $this->generate();
        $imgUrl = '/cache/' . basename(getenv('VUFIND_LOCAL_DIR')) . '/' . $id . $this->getSuffix();
        $html = '<img src="'.$imgUrl.'">';
        $html .= '<br/><br/>';
        $html .= '<input name="captcha-input"required="required">';
        $html .= '<input type="hidden" name="captcha-id" value="'.$id.'">';
        return $html;
    }
}
