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

    /**
     * Are users allowed to use PDA?
     */
    public function getPdaSetting(): string
    {
        return isset($this->tuefindConfig->General->pda)
            && $this->tuefindConfig->General->pda === 'enabled'
            ? 'enabled' : 'disabled';
    }

    /**
     * Are users allowed to upload publications for their registered authorities?
     */
    public function getPublicationSetting(): string
    {
        return isset($this->tuefindConfig->Publication->publications)
            && $this->tuefindConfig->Publication->publications === 'enabled'
            ? 'enabled' : 'disabled';
    }

    /**
     * Are users allowed to request rights on authority datasets?
     */
    public function getRequestAuthorityRightsSetting(): string
    {
        return isset($this->tuefindConfig->General->request_authority_rights)
            && $this->tuefindConfig->General->request_authority_rights === 'enabled'
            ? 'enabled' : 'disabled';
    }

    /**
     * Are users allowed to subscribe rss feeds?
     */
    public function getRssSubscriptionSetting(): string
    {
        return isset($this->tuefindConfig->General->rss_subscriptions)
            && $this->tuefindConfig->General->rss_subscriptions === 'enabled'
            ? 'enabled' : 'disabled';
    }

    /**
     * Are users allowed to subscribe journals?
     */
    public function getSubscriptionSetting(): string
    {
        return isset($this->tuefindConfig->General->subscriptions)
            && $this->tuefindConfig->General->subscriptions === 'enabled'
            ? 'enabled' : 'disabled';
    }
}
