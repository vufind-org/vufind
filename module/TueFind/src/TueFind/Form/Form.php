<?php

namespace TueFind\Form;

use Laminas\View\HelperPluginManager;
use VuFind\Config\YamlReader;


class Form extends \VuFind\Form\Form {
    public $defaultSiteConfig;

    // to map a form id to its (optional existing) config key in local overrides
    protected $emailReceiverLocalOverridesConfigKeys = ['AcquisitionRequest' => 'acquisition_request_receivers'];

    public function __construct(YamlReader $yamlReader, HelperPluginManager $viewHelperManager,
                                array $defaultFeedbackConfig = null, array $defaultSiteConfig = null)
    {
        parent::__construct($yamlReader, $viewHelperManager, $defaultFeedbackConfig);
        $this->defaultSiteConfig = $defaultSiteConfig;
    }

    public function getRecipient($postParams = null)
    {
        $recipient = $this->formConfig['recipient'] ?? [null];
        $recipients = isset($recipient['email']) || isset($recipient['name'])
            ? [$recipient] : $recipient;

        $formId = $this->formConfig['id'];
        foreach ($recipients as &$recipient) {
            $recipientEmail = $recipient['email'] ?? null;

            // TueFind: local overrides / special forms
            if (!isset($recipient['email']) && isset($this->emailReceiverLocalOverridesConfigKeys[$formId])) {
                $configKey = $this->emailReceiverLocalOverridesConfigKeys[$formId];
                if (isset($this->defaultSiteConfig[$configKey]))
                    $recipient['email'] = $this->defaultSiteConfig[$configKey];
            }

            // TueFind: local overrides / general email address
            $recipient['email'] = $recipient['email']
                ?? $this->defaultFormConfig['recipient_email'] ?? $this->defaultSiteConfig['email'] ?? null;

            $recipient['name'] = $recipient['name']
                ?? $this->defaultFormConfig['recipient_name'] ?? null;
        }

        return $recipients;
    }
}
