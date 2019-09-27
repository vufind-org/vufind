<?php

namespace TueFind\Form;

use VuFind\Config\YamlReader;

class Form extends \VuFind\Form\Form {
    public $defaultSiteConfig;

    // to map a form id to its (optional existing) config key in local overrides
    protected $emailReceiverLocalOverridesConfigKeys = ['AcquisitionRequest' => 'acquisition_request_receivers'];

    public function __construct(YamlReader $yamlReader, array $defaultFormConfig = null, array $defaultSiteConfig = null)
    {
        parent::__construct($yamlReader, $defaultFormConfig);
        $this->defaultSiteConfig = $defaultSiteConfig;
    }

    public function getRecipient()
    {
        $recipient = $this->formConfig['recipient'] ?? null;

        $recipientEmail = $recipient['email'] ?? null;

        // TueFind: local overrides / special forms
        $formId = $this->formConfig['id'];
        if (!isset($recipientEmail) && isset($this->emailReceiverLocalOverridesConfigKeys[$formId])) {
            $configKey = $this->emailReceiverLocalOverridesConfigKeys[$formId];
            if (isset($this->defaultSiteConfig[$configKey]))
                $recipientEmail = $this->defaultSiteConfig[$configKey];
        }

        // TueFind: local overrides / general email address
        $recipientEmail = $recipientEmail
            ?? $this->defaultFormConfig['recipient_email'] ?? $this->defaultSiteConfig['email'] ?? null;

        $recipientName = $recipient['name']
            ?? $this->defaultFormConfig['recipient_name'] ?? null;

        return [
            $recipientName,
            $recipientEmail,
        ];
    }
}
