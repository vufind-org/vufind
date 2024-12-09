<?php

/**
 * Configurable form.
 *
 * PHP version 8
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
use VuFind\Form\Handler\HandlerInterface;
use VuFind\Form\Handler\PluginManager as HandlerManager;

use function count;
use function in_array;
use function is_array;

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
     * Handler plugin manager
     *
     * @var HandlerManager
     */
    protected $handlerManager;

    /**
     * Constructor
     *
     * @param YamlReader          $yamlReader        YAML reader
     * @param HelperPluginManager $viewHelperManager View helper manager
     * @param HandlerManager      $handlerManager    Handler plugin manager
     * @param array               $config            VuFind main configuration
     * (optional)
     *
     * @throws \Exception
     */
    public function __construct(
        YamlReader $yamlReader,
        HelperPluginManager $viewHelperManager,
        HandlerManager $handlerManager,
        array $config = null
    ) {
        parent::__construct();

        $this->vufindConfig = $config;
        $this->defaultFormConfig = $config['Feedback'] ?? null;
        $this->yamlReader = $yamlReader;
        $this->viewHelperManager = $viewHelperManager;
        $this->handlerManager = $handlerManager;
    }

    /**
     * Set form id
     *
     * @param string $formId  Form id
     * @param array  $params  Additional form parameters.
     * @param array  $prefill Prefill form with these values.
     *
     * @return void
     * @throws \Exception
     */
    public function setFormId($formId, $params = [], $prefill = [])
    {
        if (!$config = $this->getFormConfig($formId)) {
            throw new \VuFind\Exception\RecordMissing("Form '$formId' not found");
        }

        $this->formElementConfig
            = $this->parseConfig($formId, $config, $params, $prefill);

        $this->buildForm();
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
        $escape ??= !str_ends_with($translationKey, '_html');
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
     * @return array of recipients, each consisting of an array with
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
            $recipient['email'] ??= $this->defaultFormConfig['recipient_email'] ?? null;
            $recipient['name'] ??= $this->defaultFormConfig['recipient_name'] ?? null;
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
     * Replaces any placeholders for form field values or labels with the submitted
     * values.
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

        $mappings = [];
        foreach ($this->mapRequestParamsToFieldValues($postParams) as $field) {
            // Use translated value as default for backward compatibility:
            $mappings["%%{$field['name']}%%"]
                = $mappings["%%translatedValue:{$field['name']}%%"]
                    = implode(
                        ', ',
                        array_map(
                            [$this, 'translate'],
                            (array)($field['value'] ?? [])
                        )
                    );
            $mappings["%%value:{$field['name']}%%"]
                = implode(', ', (array)($field['value'] ?? []));
            $mappings["%%label:{$field['name']}%%"]
                = implode(', ', (array)($field['valueLabel'] ?? []));
            $mappings["%%translatedLabel:{$field['name']}%%"] = implode(
                ', ',
                array_map(
                    [$this, 'translate'],
                    (array)($field['valueLabel'] ?? [])
                )
            );
        }

        return trim($this->translate($subject, $mappings));
    }

    /**
     * Return response that is shown after successful form submit.
     *
     * @return string
     */
    public function getSubmitResponse()
    {
        return !empty($this->formConfig['response'])
            ? $this->formConfig['response']
            : 'feedback_response';
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
     *
     * @deprecated Use mapRequestParamsToFieldValues
     */
    public function formatEmailMessage(array $requestParams = [])
    {
        return [
            $this->mapRequestParamsToFieldValues($requestParams),
            'Email/form.phtml',
        ];
    }

    /**
     * Map request parameters to field values
     *
     * @param array $requestParams Request parameters
     *
     * @return array
     */
    public function mapRequestParamsToFieldValues(array $requestParams): array
    {
        $params = [];
        foreach ($this->getFormElementConfig() as $el) {
            $type = $el['type'];
            if ($type === 'submit') {
                continue;
            }
            $value = $requestParams[$el['name']] ?? null;
            $valueLabel = null;

            if (in_array($type, ['radio', 'select'])) {
                $option = null;
                if (isset($el['options'])) {
                    $option = $el['options'][$value] ?? null;
                } elseif (isset($el['optionGroups'])) {
                    foreach ($el['optionGroups'] as $group) {
                        if (isset($group['options'][$value])) {
                            $option = $group['options'][$value];
                            break;
                        }
                    }
                }
                if (null === $option) {
                    $value = null;
                    $valueLabel = null;
                } else {
                    $value = $option['value'];
                    $valueLabel = $option['label'];
                }
            } elseif ($type === 'checkbox' && !empty($value)) {
                $labels = [];
                $values = [];
                foreach ($value as $val) {
                    $option = $el['options'][$val] ?? null;
                    if (null === $option) {
                        continue;
                    }
                    $labels[] = $option['label'];
                    $values[] = $option['value'];
                }
                $value = $values;
                $valueLabel = $labels;
            } elseif ($type === 'date' && !empty($value)) {
                $format = $el['format']
                    ?? $this->vufindConfig['Site']['displayDateFormat'] ?? 'Y-m-d';
                $date = strtotime($value);
                $value = date($format, $date);
            }
            $params[] = $el + compact('value', 'valueLabel');
        }

        return $params;
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
                ],
            ],
            'notEmpty' => [
                'name' => NotEmpty::class,
                'options' => [
                    'message' => [
                        NotEmpty::IS_EMPTY => $this->getValidationMessage('empty'),
                    ],
                ],
            ],
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
                            },
                         ],
                    ];
                } elseif ($required) {
                    $fieldValidators[] = [
                        'name' => Identical::class,
                        'options' => [
                            'message' => [
                                Identical::MISSING_TOKEN
                                => $this->getValidationMessage('empty'),
                            ],
                            'strict' => true,
                            'token' => array_keys($el['options']),
                        ],
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
                    'validators' => $fieldValidators,
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
     * @throws \Exception
     */
    protected function getFormConfig($formId = null)
    {
        $confName = 'FeedbackForms.yaml';
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
     * @param array  $config      Default configuration
     * @param ?array $localConfig Local configuration
     *
     * @return array
     */
    protected function mergeLocalConfig($config, $localConfig = null)
    {
        return $localConfig ?? $config;
    }

    /**
     * Parse form configuration.
     *
     * @param string $formId  Form id
     * @param array  $config  Configuration
     * @param array  $params  Additional form parameters.
     * @param array  $prefill Prefill form with these values.
     *
     * @return array
     */
    protected function parseConfig($formId, $config, $params, $prefill)
    {
        $formConfig = [
           'id' => $formId,
           'title' => !empty($config['name']) ?: $formId,
        ];

        foreach ($this->getFormSettingFields() as $key) {
            if (isset($config[$key])) {
                $formConfig[$key] = $config[$key];
            }
        }

        $this->formConfig = $formConfig;

        $prefill = $this->sanitizePrefill($prefill);

        $elements = [];
        $configuredElements = $this->getFormElements($config);

        // Defaults for sender contact name & email fields:
        $senderName = [
            'name' => 'name',
            'type' => 'text',
            'label' => $this->translate('feedback_name'),
            'group' => '__sender__',
        ];
        $senderEmail = [
            'name' => 'email',
            'type' => 'email',
            'label' => $this->translate('feedback_email'),
            'group' => '__sender__',
        ];
        if ($formConfig['senderInfoRequired'] ?? false) {
            $senderEmail['required'] = $senderName['required'] = true;
        }
        if ($formConfig['senderNameRequired'] ?? false) {
            $senderName['required'] = true;
        }
        if ($formConfig['senderEmailRequired'] ?? false) {
            $senderEmail['required'] = true;
        }

        foreach ($configuredElements as $el) {
            $element = [];

            $required = ['type', 'name'];
            $optional = $this->getFormElementSettingFields();
            foreach (
                array_merge($required, $optional) as $field
            ) {
                if (!isset($el[$field])) {
                    continue;
                }
                $value = $el[$field];
                $element[$field] = $value;
            }

            if (
                in_array($element['type'], ['checkbox', 'radio'])
                && !isset($element['group'])
            ) {
                $element['group'] = $element['name'];
            }

            $element['label'] = $el['label'] ?? '';

            $elementType = $element['type'];
            if (in_array($elementType, ['checkbox', 'radio', 'select'])) {
                if ($options = $this->getElementOptions($el)) {
                    $element['options'] = $options;
                } elseif ($optionGroups = $this->getElementOptionGroups($el)) {
                    $element['optionGroups'] = $optionGroups;
                }
            }

            $settings = [];
            foreach ($el['settings'] ?? [] as $setting) {
                if (!is_array($setting)) {
                    continue;
                }
                // Allow both [key => value] and [key, value]:
                if (count($setting) !== 2) {
                    reset($setting);
                    $settingId = trim(key($setting));
                    $settingVal = trim(current($setting));
                } else {
                    $settingId = trim($setting[0]);
                    $settingVal = trim($setting[1]);
                }
                $settings[$settingId] = $settingVal;
            }
            $element['settings'] = $settings;

            // Merge sender fields with any existing field definitions:
            if ('name' === $element['name']) {
                $element = array_replace_recursive($senderName, $element);
                $senderName = null;
            } elseif ('email' === $element['name']) {
                $element = array_replace_recursive($senderEmail, $element);
                $senderEmail = null;
            }

            if ($elementType == 'textarea') {
                if (!isset($element['settings']['rows'])) {
                    $element['settings']['rows'] = 8;
                }
            }

            if (!empty($prefill[$element['name']])) {
                $element['settings']['value'] = $prefill[$element['name']];
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
                    'label' => 'Referrer',
                ];
            }
        }

        if ($this->reportUserAgent()) {
            if ($userAgent = ($params['userAgent'] ?? false)) {
                $elements[] = [
                    'type' => 'hidden',
                    'name' => 'useragent',
                    'settings' => ['value' => $userAgent],
                    'label' => 'User Agent',
                ];
            }
        }

        $elements[] = [
            'type' => 'submit',
            'name' => 'submitButton',
            'label' => 'Send',
        ];

        return $elements;
    }

    /**
     * Get options for an element
     *
     * @param array $element Element configuration
     *
     * @return array
     */
    protected function getElementOptions(array $element): array
    {
        if (!isset($element['options'])) {
            return [];
        }

        $options = [];
        $isSelect = $element['type'] === 'select';
        $placeholder = $element['placeholder'] ?? null;

        if ($isSelect && $placeholder) {
            // Add placeholder option (without value) for
            // select element.
            $options['o0'] = [
                'value' => '',
                'label' => $placeholder,
                'attributes' => [
                    'selected' => 'selected', 'disabled' => 'disabled',
                ],
            ];
        }
        $idx = 0;
        foreach ($element['options'] as $option) {
            ++$idx;
            $value = $option['value'] ?? $option;
            $label = $option['label'] ?? $option;
            $options["o$idx"] = [
                'value' => $value,
                'label' => $label,
            ];
        }
        return $options;
    }

    /**
     * Get option groups for an element
     *
     * @param array $element Element configuration
     *
     * @return array
     */
    protected function getElementOptionGroups(array $element): array
    {
        if (!isset($element['optionGroups'])) {
            return [];
        }
        $groups = [];
        $idx = 0;
        foreach ($element['optionGroups'] as $group) {
            if (empty($group['options'])) {
                continue;
            }
            $options = [];
            foreach ($group['options'] as $option) {
                ++$idx;
                $value = $option['value'] ?? $option;
                $label = $option['label'] ?? $option;

                $options["o$idx"] = [
                    'value' => $value,
                    'label' => $label,
                ];
            }
            $groups[$group['label']] = [
                'label' => $group['label'],
                'options' => $options,
            ];
        }
        return $groups;
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
            'primaryHandler',
            'secondaryHandlers',
            'prefillFields',
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
     * Return field names that should not be prefilled.
     *
     * @return array
     */
    protected function getProtectedFieldNames(): array
    {
        return [
            'referrer',
            'submit',
            'userAgent',
        ];
    }

    /**
     * Build form.
     *
     * @return void
     */
    protected function buildForm()
    {
        foreach ($this->formElementConfig as $el) {
            if ($element = $this->getFormElement($el)) {
                $this->add($element);
            }
        }
    }

    /**
     * Get configuration for a Laminas form element
     *
     * @param array $el Element configuration
     *
     * @return array
     */
    protected function getFormElement($el)
    {
        $type = $el['type'];
        if (!($class = $this->getFormElementClass($type))) {
            return null;
        }

        $conf = [];
        $conf['name'] = $el['name'];

        $conf['type'] = $class;
        $conf['options'] = [];

        $attributes = [
            'id' => $this->getElementId($el['name']),
            'class' => [$el['settings']['class'] ?? null],
        ];

        if ($type !== 'submit') {
            $attributes['class'][] = 'form-control';
        }

        if (!empty($el['required'])) {
            $attributes['required'] = true;
        }
        if (!empty($el['settings'])) {
            $attributes += $el['settings'];
        }
        // Add aria-label only if not a hidden field and no aria-label specified:
        if (
            !empty($el['label']) && 'hidden' !== $type
            && !isset($attributes['aria-label'])
        ) {
            $attributes['aria-label'] = $this->translate($el['label']);
        }

        switch ($type) {
            case 'checkbox':
                $options = [];
                if (isset($el['options'])) {
                    $options = $el['options'];
                }
                $optionElements = [];
                foreach ($options as $key => $item) {
                    $optionElements[] = [
                        'label' => $this->translate($item['label']),
                        'value' => $key,
                        'attributes' => [
                            'id' => $this->getElementId($el['name'] . '_' . $key),
                        ],
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
                foreach ($options as $key => $option) {
                    $elemId = $this->getElementId($el['name'] . '_' . $key);
                    $optionElements[] = [
                        'label' => $this->translate($option['label']),
                        'value' => $key,
                        'label_attributes' => ['for' => $elemId],
                        'attributes' => [
                            'id' => $elemId,
                        ],
                        'selected' => $first,
                    ];
                    $first = false;
                }
                $conf['options'] = ['value_options' => $optionElements];
                break;
            case 'select':
                if (isset($el['options'])) {
                    $options = $el['options'];
                    foreach ($options as $key => &$option) {
                        $option['value'] = $key;
                    }
                    // Unset reference:
                    unset($option);
                    $conf['options'] = ['value_options' => $options];
                } elseif (isset($el['optionGroups'])) {
                    $groups = $el['optionGroups'];
                    foreach ($groups as &$group) {
                        foreach ($group['options'] as $key => &$option) {
                            $option['value'] = $key;
                        }
                        // Unset reference:
                        unset($key);
                    }
                    // Unset reference:
                    unset($group);
                    $conf['options'] = ['value_options' => $groups];
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

    /**
     * Get a complete id for an element
     *
     * @param string $id Element ID
     *
     * @return string
     */
    protected function getElementId(string $id): string
    {
        return 'form_' . $this->getFormId() . '_' . $id;
    }

    /**
     * Get primary form handler
     *
     * @return HandlerInterface
     */
    public function getPrimaryHandler(): HandlerInterface
    {
        $handlerName = ($this->formConfig['primaryHandler'] ?? 'email');
        return $this->handlerManager->get($handlerName);
    }

    /**
     * Get secondary form handlers
     *
     * @return HandlerInterface[]
     */
    public function getSecondaryHandlers(): array
    {
        $handlerNames = (array)($this->formConfig['secondaryHandlers'] ?? []);
        return array_map([$this->handlerManager, 'get'], $handlerNames);
    }

    /**
     * Get current form id/name
     *
     * @return string
     */
    public function getFormId(): string
    {
        return $this->formConfig['id'] ?? '';
    }

    /**
     * Validates prefill data and returns only the prefill values for enabled fields
     *
     * @param array $prefill Prefill data
     *
     * @return array
     */
    protected function sanitizePrefill(array $prefill): array
    {
        $prefillFields = $this->formConfig['prefillFields'] ?? [];
        $prefill = array_filter(
            $prefill,
            function ($key) use ($prefillFields) {
                return in_array($key, $prefillFields)
                    && !in_array($key, $this->getProtectedFieldNames());
            },
            ARRAY_FILTER_USE_KEY
        );
        return $prefill;
    }
}
