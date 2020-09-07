<?php

namespace TueFind\View\Helper\Root;

class Piwik extends \VuFind\View\Helper\Root\Piwik
{
    protected $auth;

    public function __construct($url, $options, $customVars, $router, $request, ?\VuFind\Auth\Manager $auth)
    {
        parent::__construct($url, $options, $customVars, $router, $request);
        $this->auth = $auth;
    }

    protected function getCustomVarsCode($customVars)
    {
        $customVars['isLoggedIn'] = ((isset($this->auth) && $this->auth->isLoggedIn()) ? 'true' : 'false');
        return parent::getCustomVarsCode($customVars);
    }
}
