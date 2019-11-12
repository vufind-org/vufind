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
namespace VuFind\Form;

use VuFind\Config\YamlReader;
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
class Form extends \Zend\Form\Form implements
    \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

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
    protected $defaultFormConfig;

    /**
     * Form config
     *
     * @var array
     */
    protected $formElementConfig = [];

    /**
     * Form config
     *
     * @var array
     */
    protected $formConfig;

    /**
     * YAML reader
     *
     * @var YamlReader
     */
    protected $yamlReader;

    /**
     * Constructor
     *
     * @param YamlReader $yamlReader    YAML reader
     * @param array      $defaultConfig Default Feedback configuration (optional)
     *
     * @throws \Exception
     */
    public function __construct(YamlReader $yamlReader, array $defaultConfig = null)
    {
        parent::__construct();

        $this->defaultFormConfig = $defaultConfig;
        $this->yamlReader = $yamlReader;
    }

    /**
     * Set form id
     *
     * @param string $formId Form id
     *
     * @return void
     * @throws Exception
     */
    public function setFormId($formId)
    {
        if (!$config = $this->getFormConfig($formId)) {
            throw new \VuFind\Exception\RecordMissing("Form '$formId' not found");
        }

        $this->messages = [];
        $this->messages['empty']
            = $this->translate('This field is required');

        $this->messages['invalid_email']
            = $this->translate('Email address is invalid');

        $this->formElementConfig
            = $this->parseConfig($formId, $config);

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

        $config = $this->yamlReader->get($confName, false, true);
        $localConfig = $this->yamlReader->get($confName, true, true);

        if (!$formId) {
            $formId = $localConfig['default'] ?? $config['default'] ?? null;
            if (!$formId) {
                return null;
            }
        }

        $config = $config['forms'][$formId] ?? null;
        $localConfig = $localConfig['forms'][$formId] ?? null;

        return $this->mergeLocalConfig($config, $localConfig);
    }

    /**
     * Merge local configuration into default configuration.
     *
     * @param array $config      Default configuration
     * @param array $localConfig Local configuration
     *
     * @return array
     */
    protected function mergeLocalConfig($config, $localConfig)
    {
        return $localConfig ?? $config;
    }

    /**
     * Parse form configuration.
     *
     * @param string $formId Form id
     * @param array  $config Configuration
     *
     * @return array
     */
    protected function parseConfig($formId, $config)
    {
        $formConfig = [
           'id' => $formId,
           'title' => !empty($config['name']) ?: $formId
        ];

        foreach ($this->getFormSettingFields() as $key) {
            if (isset($config[$key])) {
                $formConfig[$key] = $config[$key];
            }
        }

        $this->formConfig = $formConfig;

        $elements = [];
        $configuredElements = $this->getFormElements($config);

        // Add sender contact name & email fields
        $senderName = [
            'name' => 'name', 'type' => 'text', 'label' => 'feedback_name',
            'group' => '__sender__'
        ];
        $senderEmail = [
            'name' => 'email', 'type' => 'email', 'label' => 'feedback_email',
            'group' => '__sender__'
        ];
        if ($formConfig['senderInfoRequired'] ?? false) {
            $senderEmail['required'] = $senderEmail['aria-required']
                = $senderName['required'] = $senderName['aria-required'] = true;
        }

        $configuredElements[] = $senderName;
        $configuredElements[] = $senderEmail;

        foreach ($configuredElements as $el) {
            $element = [];

            $required = ['type', 'name'];
            $optional
                = ['required', 'help','value', 'inputType', 'group', 'placeholder'];
            foreach (array_merge($required, $optional) as $field
            ) {
                if (!isset($el[$field])) {
                    continue;
                }
                $value = $el[$field];
                $element[$field] = $value;
            }

            if (in_array($element['type'], ['checkbox', 'radio'])
                && ! isset($element['group'])
            ) {
                $element['group'] = $element['name'];
            }

            $element['label'] = $this->translate($el['label'] ?? null);

            $elementType = $element['type'];
            if (in_array($elementType, ['checkbox', 'radio', 'select'])) {
                if (empty($el['options']) && empty($el['optionGroups'])) {
                    continue;
                }
                if (isset($el['options'])) {
                    $options = [];
                    $isSelect = $elementType === 'select';
                    $placeholder = $element['placeholder'] ?? null;

                    if ($isSelect && $placeholder) {
                        // Add placeholder option (without value) for
                        // select element.
                        $options[] = [
                            'value' => '',
                            'label' => $this->translate($placeholder),
                            'attributes' => [
                                'selected' => 'selected', 'disabled' => 'disabled'
                            ]
                        ];
                    }
                    foreach ($el['options'] as $option) {
                        if ($isSelect) {
                            $options[] = [
                                'value' => $option,
                                'label' => $this->translate($option)
                            ];
                        } else {
                            $options[$option] = $this->translate($option);
                        }
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
                            $options[$option] = $this->translate($option);
                        }
                        $label = $this->translate($group['label']);
                        $groups[$label] = ['label' => $label, 'options' => $options];
                    }
                    $element['optionGroups'] = $groups;
                }
            }

            $settings = [];
            if (isset($el['settings'])) {
                foreach ($el['settings'] as list($settingId, $settingVal)) {
                    $settingId = trim($settingId);
                    $settingVal = trim($settingVal);
                    if ($settingId === 'placeholder') {
                        $settingVal = $this->translate($settingVal);
                    }
                    $settings[$settingId] = $settingVal;
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

        $elements[]= [
            'type' => 'submit',
            'name' => 'submit',
            'label' => $this->translate('Send')
        ];

        return $elements;
    }

    /**
     * Return a list of field names to read from settings file.
     *
     * @return array
     */
    protected function getFormSettingFields()
    {
        return [
            'recipient', 'title', 'help', 'submit', 'response', 'useCaptcha',
            'enabled', 'onlyForLoggedUsers', 'emailSubject', 'senderInfoRequired'
        ];
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
        case 'checkbox':
            $options = [];
            if (isset($el['options'])) {
                $options = $el['options'];
            }
            $optionElements = [];
            foreach ($options as $key => $val) {
                $optionElements[] = [
                    'label' => $val,
                    'value' => $key,
                    'attributes' => ['id' => $val]
                ];
            }
            $conf['options'] = ['value_options' => $optionElements];
            break;
        case 'radio':
            $options = [];
            if (isset($el['options'])) {
                $options = $el['options'];
            }
            $optionElements = [];
            $first = true;
            foreach ($options as $key => $val) {
                $optionElements[] = [
                    'label' => $val,
                    'value' => $key,
                    'label_attributes' => ['for' => $val],
                    'attributes' => ['id' => $val],
                    'selected' => $first
                ];
                $first = false;
            }
            $conf['options'] = ['value_options' => $optionElements];
            break;
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
            'checkbox' => '\Zend\Form\Element\MultiCheckbox',
            'text' => '\Zend\Form\Element\Text',
            'url' => '\Zend\Form\Element\Url',
            'email' => '\Zend\Form\Element\Email',
            'textarea' => '\Zend\Form\Element\Textarea',
            'radio' => '\Zend\Form\Element\Radio',
            'select' => '\Zend\Form\Element\Select',
            'submit' => '\Zend\Form\Element\Submit'
        ];

        return $map[$type] ?? null;
    }

    /**
     * Check if form enabled.
     *
     * @return bool
     */
    public function isEnabled()
    {
        // Enabled unless explicitly disabled
        return ($this->formConfig['enabled'] ?? true) === true;
    }

    /**
     * Check if the form should use Captcha validation (if supported)
     *
     * @return bool
     */
    public function useCaptcha()
    {
        return (bool)($this->formConfig['useCaptcha'] ?? true);
    }

    /**
     * Check if form is available only for logged users.
     *
     * @return bool
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
     * Return form recipient(s).
     *
     * @return array of reciepients, each consisting of an array with
     * name, email or null if not configured
     */
    public function getRecipient()
    {
        $recipient = $this->formConfig['recipient'] ?? [null];
        $recipients = isset($recipient['email']) || isset($recipient['name'])
            ? [$recipient] : $recipient;

        foreach ($recipients as &$recipient) {
            $recipient['email'] = $recipient['email']
                ?? $this->defaultFormConfig['recipient_email'] ?? null;

            $recipient['name'] = $recipient['name']
                ?? $this->defaultFormConfig['recipient_name'] ?? null;
        }

        return $recipients;
    }

    /**
     * Return form title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->formConfig['title'] ?? null;
    }

    /**
     * Return form help text.
     *
     * @return string|null
     */
    public function getHelp()
    {
        return $this->formConfig['help'] ?? null;
    }

    /**
     * Return form email message subject.
     *
     * @param array $postParams Posted form data
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

        $translated = [];
        foreach ($postParams as $key => $val) {
            $translated["%%{$key}%%"] = $this->translate($val);
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
    public function formatEmailMessage(array $requestParams = [])
    {
        $params = [];
        foreach ($this->getElements() as $el) {
            $type = $el['type'];
            $name = $el['name'];
            if ($type === 'submit') {
                continue;
            }
            $value = $requestParams[$el['name']] ?? null;

            if (in_array($type, ['radio', 'select'])) {
                $value = $this->translate($value);
            } elseif ($type === 'checkbox') {
                $translated = [];
                foreach ($value as $val) {
                    $translated[] = $this->translate($val);
                }
                $value = implode(', ', $translated);
            }

            $label = isset($el['label']) ? $this->translate($el['label']) : null;
            $params[] = ['type' => $type, 'value' => $value, 'label' => $label];
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
            $required = ($el['required'] ?? false) === true;
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
