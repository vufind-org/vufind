<?php

namespace VuFind\View\Helper\Bootstrap3;

class Captcha extends \VuFind\View\Helper\Root\Captcha
{
    public function __construct($rc, $config)
    {
        $this->prefixHtml = '<div class="form-group">';
        $this->suffixHtml = '</div>';
        parent::__construct($rc, $config);
    }
}
