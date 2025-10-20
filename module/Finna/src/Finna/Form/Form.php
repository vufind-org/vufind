<?php

/**
 * Configurable form.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2018-2022.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace Finna\Form;

use Exception;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\RecordDriver\DefaultRecord;

use function in_array;

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
class Form extends \VuFind\Form\Form
{
    /**
     * Site feedback form id.
     *
     * @var string
     */
    public const FEEDBACK_FORM = 'FeedbackSite';

    /**
     * Record feedback form id.
     *
     * @var string
     */
    public const RECORD_FEEDBACK_FORM = 'FeedbackRecord';

    /**
     *  Archive request form id.
     *
     * @var        string
     * @deprecated Logic moved to ReservationList::placeSingleOrder
     */
    public const ARCHIVE_MATERIAL_REQUEST = 'ArchiveRequest';

    /**
     * Handlers that are considered safe for transmitting information about the user
     *
     * @var array
     */
    protected $secureHandlers = ['api', 'database'];

    /**
     * Institution name
     *
     * @var string
     */
    protected $institution = '';

    /**
     * Institution email
     *
     * @var string
     */
    protected $institutionEmail = '';

    /**
     * User
     *
     * @var ?UserEntityInterface
     */
    protected $user = null;

    /**
     * ILS Patron
     *
     * @var array
     */
    protected $ilsPatron = null;

    /**
     * User roles
     *
     * @var array
     */
    protected $userRoles = [];

    /**
     * User library card barcode.
     *
     * @var ?string
     */
    protected $userCatUsername = null;

    /**
     * User patron id in library.
     *
     * @var ?string
     */
    protected $userCatId = null;

    /**
     * Record request forms that are allowed to send user's library card barcode
     * along with the form data.
     *
     * @var array Form ids
     */
    protected $recordRequestFormsWithBarcode = [];

    /**
     * Data source configuation
     *
     * @var array
     */
    protected $dataSourceConfig = null;

    /**
     * Record driver
     *
     * @var DefaultRecord
     */
    protected $record = null;

    /**
     * Record loader
     *
     * @var \VuFind\Record\Loader
     */
    protected $recordLoader = null;

    /**
     * Set form id
     *
     * @param string $formId  Form id
     * @param array  $params  Additional form parameters.
     * @param array  $prefill Prefill form with these values.
     *
     * @return void
     * @throws Exception
     */
    public function setFormId($formId, $params = [], $prefill = [])
    {
        parent::setFormId($formId, $params, $prefill);

        if ($this->ilsPatron) {
            if ($this->reportPatronBarcode()) {
                $this->userCatUsername = $this->ilsPatron['__local_cat_username'] ?? $this->ilsPatron['cat_username'];
            }
            if ($this->reportPatronId()) {
                $this->userCatId = $this->ilsPatron['__local_id'] ?? $this->ilsPatron['id'];
            }
        }

        $this->setName($formId);
    }

    /**
     * Set data to validate and/or populate elements
     *
     * Typically, also passes data on to the composed input filter.
     *
     * @param iterable $data Data
     *
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setData(iterable $data)
    {
        if ($data instanceof \Traversable) {
            $data = \Laminas\Stdlib\ArrayUtils::iteratorToArray($data);
        }
        if (!empty($data['record_id'])) {
            if (!$this->recordLoader) {
                throw new \Exception('Record loader not set');
            }
            // Set record driver used by FeedbackRecord form:
            [$source, $recId] = explode('|', $data['record_id'], 2);
            $driver = $this->recordLoader->load($recId, $source);
            $this->setRecord($driver);
        }
        return parent::setData($data);
    }

    /**
     * Sets name and email field values from preferred source
     *
     * @return static
     */
    public function setContactInformation(): static
    {
        if ($this->preferPatronInformation()) {
            $this->setData(
                [
                    'name' => $this->ilsPatron['firstname'] . ' ' . $this->ilsPatron['lastname'],
                    'email' => $this->ilsPatron['email'],
                ]
            );
        } elseif ($this->user) {
            $this->setData(
                [
                    'name' => $this->user->getFirstname() . ' ' . $this->user->getLastname(),
                    'email' => $this->user->getEmail(),
                ]
            );
        }
        return $this;
    }

    /**
     * Set institution
     *
     * @param string $institution Institution
     *
     * @return void
     */
    public function setInstitution($institution)
    {
        $this->institution = $institution;
    }

    /**
     * Set institution email
     *
     * @param string $email Email
     *
     * @return void
     */
    public function setInstitutionEmail($email)
    {
        $this->institutionEmail = $email;
    }

    /**
     * Set user
     *
     * @param UserEntityInterface $user      User
     * @param array               $roles     User roles
     * @param ?array              $ilsPatron ILS patron account
     *
     * @return void
     */
    public function setUser(UserEntityInterface $user, array $roles, ?array $ilsPatron)
    {
        $this->user = $user;
        $this->userRoles = $roles;
        $this->ilsPatron = $ilsPatron;
    }

    /**
     * Get record driver
     *
     * @return ?DefaultRecord
     */
    public function getRecord(): ?DefaultRecord
    {
        return $this->record;
    }

    /**
     * Set record driver
     *
     * @param DefaultRecord $record Record
     *
     * @return void
     */
    protected function setRecord(DefaultRecord $record): void
    {
        $this->record = $record;
    }

    /**
     * Set form ids that are allowed to send user's library card barcode
     * along with the form data.
     *
     * @param array $formIds Form ids
     *
     * @return void
     */
    public function setRecordRequestFormsWithBarcode(array $formIds): void
    {
        $this->recordRequestFormsWithBarcode = $formIds;
    }

    /**
     * Set data source configuration
     *
     * @param array $config Data source configuration
     *
     * @return void
     */
    public function setDataSourceConfig(array $config): void
    {
        $this->dataSourceConfig = $config;
    }

    /**
     * Set record loader
     *
     * @param \VuFind\Record\Loader $loader Record loader
     *
     * @return void
     */
    public function setRecordLoader(\VuFind\Record\Loader $loader): void
    {
        $this->recordLoader = $loader;
    }

    /**
     * Check if the form should report patron's barcode
     *
     * @return bool
     */
    public function reportPatronBarcode()
    {
        return (bool)($this->formConfig['includeBarcode'] ?? false);
    }

    /**
     * Check if the form should report patron's id
     *
     * @return bool
     */
    public function reportPatronId(): bool
    {
        return (bool)($this->formConfig['includePatronId'] ?? false);
    }

    /**
     * Should the form fill user data from patron?
     *
     * @return bool
     */
    public function preferPatronInformation(): bool
    {
        return $this->ilsPatron
            && (
                $this->reportPatronBarcode()
                || $this->reportPatronId()
                || (bool)($this->formConfig['preferPatronInformation'] ?? false)
            );
    }

    /**
     * Return form recipient.
     *
     * @param array $postParams Posted form data
     *
     * @return array with name, email or null if not configured
     */
    public function getRecipient($postParams = null)
    {
        // Get recipient email address for feedback form from data source
        // configuration:
        if ($this->getFormId() === 'FeedbackRecord') {
            if (!$this->record) {
                throw new \Exception('Record not set for FeedbackRecord form');
            }
            $dataSource = $this->record->tryMethod('getDataSource');
            $inst = $this->dataSourceConfig[$dataSource] ?? null;
            if (!($recipientEmail = $inst['feedbackEmail'] ?? null)) {
                throw new \Exception(
                    'Error sending record feedback: Recipient email for'
                    . " $dataSource not set in datasources.ini"
                );
            }
            return [
                [
                    'name' => '',
                    'email' => $recipientEmail,
                ],
            ];
        }

        if ($recipient = $this->getRecipientFromFormData($postParams)) {
            return [$recipient];
        }

        $recipients = parent::getRecipient();
        foreach ($recipients as &$recipient) {
            if (empty($recipient['email']) && $this->institutionEmail) {
                $recipient['email'] = $this->institutionEmail;
            }
        }
        // Unset reference to be safe:
        unset($recipient);

        return $recipients;
    }

    /**
     * Resolve email recipient based on posted form data.
     *
     * @param array $postParams Posted form data
     *
     * @return array with 'name' and 'email' keys or null if no form element
     * is configured to carry recipient address.
     */
    protected function getRecipientFromFormData($postParams)
    {
        if (!$recipientField = $this->getRecipientField($this->formElementConfig)) {
            return null;
        }

        $params = $this->mapRequestParamsToFieldValues($postParams);

        foreach ($params as $param) {
            if ($recipientField === $param['name']) {
                return [
                    'email' => $param['value'],
                    'name' => $this->translate($param['valueLabel']),
                ];
            }
        }

        return null;
    }

    /**
     * Return form help texts.
     *
     * @return array|null
     */
    public function getHelp()
    {
        $help = parent::getHelp();

        if (!$this->viewHelperManager) {
            throw new \Exception('ViewHelperManager not defined');
        }

        $escapeHtml = $this->viewHelperManager->get('escapeHtml');
        $transEsc = $this->viewHelperManager->get('transEsc');
        $translationEmpty = $this->viewHelperManager->get('translationEmpty');
        $organisationDisplayName
            = $this->viewHelperManager->get('organisationDisplayName');

        $preParagraphs = [];
        $postParagraphs = [];
        $datasource = null !== $this->record ? $this->record->tryMethod('getDataSource') : '';

        // 'feedback_instructions_html' translation
        if ($this->getFormId() === self::FEEDBACK_FORM) {
            $key = 'feedback_instructions_html';
            $instructions = $this->translate($key);
            if ($instructions !== $key && !$translationEmpty($instructions)) {
                $preParagraphs[] = $instructions;
            }
        }
        // 'archive_request_{$datasource}_reserve_material_pre_html' translation
        if ($this->getFormId() === self::ARCHIVE_MATERIAL_REQUEST) {
            $text = $this->translateCombinedString('archive_request', $datasource, 'reserve_material_pre_html');
            if ($text) {
                $preParagraphs[] = $text;
            }
        }

        // Help texts from configuration
        foreach ((array)($this->formConfig['help']['pre'] ?? []) as $translation) {
            if (!$translationEmpty($translation)) {
                $preParagraphs[] = $this->translate($translation);
            }
        }
        foreach ((array)($this->formConfig['help']['post'] ?? []) as $translation) {
            if (!$translationEmpty($translation)) {
                $postParagraphs[] = $this->translate($translation);
            }
        }

        if ($this->getFormId() === self::RECORD_FEEDBACK_FORM && null !== $this->record) {
            // Append receiver info after general record feedback instructions
            // (translation key for this is defined in FeedbackForms.yaml)
            if (!$translationEmpty('feedback_recipient_info_record')) {
                $preParagraphs[] = $transEsc(
                    'feedback_recipient_info_record',
                    [
                        '%%institution%%'
                            => $organisationDisplayName($this->record, true),
                    ]
                );
            }
            $datasourceKey = 'feedback_recipient_info_record_'
                . $this->record->tryMethod('getDataSource', [], '') . '_html';
            if (!$translationEmpty($datasourceKey)) {
                $preParagraphs[] = '<span class="datasource-info">'
                    . $this->translate($datasourceKey) . '</span>';
            }
        }
        if (
            $this->getFormId() === self::ARCHIVE_MATERIAL_REQUEST
            && null !== $this->record
        ) {
            $text = $this->translateCombinedString('archive_request', $datasource, 'info');
            if ($text) {
                $preParagraphs[] = $escapeHtml($text);
            }
        } elseif (
            !($this->formConfig['hideRecipientInfo'] ?? false)
            && $this->institution
        ) {
            // Receiver info
            $institution = $this->institution;
            $institutionName = $this->translate(
                "institution::$institution",
                null,
                $institution
            );

            // Try to handle cases like tritonia-tria
            if (
                $institutionName === $institution && strpos($institution, '-') > 0
            ) {
                $part = substr($institution, 0, strpos($institution, '-'));
                $institutionName = $this->translate(
                    "institution::$part",
                    null,
                    $institution
                );
            }

            $isEmail = $this->getPrimaryHandler()
                instanceof \VuFind\Form\Handler\Email;
            $translationKey = $isEmail
                ? 'feedback_recipient_info_email'
                : 'feedback_recipient_info';

            $recipientInfo = $this->translate(
                $translationKey,
                ['%%institution%%' => $institutionName]
            );

            $postParagraphs[] = '<strong>' . $recipientInfo . '</strong>';
        }

        // Append record title
        if (
            null !== $this->record
            && ($this->getFormId() === self::RECORD_FEEDBACK_FORM
            || $this->getFormId() === self::ARCHIVE_MATERIAL_REQUEST
            || $this->isRecordRequestFormWithBarcode())
        ) {
            $preParagraphs[] = '<strong>'
                . $transEsc('feedback_material') . '</strong>:<br>'
                . $escapeHtml($this->record->getTitle());
        }

        if (
            null !== $this->record
            && $this->getFormId() === self::ARCHIVE_MATERIAL_REQUEST
        ) {
            $identifier = $this->record->tryMethod('getIdentifier');
            if ($identifier) {
                $preParagraphs[] = '<strong>'
                    . $transEsc('Inventory ID') . '</strong>:<br>'
                    . $escapeHtml($identifier[0]);
            }
        }

        if ($this->userCatUsername) {
            $preParagraphs[] = $this->translate(
                'feedback_library_card_barcode_html',
                ['%%barcode%%' => $escapeHtml($this->userCatUsername)]
            );
        }
        if ($this->userCatId) {
            $postParagraphs[] = $this->translate(
                'feedback_library_patron_id_html',
                ['%%id%%' => $escapeHtml($this->userCatId)]
            );
        }

        if (
            null !== $this->record
            && $this->getFormId() === self::ARCHIVE_MATERIAL_REQUEST
        ) {
            $text = $this->translateCombinedString('archive_request', $datasource, 'material_arrival_info_html');
            if ($text) {
                $postParagraphs[] = '<div class="alert alert-info">' . $text . '</div>';
            }
        }

        $pre = implode('</div><div>', $preParagraphs);
        $help['pre'] = $pre ? "<div>$pre</div>" : '';
        $post = implode('</div><div>', $postParagraphs);
        $help['post'] = $post ? "<div>$post</div>" : '';

        return $help;
    }

    /**
     * Combine a translation key from two or three strings
     *
     * @param string $prefix The prefix/first part of translation key
     * @param string $middle The second part of translation key
     * @param string $suffix The suffix for translation key (optional)
     *
     * @return string
     */
    public function translateCombinedString(string $prefix, string $middle, string $suffix = '')
    {
        $translate = $prefix;
        $translationEmpty = $this->viewHelperManager->get('translationEmpty');
        if ('' !== $middle) {
            $translate .= '_' . $middle;
        }
        if ('' !== $suffix) {
            $translate .= '_' . $suffix;
        }
        return !$translationEmpty($translate) ? $this->translate($translate) : '';
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
        $params = parent::mapRequestParamsToFieldValues($requestParams);

        $params = array_filter(
            $params,
            function ($param) {
                return !empty($param['label']) || !empty($param['value']);
            }
        );
        reset($params);

        if ($this->userCatUsername) {
            // Append library card barcode
            $field = [
                'type' => 'text',
                'name' => 'userCatUsername',
                'label' => $this->translate('Library Catalog Username'),
                'value' => $this->userCatUsername,
            ];
            if ($idx = array_search('email', array_column($params, 'type'))) {
                array_splice($params, $idx + 1, 0, [$field]);
            } else {
                $params[] = $field;
            }
        }

        if ($this->userCatId) {
            // Append patron's id in library
            $field = [
                'type' => 'text',
                'name' => 'userCatId',
                'label' => $this->translate('Unique patron identifier'),
                'value' => $this->userCatId,
            ];
            if ($idx = array_search('email', array_column($params, 'type'))) {
                array_splice($params, $idx + 1, 0, [$field]);
            } else {
                $params[] = $field;
            }
        }
        if (!$this->isRecordRequestFormWithBarcode()) {
            // Append user logged status and permissions
            $loginMethod = $this->user ?
                $this->translate(
                    'login_method_' . $this->user->getAuthMethod(),
                    null,
                    $this->user->getAuthMethod(),
                ) : $this->translate('feedback_user_anonymous');

            $label = $this->translate('feedback_user_login_method');
            $params[] = [
                'name' => 'userLoginMethod',
                'type' => 'text',
                'label' => $label,
                'value' => $loginMethod,
            ];

            if ($this->user) {
                $label = $this->translate('feedback_user_roles');
                $params[] = [
                    'name' => 'userRoles',
                    'type' => 'text',
                    'label' => $label,
                    'value' => implode(', ', $this->userRoles),
                ];
            }
        }
        return $params;
    }

    /**
     * Return API settings
     *
     * @return array
     */
    public function getApiSettings(): array
    {
        return $this->formConfig['apiSettings'] ?? [];
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
        if ($type === 'hidden') {
            return '\Laminas\Form\Element\Hidden';
        }

        return parent::getFormElementClass($type);
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
        $elements = parent::getFormElements($config);

        $includeRecordData = in_array(
            $this->getFormId(),
            [
                self::RECORD_FEEDBACK_FORM,
                self::ARCHIVE_MATERIAL_REQUEST,
            ]
        ) || $this->isRecordRequestFormWithBarcode();

        if ($includeRecordData) {
            // Add hidden fields for record data
            foreach (['record_id', 'record', 'record_info'] as $key) {
                $elements[$key] = ['type' => 'hidden', 'name' => $key, 'value' => null];
            }
        }

        return $elements;
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
        $elements = parent::parseConfig($formId, $config, $params, $prefill);

        if (!empty($this->formConfig['hideSenderInfo'])) {
            // Remove default sender info fields
            $filtered = [];
            $configFieldNames = array_column($config['fields'] ?? [], 'name');
            foreach ($elements as $el) {
                if (
                    isset($el['group']) && $el['group'] === '__sender__'
                    && !in_array($el['name'] ?? '', $configFieldNames)
                ) {
                    continue;
                }
                $filtered[] = $el;
            }
            $elements = $filtered;
        } else {
            // Add help text for default sender name & email fields
            if (!empty($this->formConfig['senderInfoHelp'])) {
                $help = $this->formConfig['senderInfoHelp'];
                foreach ($elements as &$el) {
                    if (isset($el['group']) && $el['group'] === '__sender__') {
                        $el['help'] = $help;
                        break;
                    }
                }
            }
        }
        if ($formId === self::ARCHIVE_MATERIAL_REQUEST) {
            foreach ($elements as &$value) {
                if ($value['name'] === 'submit') {
                    $value['label'] = 'request_submit_text';
                }
            }
            unset($value);
        }
        return $elements;
    }

    /**
     * Return name of form element that is used as email recipient.
     *
     * @param array $config Form elements configuration.
     *
     * @return string|null
     */
    protected function getRecipientField($config)
    {
        foreach ($config as $el) {
            // Allow only select elements
            if ($el['recipient'] ?? false && $el['type'] === 'select') {
                return $el['name'];
            }
        }
        return null;
    }

    /**
     * Return a list of field names to read from settings file.
     *
     * @return array
     */
    protected function getFormSettingFields()
    {
        $fields = parent::getFormSettingFields();

        $fields = array_merge(
            $fields,
            [
                'apiSettings',
                'hideRecipientInfo',
                'hideSenderInfo',
                'includeBarcode',
                'includePatronId',
                'preferPatronInformation',
                'readonly',
                'rows',
                'senderInfoHelp',
                'sendMethod',
            ]
        );

        return $fields;
    }

    /**
     * Return a list of field names to read from form element settings.
     *
     * @return array
     */
    protected function getFormElementSettingFields()
    {
        $fields = parent::getFormElementSettingFields();
        $fields[] = 'recipient';

        return $fields;
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
        $viewConfig = $finnaConfig = null;

        if (!($this->yamlReader instanceof \Finna\Config\YamlReader)) {
            throw new \Exception('Invalid YamlReader');
        }

        $finnaConfig = $this->yamlReader->getFinna($confName, 'config/finna');
        $viewConfig = $this->yamlReader->getFinna($confName, 'config/vufind');

        if (!$formId) {
            $formId = $viewConfig['default'] ?? $finnaConfig['default'] ?? null;
            if (!$formId) {
                return null;
            }
        }

        $config = $finnaConfig['forms'][$formId] ?? [];
        $viewConfig = $viewConfig['forms'][$formId] ?? null;

        // Backward-compatibility with the sendMethod setting:
        if (empty($config['primaryHandler']) && !empty($config['sendMethod'])) {
            $config['primaryHandler'] = $config['sendMethod'];
        }
        if (
            empty($viewConfig['primaryHandler'])
            && !empty($viewConfig['sendMethod'])
        ) {
            $viewConfig['primaryHandler'] = $viewConfig['sendMethod'];
        }

        if (!$viewConfig) {
            return $config;
        }

        if (
            isset($config['allowLocalOverride'])
            && $config['allowLocalOverride'] === false
        ) {
            return $config;
        }

        // Merge local configuration to Finna default
        // - 'fields' section as such
        // - everything else key by key

        $data = array_replace_recursive($config, $viewConfig);
        $data['fields'] = $viewConfig['fields'] ?? $config['fields'];

        return $data;
    }

    /**
     * Is this form allowed to send user's library card barcode
     * along with the form data?
     *
     * @return boolean
     */
    protected function isRecordRequestFormWithBarcode(): bool
    {
        return in_array($this->getFormId(), $this->recordRequestFormsWithBarcode);
    }
}
