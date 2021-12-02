<?php
/**
 * Configurable form.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2018-2021.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Form
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFind\Form;

use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Validator\Callback;
use Laminas\Validator\EmailAddress;
use Laminas\Validator\Identical;
use Laminas\Validator\NotEmpty;
use Laminas\View\HelperPluginManager;
use VuFind\Config\YamlReader;

/**
 * Configurable form.
 *
 * @category VuFind
 * @package  Form
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class Form extends \Laminas\Form\Form implements
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
     * Default, untranslated validation messages
     *
     * @var array
     */
    protected $messages = [
        'empty' => 'This field is required',
        'invalid_email' => 'Email address is invalid',
    ];

    /**
     * VuFind main configuration
     *
     * @var array
     */
    protected $vufindConfig;

    /**
     * Default form configuration (from config.ini > Feedback)
     *
     * @var array
     */
    protected $defaultFormConfig;

    /**
     * Form element configuration
     *
     * @var array
     */
    protected $formElementConfig = [];

    /**
     * Form configuration
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
     * View helper manager.
     *
     * @var HelperPluginManager
     */
    protected $viewHelperManager;

    /**
     * Constructor
     *
     * @param YamlReader          $yamlReader        YAML reader
     * @param HelperPluginManager $viewHelperManager View helper manager
     * @param array               $config            VuFind main configuration
     * (optional)
     *
     * @throws \Exception
     */
    public function __construct(
        YamlReader $yamlReader,
        HelperPluginManager $viewHelperManager,
        array $config = null
    ) {
        parent::__construct();

        $this->vufindConfig = $config;
        $this->defaultFormConfig = $config['Feedback'] ?? null;
        $this->yamlReader = $yamlReader;
        $this->viewHelperManager = $viewHelperManager;
    }

    /**
     * Set form id
     *
     * @param string $formId Form id
     * @param array  $params Additional form parameters.
     *
     * @return void
     * @throws Exception
     */
    public function setFormId($formId, $params = [])
    {
        if (!$config = $this->getFormConfig($formId)) {
            throw new \VuFind\Exception\RecordMissing("Form '$formId' not found");
        }

        $this->formElementConfig
            = $this->parseConfig($formId, $config, $params);

        $this->buildForm($this->formElementConfig);
    }

    /**
     * Get display string.
     *
     * @param string $translationKey Translation key
     * @param bool   $escape         Whether to escape the output.
     * Default behaviour is to escape when the translation key does
     * not end with '_html'.
     *
     * @return string
     */
    public function getDisplayString($translationKey, $escape = null)
    {
        $escape = $escape ?? substr($translationKey, -5) !== '_html';
        $helper = $this->viewHelperManager->get($escape ? 'transEsc' : 'translate');
        return $helper($translationKey);
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
     * Check if the form should report referrer url
     *
     * @return bool
     */
    public function reportReferrer()
    {
        return (bool)($this->formConfig['reportReferrer'] ?? false);
    }

    /**
     * Check if the form should report browser's user agent
     *
     * @return bool
     */
    public function reportUserAgent()
    {
        return (bool)($this->formConfig['reportUserAgent'] ?? false);
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
    public function getFormElementConfig(): array
    {
        return $this->formElementConfig;
    }

    /**
     * Return form recipient(s).
     *
     * @param array $postParams Posted form data
     *
     * @return array of reciepients, each consisting of an array with
     * name, email or null if not configured
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRecipient($postParams = null)
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
     * Return form help texts.
     *
     * @return array|null
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
            $translatedVals = array_map([$this, 'translate'], (array)$val);
            $translated["%%{$key}%%"] = implode(', ', $translatedVals);
        }

        return str_replace(
            array_keys($translated),
            array_values($translated),
            $subject
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
     * Return email from address
     *
     * @return string
     */
    public function getEmailFromAddress(): string
    {
        return !empty($this->formConfig['emailFrom']['email'])
            ? $this->formConfig['emailFrom']['email']
            : '';
    }

    /**
     * Return email from name
     *
     * @return string
     */
    public function getEmailFromName(): string
    {
        return !empty($this->formConfig['emailFrom']['name'])
            ? $this->formConfig['emailFrom']['name']
            : '';
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
        foreach ($this->getFormElementConfig() as $el) {
            $type = $el['type'];
            if ($type === 'submit') {
                continue;
            }
            $value = $requestParams[$el['name']] ?? null;

            if (in_array($type, ['radio', 'select'])) {
                $value = $this->translate($value);
            } elseif ($type === 'checkbox' && !empty($value)) {
                $translated = [];
                foreach ($value as $val) {
                    $translated[] = $this->translate($val);
                }
                $value = implode(', ', $translated);
            } elseif ($type === 'date' && !empty($value)) {
                $format = $el['format']
                    ?? $this->vufindConfig['Site']['displayDateFormat'] ?? 'Y-m-d';
                $value = date($format, strtotime($value));
            }
            $label = isset($el['label']) ? $this->translate($el['label']) : null;
            $params[] = $el + compact('value', 'label');
        }

        return [$params, 'Email/form.phtml'];
    }

    /**
     * Retrieve input filter used by this form
     *
     * @return InputFilterInterface
     */
    public function getInputFilter(): InputFilterInterface
    {
        if ($this->inputFilter) {
            return $this->inputFilter;
        }

        $inputFilter = new InputFilter();

        $validators = [
            'email' => [
                'name' => EmailAddress::class,
                'options' => [
                    'message' => $this->getValidationMessage('invalid_email'),
                ]
            ],
            'notEmpty' => [
                'name' => NotEmpty::class,
                'options' => [
                    'message' => [
                        NotEmpty::IS_EMPTY => $this->getValidationMessage('empty'),
                    ]
                ]
            ]
        ];

        $elementObjects = $this->getElements();
        foreach ($this->getFormElementConfig() as $el) {
            $isCheckbox = $el['type'] === 'checkbox';
            $requireOne = $isCheckbox && ($el['requireOne'] ?? false);
            $required = $el['required'] ?? $requireOne;

            $fieldValidators = [];
            if ($required || $requireOne) {
                $fieldValidators[] = $validators['notEmpty'];
            }
            if ($isCheckbox) {
                if ($requireOne) {
                    $fieldValidators[] = [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => function ($value, $context) use ($el) {
                                return
                                    !empty(
                                        array_intersect(
                                            array_keys($el['options']),
                                            $value
                                        )
                                    );
                            }
                         ]
                    ];
                } elseif ($required) {
                    $fieldValidators[] = [
                        'name' => Identical::class,
                        'options' => [
                            'message' => [
                                Identical::MISSING_TOKEN
                                => $this->getValidationMessage('empty')
                            ],
                            'strict' => true,
                            'token' => array_keys($el['options'])
                        ]
                    ];
                }
            }

            if ($el['type'] === 'email') {
                $fieldValidators[] = $validators['email'];
            }

            if (in_array($el['type'], ['checkbox', 'radio', 'select'])) {
                // Add InArray validator from element object instance
                $elementObject = $elementObjects[$el['name']];
                $elementSpec = $elementObject->getInputSpecification();
                $fieldValidators
                    = array_merge($fieldValidators, $elementSpec['validators']);
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
        $localConfig = $config = null;

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
     * @param array  $params Additional form parameters.
     *
     * @return array
     */
    protected function parseConfig($formId, $config, $params)
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

        // Defaults for sender contact name & email fields:
        $senderName = [
            'name' => 'name',
            'type' => 'text',
            'label' => $this->translate('feedback_name'),
            'group' => '__sender__',
            'settings' => [
                'size' => 50
            ]
        ];
        $senderEmail = [
            'name' => 'email',
            'type' => 'email',
            'label' => $this->translate('feedback_email'),
            'group' => '__sender__',
            'settings' => [
                'size' => 254
            ]
        ];
        if ($formConfig['senderInfoRequired'] ?? false) {
            $senderEmail['required'] = $senderEmail['aria-required']
                = $senderName['required'] = $senderName['aria-required'] = true;
        }
        if ($formConfig['senderNameRequired'] ?? false) {
            $senderName['required'] = $senderName['aria-required'] = true;
        }
        if ($formConfig['senderEmailRequired'] ?? false) {
            $senderEmail['required'] = $senderEmail['aria-required'] = true;
        }

        foreach ($configuredElements as $el) {
            $element = [];

            $required = ['type', 'name'];
            $optional = $this->getFormElementSettingFields();
            foreach (array_merge($required, $optional) as $field
            ) {
                if (!isset($el[$field])) {
                    continue;
                }
                $value = $el[$field];
                $element[$field] = $value;
            }

            if (in_array($element['type'], ['checkbox', 'radio'])
                && !isset($element['group'])
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
                        $value = $option['value'] ?? $option;
                        $label = $option['label'] ?? $option;
                        if ($isSelect) {
                            $options[] = [
                                'value' => $value,
                                'label' => $this->translate($label)
                            ];
                        } else {
                            $options[$value] = $this->translate($label);
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
                            $value = $option['value'] ?? $option;
                            $label = $option['label'] ?? $option;

                            $options[$value] = $this->translate($label);
                        }
                        $label = $this->translate($group['label']);
                        $groups[$label] = ['label' => $label, 'options' => $options];
                    }
                    $element['optionGroups'] = $groups;
                }
            }

            $settings = [];
            if (isset($el['settings'])) {
                foreach ($el['settings'] as [$settingId, $settingVal]) {
                    $settingId = trim($settingId);
                    $settingVal = trim($settingVal);
                    if ($settingId === 'placeholder') {
                        $settingVal = $this->translate($settingVal);
                    }
                    $settings[$settingId] = $settingVal;
                }
                $element['settings'] = $settings;
            }

            // Merge sender fields with any existing field definitions:
            if ('name' === $element['name']) {
                $element = array_merge($senderName, $element);
                $senderName = null;
            } elseif ('email' === $element['name']) {
                $element = array_merge($senderEmail, $element);
                $senderEmail = null;
            }

            // Add default field size settings for fields that don't define them:
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

        // Add sender fields if they were not merged in the loop above:
        if ($senderName) {
            $elements[] = $senderName;
        }
        if ($senderEmail) {
            $elements[] = $senderEmail;
        }

        if ($this->reportReferrer()) {
            if ($referrer = ($params['referrer'] ?? false)) {
                $elements[] = [
                    'type' => 'hidden',
                    'name' => 'referrer',
                    'settings' => ['value' => $referrer],
                    'label' => $this->translate('Referrer'),
                ];
            }
        }

        if ($this->reportUserAgent()) {
            if ($userAgent = ($params['userAgent'] ?? false)) {
                $elements[] = [
                    'type' => 'hidden',
                    'name' => 'useragent',
                    'settings' => ['value' => $userAgent],
                    'label' => $this->translate('User Agent'),
                ];
            }
        }

        $elements[] = [
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
            'emailFrom',
            'emailSubject',
            'enabled',
            'help',
            'onlyForLoggedUsers',
            'recipient',
            'reportReferrer',
            'reportUserAgent',
            'response',
            'senderEmailRequired',
            'senderInfoRequired',
            'senderNameRequired',
            'submit',
            'title',
            'useCaptcha',
        ];
    }

    /**
     * Return a list of field names to read from form element settings.
     *
     * @return array
     */
    protected function getFormElementSettingFields()
    {
        return [
            'format',
            'group',
            'help',
            'inputType',
            'maxValue',
            'minValue',
            'placeholder',
            'required',
            'requireOne',
            'value',
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
        case 'date':
            if (isset($el['minValue'])) {
                $attributes['min'] = date('Y-m-d', strtotime($el['minValue']));
            }
            if (isset($el['maxValue'])) {
                $attributes['max'] = date('Y-m-d', strtotime($el['maxValue']));
            }
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
            'checkbox' => '\Laminas\Form\Element\MultiCheckbox',
            'date' => '\Laminas\Form\Element\Date',
            'email' => '\Laminas\Form\Element\Email',
            'hidden' => '\Laminas\Form\Element\Hidden',
            'radio' => '\Laminas\Form\Element\Radio',
            'select' => '\Laminas\Form\Element\Select',
            'submit' => '\Laminas\Form\Element\Submit',
            'text' => '\Laminas\Form\Element\Text',
            'textarea' => '\Laminas\Form\Element\Textarea',
            'url' => '\Laminas\Form\Element\Url',
        ];

        return $map[$type] ?? null;
    }

    /**
     * Get translated validation message.
     *
     * @param string $messageId Message identifier
     *
     * @return string
     */
    protected function getValidationMessage($messageId)
    {
        return $this->translate(
            $this->messages[$messageId] ?? $messageId
        );
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
