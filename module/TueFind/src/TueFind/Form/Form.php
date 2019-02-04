<?php

namespace TueFind\Form;

use VuFind\Config\YamlReader;

class Form extends \VuFind\Form\Form {
    public $defaultSiteConfig;

    public function __construct(YamlReader $yamlReader, array $defaultFormConfig = null, array $defaultSiteConfig = null)
    {
        parent::__construct($yamlReader, $defaultFormConfig);
        $this->defaultSiteConfig = $defaultSiteConfig;
    }

    public function getRecipient()
    {
        $recipient = $this->formConfig['recipient'] ?? null;

        // TueFind: use Site email as fallback (local_overrides)
        $recipientEmail = $recipient['email']
            ?? $this->defaultFormConfig['recipient_email'] ?? $this->defaultSiteConfig['email'] ?? null;

        $recipientName = $recipient['name']
            ?? $this->defaultFormConfig['recipient_name'] ?? null;

        return [
            $recipientName,
            $recipientEmail,
        ];
    }
}
