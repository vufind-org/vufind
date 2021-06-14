<?php

namespace TueFind\Config;

use Laminas\Config\Config;
use VuFind\Auth\Manager as AuthManager;

class AccountCapabilities extends \VuFind\Config\AccountCapabilities
{
    protected $tuefindConfig;

    public function __construct(Config $config, AuthManager $auth, Config $tuefindConfig)
    {
        parent::__construct($config, $auth);
        $this->tuefindConfig = $tuefindConfig;
    }

    public function getRequestAuthorityRightsSetting(): string
    {
        return isset($this->tuefindConfig->General->request_authority_rights)
            && $this->tuefindConfig->General->request_authority_rights === 'enabled'
            ? 'enabled' : 'disabled';
    }
}
