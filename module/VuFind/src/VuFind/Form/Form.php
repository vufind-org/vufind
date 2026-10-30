<?php

/**
 * Configurable form.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2018-2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Form
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFind\Form;

use DateTime;
use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\NoRFCWarningsValidation;
use Exception;
use Laminas\Uri\UriFactory;
use Laminas\View\HelperPluginManager;
use VuFind\Date\Converter as DateConverter;
use VuFind\Form\Handler\HandlerInterface;
use VuFind\Form\Handler\PluginManager as HandlerManager;
use VuFind\ServiceManager\Factory\Autowire;

use function count;
use function in_array;
use function is_array;
use function is_string;

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
class Form implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Default, untranslated validation messages.
     *
     * @var array
     */
    protected $messages = [
        'empty' => 'This field is required',
        'invalid_email' => 'Email address is invalid',
    ];

    /**
     * Default form configuration (from config.ini > Feedback).
     *
     * @var array
     */
    protected array $defaultFormConfig;

    /**
     * Form element configuration.
     *
     * @var array
     */
    protected array $formElementConfig = [];

    /**
     * Form configuration.
     *
     * @var array
     */
    protected array $formConfig;

    /**
     * Field data.
     *
     * @var array
     */
    protected array $fieldData = [];

    /**
     * Constructor.
     *
     * @param HelperPluginManager $viewHelperManager  View helper manager
     * @param HandlerManager      $handlerManager     Handler plugin manager
     * @param DateConverter       $dateConverter      Date converter
     * @param array               $config             VuFind main configuration
     * @param array               $feedbackFormConfig Feedback form config
     *
     * @throws \Exception
     */
    public function __construct(
        #[Autowire(service: 'ViewHelperManager')]
        protected HelperPluginManager $viewHelperManager,
        protected HandlerManager $handlerManager,
        protected DateConverter $dateConverter,
        #[Autowire(config: 'config')]
        protected array $config,
        #[Autowire(config: 'FeedbackForms', configType: 'yaml')]
        protected array $feedbackFormConfig
    ) {
        $this->defaultFormConfig = $this->config['Feedback'] ?? [];
    }

    /**
     * Set form id.
     *
     * @param string $formId  Form id
     * @param array  $params  Additional form parameters.
     * @param array  $prefill Prefill form with these values.
     *
     * @return void
     * @throws \Exception
     */
    public function setFormId(string $formId, array $params = [], array $prefill = []): void
    {
        if (!$config = $this->getFormConfig($formId)) {
            throw new \VuFind\Exception\RecordMissing("Form '$formId' not found");
        }

        $this->formElementConfig = $this->parseConfig($formId, $config, $params, $prefill);
    }

    /**
     * Get field data.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->fieldData;
    }

    /**
     * Set field data using exposed values.
     *
     * @param array $requestParams Request parameters
     *
     * @return void
     */
    public function setData(array $requestParams): void
    {
        $this->fieldData = $requestParams;
    }

    /**
     * Get display string.
     *
     * @param string $translationKey Translation key
     * @param ?bool  $escape         Whether to escape the output.
     * Default behaviour is to escape when the translation key does
     * not end with '_html'.
     *
     * @return string
     */
    public function getDisplayString(string $translationKey, ?bool $escape = null): string
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
    public function isEnabled(): bool
    {
        // Enabled unless explicitly disabled
        return ($this->formConfig['enabled'] ?? true) === true;
    }

    /**
     * Check if the form should use Captcha validation (if supported).
     *
     * @return bool
     */
    public function useCaptcha(): bool
    {
        return (bool)($this->formConfig['useCaptcha'] ?? true);
    }

    /**
     * Check if the form should report referrer url.
     *
     * @return bool
     */
    public function reportReferrer(): bool
    {
        return (bool)($this->formConfig['reportReferrer'] ?? false);
    }

    /**
     * Check if the form should report browser's user agent.
     *
     * @return bool
     */
    public function reportUserAgent(): bool
    {
        return (bool)($this->formConfig['reportUserAgent'] ?? false);
    }

    /**
     * Check if form is available only for logged users.
     *
     * @return bool
     */
    public function showOnlyForLoggedUsers(): bool
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
     * Return form action route if set in config.
     *
     * @return string Form action route or feedback-form as default
     */
    public function getFormActionRoute(): string
    {
        return $this->formConfig['formActionRoute'] ?? 'feedback-form';
    }

    /**
     * Return form recipient(s).
     *
     * @param ?array $postParams Posted form data
     *
     * @return array of recipients, each consisting of an array with
     * name, email or null if not configured
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRecipient(?array $postParams = null): array
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
     * @return ?string
     */
    public function getTitle(): ?string
    {
        return $this->formConfig['title'] ?? null;
    }

    /**
     * Return form help texts.
     *
     * @return ?array
     */
    public function getHelp(): ?array
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
    public function getEmailSubject(array $postParams): string
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
            $mappings["%%{$field['name']}%%"] = $mappings["%%translatedValue:{$field['name']}%%"]
                = implode(', ', array_map([$this, 'translate'], (array)($field['value'] ?? [])));
            $mappings["%%value:{$field['name']}%%"]
                = implode(', ', (array)($field['value'] ?? []));
            $mappings["%%label:{$field['name']}%%"]
                = implode(', ', (array)($field['valueLabel'] ?? []));
            $mappings["%%translatedLabel:{$field['name']}%%"]
                = implode(', ', array_map([$this, 'translate'], (array)($field['valueLabel'] ?? [])));
        }

        return trim($this->translate($subject, $mappings));
    }

    /**
     * Return response that is shown after successful form submit.
     *
     * @return string
     */
    public function getSubmitResponse(): string
    {
        return !empty($this->formConfig['response'])
            ? $this->formConfig['response']
            : 'feedback_response';
    }

    /**
     * Return email from address.
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
     * Return email from name.
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
    public function formatEmailMessage(array $requestParams = []): array
    {
        return [
            $this->mapRequestParamsToFieldValues($requestParams),
            'Email/form.phtml',
        ];
    }

    /**
     * Map request parameters to actual field values.
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
            $requestValue = $value = $requestParams[$el['name']] ?? null;
            $valueLabel = null;

            if (in_array($type, ['radio', 'select'])) {
                $value = null;
                $valueLabel = null;
                if ('select' === $type && $groups = $this->getElementOptionGroups($el)) {
                    foreach ($groups as $group) {
                        foreach ($group['options'] as $option) {
                            if ($requestValue === $option['value']) {
                                $value = $option['internalValue'];
                                $valueLabel = $option['label'];
                                break 2;
                            }
                        }
                    }
                } else {
                    foreach ($this->getElementOptions($el) as $option) {
                        if ($requestValue === $option['value']) {
                            $value = $option['internalValue'];
                            $valueLabel = $option['label'];
                            break;
                        }
                    }
                }
            } elseif ('checkbox' === $type) {
                $value = [];
                $valueLabel = [];
                foreach ($this->getElementOptions($el) as $option) {
                    if (in_array($option['value'], $requestValue ?? [], true)) {
                        $value[] = $option['internalValue'];
                        $valueLabel[] = $option['label'];
                    }
                }
            } elseif ($type === 'date' && !empty($value)) {
                $format = $el['format'] ?? $this->config['Site']['displayDateFormat'] ?? 'Y-m-d';
                $date = strtotime($requestValue);
                $value = date($format, $date);
            }
            $params[] = $el + compact('value', 'valueLabel');
        }

        return $params;
    }

    /**
     * Check form validity.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        foreach ($this->getFormElementConfig() as $el) {
            $isCheckbox = $el['type'] === 'checkbox';
            $requireOne = $isCheckbox && ($el['requireOne'] ?? false);
            $required = $el['required'] ?? false;

            $value = $this->fieldData[$el['name']] ?? null;

            if ($isCheckbox) {
                $validOptions = $this->getValidOptions($el);
                // requireOne means that at least one checkbox must be checked:
                if ($requireOne && !$value) {
                    return false;
                }
                // required means that all checkboxes must be checked:
                if ($required && (array)$value !== $validOptions) {
                    return false;
                }
                // Ensure that any values are included in valid options:
                if ($value && array_diff((array)$value, $validOptions)) {
                    return false;
                }
            } elseif ($required && (null === $value || '' === $value)) {
                return false;
            }
            switch ($el['type']) {
                case 'text': // no default
                    break;
                case 'textarea': // no default
                    break;
                case 'date':
                    if (
                        $value
                        && (!is_string($value)
                        || !$this->isValidDate($value, $el['minValue'] ?? null, $el['maxValue'] ?? null))
                    ) {
                        return false;
                    }
                    break;
                case 'email':
                    if ($value && (!is_string($value) || !$this->isValidEmail($value))) {
                        return false;
                    }
                    break;
                case 'url':
                    if ($value && (!is_string($value) || !$this->isValidUri($value))) {
                        return false;
                    }
                    break;
                case 'radio':
                case 'select':
                    $validOptions = $this->getValidOptions($el);
                    if (null !== $value && !in_array($value, $validOptions, true)) {
                        return false;
                    }
                    break;
            }
        }
        return true;
    }

    /**
     * Get configuration for an HTML element.
     *
     * Note: Any labels included in attributes are translated for use with the htmlAttributes helper. Other labels are
     * not translated here.
     *
     * @param array $el Element configuration
     *
     * @return array
     */
    public function getHtmlElementConfig(array $el): array
    {
        $id = $this->getElementId($el['name']);
        $conf = [
            'id' => $id,
            'type' => $el['type'],
            'options' => [],
        ];

        $value = $this->fieldData[$el['name']] ?? $el['settings']['value'] ?? null;
        $class = $el['settings']['class'] ?? null;
        $attributes = [
            'name' => $el['name'],
            'id' => $id,
            'class' => $class ? [$class] : [],
        ];
        if ($placeholder = $el['placeholder'] ?? null) {
            $attributes['placeholder'] = $this->translate($placeholder);
        }

        if (!in_array($el['type'], ['submit', 'radio', 'checkbox', 'select'], true)) {
            $attributes['class'][] = 'form-control';
        }

        if (!empty($el['required'])) {
            $attributes['required'] = null;
        }
        if (!empty($el['settings'])) {
            $attributes += $el['settings'];
        }
        // Add autocomplete only for text and email fields
        if (!empty($el['autocomplete']) && in_array($el['type'], ['text', 'email'])) {
            $attributes['autocomplete'] = $el['autocomplete'];
        }
        // Add aria-label only if not a hidden field and no aria-label specified:
        if (
            !empty($el['label']) && 'hidden' !== $el['type']
            && !isset($attributes['aria-label'])
        ) {
            $attributes['aria-label'] = $this->translate($el['label']);
        }

        switch ($el['type']) {
            case 'text':
                $attributes['type'] = 'text';
                $attributes['value'] = $value;
                break;
            case 'textarea':
                $conf['value'] = $value;
                break;
            case 'email':
                $attributes['type'] = 'email';
                $attributes['value'] = $value;
                break;
            case 'url':
                $attributes['type'] = 'url';
                $attributes['value'] = $value;
                break;
            case 'hidden':
                $attributes['type'] = 'hidden';
                $attributes['value'] = $value;
                break;
            case 'date':
                $attributes['type'] = 'date';
                $attributes['value'] = $value;
                if (isset($el['minValue'])) {
                    $attributes['min'] = date('Y-m-d', strtotime($el['minValue']));
                }
                if (isset($el['maxValue'])) {
                    $attributes['max'] = date('Y-m-d', strtotime($el['maxValue']));
                }
                break;
            case 'checkbox':
            case 'radio':
                $attributes['class'][] = 'form-check-input';
                $conf['options'] = $this->getElementOptions($el, $value);
                break;
            case 'select':
                $attributes['class'][] = 'form-select';
                $conf['options'] = $this->getElementOptions($el, $value);
                $conf['optionGroups'] = $this->getElementOptionGroups($el, $value);
                break;
            case 'submit':
                $attributes['type'] = 'submit';
                $attributes['value'] = $this->translate($el['label']);
                $attributes['class'][] = 'btn';
                $attributes['class'][] = 'btn-primary';
                break;
        }

        $conf['attributes'] = $attributes;
        return $conf;
    }

    /**
     * Prepare the form for use.
     *
     * @return static
     */
    public function prepare(): static
    {
        // By default this does nothing, but is available for back-compatibility and downstream use.
        return $this;
    }

    /**
     * Check date validity.
     *
     * @param string  $dateStr  Date
     * @param ?string $minValue Minimum value (strtotime compatible)
     * @param ?string $maxValue Maximum value (strtotime compatible)
     *
     * @return bool
     */
    protected function isValidDate(string $dateStr, ?string $minValue, ?string $maxValue): bool
    {
        try {
            $date = $this->dateConverter->convertToDateTime('Y-m-d', $dateStr);
            if ($minValue) {
                $minDate = new DateTime($minValue);
                if ($date->getTimestamp() < $minDate->getTimestamp()) {
                    return false;
                }
            }
            if ($maxValue) {
                $maxDate = new DateTime($maxValue);
                if ($date->getTimestamp() > $maxDate->getTimestamp()) {
                    return false;
                }
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check email validity.
     *
     * @param string $email Email
     *
     * @return bool
     */
    protected function isValidEmail(string $email): bool
    {
        $validator = new EmailValidator();
        return $validator->isValid($email, new NoRFCWarningsValidation());
    }

    /**
     * Check URI validity.
     *
     * @param string $uri URI
     *
     * @return bool
     */
    protected function isValidUri(string $uri): bool
    {
        $uri = UriFactory::factory($uri);
        return $uri->isValid();
    }

    /**
     * Get valid option values for a checkbox, radio or select element.
     *
     * @param array $element Element
     *
     * @return array
     */
    protected function getValidOptions(array $element): array
    {
        $result = [];
        foreach ($this->getElementOptions($element) as $option) {
            if (!($option['disabled'] ?? false)) {
                $result[] = $option['value'];
            }
        }
        foreach ($this->getElementOptionGroups($element) as $group) {
            foreach ($group['options'] as $option) {
                if (!($option['disabled'] ?? false)) {
                    $result[] = $option['value'];
                }
            }
        }
        return $result;
    }

    /**
     * Get form configuration.
     *
     * @param ?string $formId Form id
     *
     * @return ?array
     * @throws \Exception
     */
    protected function getFormConfig(?string $formId = null): ?array
    {
        if (!$formId) {
            $formId = $this->feedbackFormConfig['default'] ?? null;
            if (!$formId) {
                return null;
            }
        }
        return $this->feedbackFormConfig['forms'][$formId] ?? null;
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
    protected function parseConfig(string $formId, array $config, array $params, array $prefill): array
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
            'autocomplete' => 'email',
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
            foreach (array_merge($required, $optional) as $field) {
                if (!isset($el[$field])) {
                    continue;
                }
                $value = $el[$field];
                $element[$field] = $value;
            }

            if (in_array($element['type'], ['checkbox', 'radio']) && !isset($element['group'])) {
                $element['group'] = $element['name'];
            }

            $element['label'] = $el['label'] ?? '';

            $elementType = $element['type'];
            if (in_array($elementType, ['checkbox', 'radio', 'select'])) {
                $element['options'] = $this->parseOptions($el['options'] ?? []);
                if ('select' === $elementType) {
                    $element['optionGroups'] = $this->parseOptionGroups($el['optionGroups'] ?? []);
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
                $element['settings']['rows'] ??= 8;
            }

            if (!empty($prefill[$element['name']])) {
                $element['settings']['value'] = $prefill[$element['name']];
            }

            if (is_string($element['help'] ?? null)) {
                $element['help'] = [
                    'pre' => $element['help'],
                    'post' => '',
                ];
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
     * Parse field options.
     *
     * @param array $options Options
     *
     * @return array
     */
    protected function parseOptions(array $options): array
    {
        array_walk(
            $options,
            function (&$option): void {
                if (is_string($option)) {
                    $option = [
                        'value' => $option,
                        'label' => $option,
                    ];
                }
            }
        );
        return $options;
    }

    /**
     * Parse field option groups.
     *
     * @param array $groups Option groups
     *
     * @return array
     */
    protected function parseOptionGroups(array $groups): array
    {
        array_walk(
            $groups,
            function (&$group): void {
                $group['options'] = $this->parseOptions($group['options'] ?? []);
            }
        );
        return $groups;
    }

    /**
     * Get options for an element.
     *
     * @param array             $element      Element configuration
     * @param string|array|null $currentValue Field value(s) for checked/selected status (optional)
     *
     * @return array
     */
    protected function getElementOptions(array $element, string|array|null $currentValue = null): array
    {
        if (!isset($element['options'])) {
            return [];
        }

        $options = [];
        $placeholder = $element['placeholder'] ?? null;

        if ('select' === $element['type'] && $placeholder) {
            // Add placeholder option (without value) for select element:
            $options[] = [
                'value' => '',
                'internalValue' => '',
                'label' => $placeholder,
                'disabled' => true,
                // Set selected if no other value is currently selected:
                'selected' => !$currentValue,
            ];
        } elseif ('radio' === $element['type']) {
            // Mark first element selected by default:
            $currentValue ??= 'o1';
        }
        $idx = 0;
        foreach ($element['options'] as $option) {
            ++$idx;
            $value = "o$idx";
            $internalValue = $option['value'] ?? $option;
            $options[] = [
                'value' => $value,
                'internalValue' => $internalValue,
                'label' => $option['label'] ?? $option,
                'selected' => in_array($value, (array)$currentValue, true),
            ];
        }
        return $options;
    }

    /**
     * Get option groups for an element.
     *
     * @param array             $element      Element configuration
     * @param string|array|null $currentValue Field value(s) for checked/selected status (optional)
     *
     * @return array
     */
    protected function getElementOptionGroups(array $element, string|array|null $currentValue = null): array
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
                // Note: we need a common index for all groups to avoid duplicate values!
                ++$idx;
                $value = "o$idx";
                $internalValue = $option['value'] ?? $option;
                $options[] = [
                    'value' => $value,
                    'internalValue' => $internalValue,
                    'label' => $option['label'] ?? $option,
                    'selected' => in_array($value, (array)$currentValue, true),
                ];
            }
            $groups[] = [
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
    protected function getFormSettingFields(): array
    {
        return [
            'emailFrom',
            'emailSubject',
            'enabled',
            'formActionRoute',
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
    protected function getFormElementSettingFields(): array
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
            'autocomplete',
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
     * Get translated validation message.
     *
     * @param string $messageId Message identifier
     *
     * @return string
     */
    protected function getValidationMessage(string $messageId): string
    {
        return $this->translate(
            $this->messages[$messageId] ?? $messageId
        );
    }

    /**
     * Get form elements.
     *
     * @param array $config Form configuration
     *
     * @return array
     */
    protected function getFormElements(array $config): array
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
     * Get a complete id for an element.
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
     * Get primary form handler.
     *
     * @return HandlerInterface
     */
    public function getPrimaryHandler(): HandlerInterface
    {
        $handlerName = ($this->formConfig['primaryHandler'] ?? 'email');
        return $this->handlerManager->get($handlerName);
    }

    /**
     * Get secondary form handlers.
     *
     * @return HandlerInterface[]
     */
    public function getSecondaryHandlers(): array
    {
        $handlerNames = (array)($this->formConfig['secondaryHandlers'] ?? []);
        return array_map([$this->handlerManager, 'get'], $handlerNames);
    }

    /**
     * Get current form id/name.
     *
     * @return string
     */
    public function getFormId(): string
    {
        return $this->formConfig['id'] ?? '';
    }

    /**
     * Validates prefill data and returns only the prefill values for enabled fields.
     *
     * @param array $prefill Prefill data
     *
     * @return array
     */
    protected function sanitizePrefill(array $prefill): array
    {
        $prefillFields = (array)($this->formConfig['prefillFields'] ?? []);
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
