<?php
/**
 * Configurable form.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2018.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Form
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFind;

use Zend\InputFilter\InputFilter;
use Zend\Validator\EmailAddress;
use Zend\Validator\NotEmpty;

/**
 * Configurable form.
 *
 * @category VuFind
 * @package  Form
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class Form extends \Zend\Form\Form
{
    /**
     * Input filter
     *
     * @var InputFilter
     */
    protected $inputFilter;

    /**
     * Validation messages
     *
     * @var array
     */
    protected $messages;

    /**
     * Default form config (from config.ini > Feedback)
     *
     * @var array
     */
    protected $defaultConfig;

    /**
     * Form config
     *
     * @var array
     */
    protected $formElementConfig;

    /**
     * Form config
     *
     * @var array
     */
    protected $formConfig;

    /**
     * Translator
     *
     * @var Translator
     */
    protected $translator;

    protected $yamlReader;

    /**
     * Constructor
     *
     * @param string             $formId        Form id
     * @param Zend\Config\Config $defaultConfig Default Feedback configuration
     * @param Translator         $translator    Translator
     * @param User               $user          User
     *
     * @throws \Exception
     */
    public function __construct($defaultConfig, $translator, $yamlReader)
    {
        parent::__construct();

        $this->defaultConfig = $defaultConfig;
        $this->translator = $translator;
        $this->yamlReader = $yamlReader;
    }

    public function setFormId($formId)
    {
        if (!$config = $this->getFormConfig($formId)) {
            throw new \VuFind\Exception\RecordMissing("Form '$formId' not found");
            return null;
        }

        $this->messages = [];
        $this->messages['empty']
            = $this->translator->translate('This field is required');

        $this->messages['invalid_email']
            = $this->translator->translate('Email address is invalid');

        $this->formElementConfig
            = $this->parseConfig($formId, $config, $this->translator);

        $this->buildForm($this->formElementConfig);
    }

    /**
     * Get form configuration
     *
     * @param string $formId Form id
     *
     * @return mixed null|array
     * @throws Exception
     */
    protected function getFormConfig($formId = null)
    {
        $confName = 'FeedbackForms.yaml';
        $localConfig = $parentConfig = $config = null;

        $config = $this->yamlReader->get($confName, false, false);
        $localConfig = $this->yamlReader->get($confName, true, false);

        if (!$formId) {
            if (isset($localConfig['default'])) {
                $formId = $localConfig['default'];
            } elseif (isset($config['default'])) {
                $formId = $config['default'];
            }

            if (!$formId) {
                return null;
            }
        }

        $config = $config['forms'][$formId] ?? null;
        $localConfig = $localConfig['forms'][$formId] ?? null;

        $useLocal = isset($localConfig) && !empty($config['allowLocalOverride']);

        if ($useLocal) {
            $config = $localConfig;
        }

        return $config;
    }

    /**
     * Parse form configuration.
     *
     * @param string     $formId     Form id
     * @param array      $config     Configuration
     * @param Translator $translator Translator
     *
     * @return array
     */
    protected function parseConfig($formId, $config, $translator)
    {
        $formConfig = [
           'id' => $formId,
           'title' => !empty($config['name']) ?: $formId
        ];
        $fields = [
            'recipient', 'title', 'help', 'submit', 'response',
            'enabled', 'onlyForLoggedUsers', 'emailSubject', 'senderInfoRequired'
        ];
        foreach ($fields as $key) {
            if (isset($config[$key])) {
                $formConfig[$key] = $config[$key];
            }
        }

        $this->formConfig = $formConfig;

        $elements = [];
        $configuredElements = $this->getFormElements($config);

        // Add sender contact name & email fields
        $senderName = [
            'name' => '__name__', 'type' => 'text', 'label' => 'feedback_name',
            'group' => '__sender__'
        ];
        $senderEmail = [
            'name' => '__email__', 'type' => 'email', 'label' => 'feedback_email',
            'group' => '__sender__'
        ];
        if (isset($formConfig['senderInfoRequired'])
            && $formConfig['senderInfoRequired'] == true
        ) {
            $senderEmail['required'] = $senderEmail['aria-required']
                = $senderName['required'] = $senderName['aria-required'] = true;
        }

        $configuredElements[] = $senderName;
        $configuredElements[] = $senderEmail;

        foreach ($configuredElements as $el) {
            $element = [];

            $required = ['type', 'name'];
            $optional = ['required', 'help','value', 'inputType', 'group'];
            foreach (array_merge($required, $optional) as $field
            ) {
                if (!isset($el[$field])) {
                    continue;
                }
                $value = $el[$field];
                $element[$field] = $value;
            }
            $element['label'] = $translator->translate($el['label']);

            $elementType = $element['type'];
            if ($elementType === 'select') {
                if (empty($el['options']) && empty($el['optionGroups'])) {
                    continue;
                }
                if (isset($el['options'])) {
                    $options = [];
                    foreach ($el['options'] as $option) {
                        $options[$option] = $translator->translate($option);
                    }
                    $element['options'] = $options;
                } elseif (isset($el['optionGroups'])) {
                    $groups = [];
                    foreach ($el['optionGroups'] as $group) {
                        if (empty($group['options'])) {
                            continue;
                        }
                        $options = [];
                        foreach ($group['options'] as $option) {
                            $options[$option] = $translator->translate($option);
                        }
                        $label = $translator->translate($group['label']);
                        $groups[$label] = ['label' => $label, 'options' => $options];
                    }
                    $element['optionGroups'] = $groups;
                }
            }

            $settings = [];
            if (isset($el['settings'])) {
                //die(var_export($el['settings'], true));
                foreach ($el['settings'] as list($settingId, $settingVal)) {
                    $settings[trim($settingId)] = trim($settingVal);
                }
                $element['settings'] = $settings;
            }

            if (in_array($elementType, ['text', 'url', 'email'])
                && !isset($element['settings']['size'])
            ) {
                $element['settings']['size'] = 50;
            }

            if ($elementType == 'textarea') {
                if (!isset($element['settings']['cols'])) {
                    $element['settings']['cols'] = 50;
                }
                if (!isset($element['settings']['rows'])) {
                    $element['settings']['rows'] = 8;
                }
            }
            $elements[] = $element;
        }

        $elements[] = ['type' => 'submit', 'name' => 'submit', 'label' => 'Send'];

        return $elements;
    }

    /**
     * Build form.
     *
     * @param array $elements Parsed configuration elements
     *
     * @return void
     */
    protected function buildForm($elements)
    {
        foreach ($elements as $el) {
            if ($element = $this->getFormElement($el)) {
                $this->add($element);
            }
        }
    }

    /**
     * Get form element attributes.
     *
     * @param array $el Element configuration
     *
     * @return array
     */
    protected function getFormElement($el)
    {
        $type = $el['type'];
        if (!$class = $this->getFormElementClass($type)) {
            return null;
        }

        $conf = [];
        $conf['name'] = $el['name'];

        $conf['type'] = $class;
        $conf['options'] = [];

        $attributes = $el['settings'] ?? [];

        $attributes = [
            'id' => $el['name'],
            'class' => [$el['settings']['class'] ?? null]
        ];

        if ($type !== 'submit') {
            $attributes['class'][] = 'form-control';
        }

        if (!empty($el['required'])) {
            $attributes['required'] = true;
            $attributes['aria-required'] = "true";
        } elseif ($type !== 'submit') {
            $attributes['aria-required'] = "false";
        }
        if (!empty($el['settings'])) {
            $attributes += $el['settings'];
        }
        if (!empty($el['label'])) {
            $attributes['aria-label'] = $el['label'];
        }

        switch ($type) {
        case 'select':
            if (isset($el['options'])) {
                $conf['options'] = ['value_options' => $el['options']];
            } elseif (isset($el['optionGroups'])) {
                $conf['options'] = ['value_options' => $el['optionGroups']];
            }
            break;
        case 'submit':
            $attributes['value'] = $el['label'];
            $attributes['class'][] = 'btn';
            $attributes['class'][] = 'btn-primary';
            break;
        }

        $attributes['class'] = trim(implode(' ', $attributes['class']));
        $conf['attributes'] = $attributes;

        return $conf;
    }

    /**
     * Get form element class.
     *
     * @param string $type Element type
     *
     * @return string|null
     */
    protected function getFormElementClass($type)
    {
        $map = [
            'text' => '\Zend\Form\Element\Text',
            'url' => '\Zend\Form\Element\Url',
            'email' => '\Zend\Form\Element\Email',
            'textarea' => '\Zend\Form\Element\Textarea',
            'select' => '\Zend\Form\Element\Select',
            'submit' => '\Zend\Form\Element\Submit'
        ];

        return $map[$type] ?? null;
    }

    /**
     * Check if form enabled.
     *
     * @return boolean
     */
    public function isEnabled()
    {
        return
            !(isset($this->formConfig['enabled'])
            && $this->formConfig['enabled'] === false);
    }

    /**
     * Check if form is available only for logged users.
     *
     * @return boolean
     */
    public function showOnlyForLoggedUsers()
    {
        return !empty($this->formConfig['onlyForLoggedUsers']);
    }

    /**
     * Return form element configuration.
     *
     * @return array
     */
    public function getElements()
    {
        return $this->formElementConfig;
    }

    /**
     * Return form recipient.
     *
     * @return array with name, email or null if not configured
     */
    public function getRecipient()
    {
        $recipientName = $recipientEmail = null;
        $recipient = $this->formConfig['recipient'] ?? null;

        if (isset($recipient['email'])) {
            $recipientEmail = $recipient['email'];
        } elseif (isset($this->defaultFormConfig['recipient_email'])) {
            $recipientEmail = $this->defaultFormConfig['recipient_email'];
        }

        if (isset($recipient['name'])) {
            $recipientName = $recipient['name'];
        } elseif (isset($this->defaultFormConfig['recipient_name'])) {
            $recipientName = $this->defaultFormConfig['recipient_name'];
        }

        return [
            $recipientName,
            $recipientEmail,
        ];
    }

    /**
     * Return form title.
     *
     * @return string
     */
    public function getTitle()
    {
        return isset($this->formConfig['title']) ? $this->formConfig['title'] : null;
    }

    /**
     * Return form help text.
     *
     * @return string|null
     */
    public function getHelp()
    {
        return isset($this->formConfig['help']) ? $this->formConfig['help'] : null;
    }

    /**
     * Return form email message subject.
     *
     * @return string
     */
    public function getEmailSubject($postParams)
    {
        $subject = 'VuFind Feedback';

        if (!empty($this->formConfig['emailSubject'])) {
            $subject = $this->formConfig['emailSubject'];
        } elseif (!empty($this->defaultFormConfig['email_subject'])) {
            $subject = $this->defaultFormConfig['email_subject'];
        }
        $subject = $this->translator->translate($subject);

        $translated = [];
        foreach ($postParams as $key => $val) {
            $translated["%%{$key}%%"] = $this->translator->translate($val);
        }

        return str_replace(
            array_keys($translated), array_values($translated), $subject
        );
    }

    /**
     * Return reponse that is shown after successful form submit.
     *
     * @return string
     */
    public function getSubmitResponse()
    {
        return !empty($this->formConfig['response'])
            ? $this->formConfig['response']
            : 'Thank you for your feedback.';
    }

    /**
     * Format email message.
     *
     * @param array $requestParams Request parameters
     *
     * @return array Array with template parameters and template name.
     */
    public function formatEmailMessage($requestParams)
    {
        $params = [];
        foreach ($this->getElements() as $el) {
            $type = $el['type'];
            $name = $el['name'];
            if ($type === 'submit') {
                continue;
            }
            $value = $requestParams->fromPost($el['name'], null);

            if ($type === 'select') {
                $value = $this->translator->translate($value);
            }

            $label = $this->translator->translate($el['label']);

            $params[$label] = ['type' => $type, 'value' => $value];
        }

        return [$params, 'Email/form.phtml'];
    }

    /**
     * Retrieve input filter used by this form
     *
     * @return \Zend\InputFilter\InputFilterInterface
     */
    public function getInputFilter()
    {
        if ($this->inputFilter) {
            return $this->inputFilter;
        }

        $inputFilter = new InputFilter();

        $validators = [
            'email' => [
                'name' => EmailAddress::class,
                'options' => [
                    'message' => $this->messages['invalid_email']
                ]
            ],
            'notEmpty' => [
                'name' => NotEmpty::class,
                'options' => [
                    'message' => [
                        NotEmpty::IS_EMPTY => $this->messages['empty']
                    ]
                ]
            ]
        ];

        foreach ($this->getElements() as $el) {
            $required = isset($el['required']) && $el['required'] === true;
            $fieldValidators = [];
            if ($required) {
                $fieldValidators[] = $validators['notEmpty'];
            }
            if ($el['type'] === 'email') {
                $fieldValidators[] = $validators['email'];
            }

            $inputFilter->add(
                [
                    'name' => $el['name'],
                    'required' => $required,
                    'validators' => $fieldValidators
                ]
            );
        }

        $this->inputFilter = $inputFilter;
        return $this->inputFilter;
    }

    /**
     * Get form elements
     *
     * @param array $config Form configuration
     *
     * @return array
     */
    protected function getFormElements($config)
    {
        $elements = [];
        foreach ($config['fields'] as $field) {
            if (!isset($field['type'])) {
                continue;
            }
            $elements[] = $field;
        }
        return $elements;
    }
}
