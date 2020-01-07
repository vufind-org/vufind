<?php

namespace TueFind\View\Helper\Bootstrap3;

class Recaptcha extends \VuFind\View\Helper\Bootstrap3\Recaptcha
{
    public function __construct($rc, $config)
    {
        $this->prefixHtml = '<div class="form-group">';
        $this->suffixHtml = '</div>';
        parent::__construct($rc, $config);
    }
}
