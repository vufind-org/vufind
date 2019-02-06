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
namespace Finna\Form;

/**
 * Configurable form.
 *
 * @category VuFind
 * @package  Form
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class Form extends \VuFind\Form\Form
{
    /**
     * Email form handler
     *
     * @var string
     */
    const HANDLER_EMAIL = 'email';

    /**
     * Database form handler
     *
     * @var string
     */
    const HANDLER_DATABASE = 'database';

    /**
     * Form id
     *
     * @var string
     */
    protected $formId;

    /**
     * Institution name
     *
     * @var string
     */
    protected $institution;

    /**
     * Institution email
     *
     * @var string
     */
    protected $institutionEmail;

    /**
     * User
     *
     * @var User
     */
    protected $user;

    /**
     * User roles
     *
     * @var array
     */
    protected $userRoles;

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
        $this->formId = $formId;
        parent::setFormId($formId);
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
     * @param User  $user  User
     * @param array $roles User roles
     *
     * @return void
     */
    public function setUser($user, $roles)
    {
        $this->user = $user;
        $this->userRoles = $roles;
    }

    /**
     * Return form recipient.
     *
     * @return array with name, email or null if not configured
     */
    public function getRecipient()
    {
        $recipient = parent::getRecipient();

        if (empty($recipient[1]) && $this->institutionEmail) {
            return ['', $this->institutionEmail];
        }
        return $recipient;
    }

    /**
     * Return form help text.
     *
     * @return string|null
     */
    public function getHelp()
    {
        $help = parent::getHelp();

        // Help text from configuration
        $pre = isset($this->formConfig['help']['pre'])
            ? $this->translate($this->formConfig['help']['pre'])
            : '';

        // 'feedback_instructions_html' translation
        if ($this->formId === 'FeedbackSite') {
            $key = 'feedback_instructions_html';
            $instructions = $this->translate($key);
            // Remove zero width space
            $instructions = str_replace("\xE2\x80\x8C", '', $instructions);
            if (!empty($instructions) && $instructions !== $key) {
                $pre = !empty($pre)
                    ? $instructions . '<br><br>' . $pre
                    : $instructions;
            }
        }

        if ($this->institution) {
            // Receiver info
            $institution = $this->institution;
            $institutionName = $this->translate(
                "institution::$institution", null, $institution
            );

            // Try to handle cases like tritonia-tria
            if ($institutionName === $institution && strpos($institution, '-') > 0
            ) {
                $part = substr($institution, 0, strpos($institution, '-'));
                $institutionName = $this->translate(
                    "institution::$part", null, $institution
                );
            }

            $translationKey = $this->useEmailHandler()
                ? 'feedback_recipient_info_email'
                : 'feedback_recipient_info';

            $recipientInfo = $this->translate(
                $translationKey, ['%%institution%%' => $institutionName]
            );

            if (!empty($pre)) {
                $pre .= '<br><br>';
            }
            $pre .= '<strong>' . $recipientInfo . '</strong>';
        }

        $help['pre'] = $pre;

        return $help;
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
        list($params, $tpl) = parent::formatEmailMessage($requestParams);

        // Append user logged status and permissions
        $loginMethod = $this->user ?
            $this->translate(
                'login_method_' . $this->user->auth_method,
                null,
                $this->user->auth_method
            ) : $this->translate('feedback_user_anonymous');

        $params[$this->translate('feedback_user_login_method')]
            = ['type' => 'text', 'value' => $loginMethod];

        if ($this->user) {
            $params[$this->translate('feedback_user_roles')]
                = ['type' => 'text', 'value' => implode(', ', $this->userRoles)];
        }

        return [$params, $tpl];
    }

    /**
     * Should submitted form data be sent via email?
     *
     * @return boolean
     */
    public function useEmailHandler()
    {
        // Send via email if not configured otherwise locally.
        return !isset($this->formConfig['sendMethod'])
                || $this->formConfig['sendMethod'] !== Form::HANDLER_DATABASE;
    }

    /**
     * Get form element/field names
     *
     * @return array
     */
    public function getFormFields()
    {
        $elements = parent::getFormElements($this->getFormConfig($this->formId));
        $fields = [];
        foreach ($elements as $el) {
            if ($el['type'] === 'submit') {
                continue;
            }
            $fields[] = $el['name'];
        }

        return $fields;
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
        $elements = parent::parseConfig($formId, $config);

        if (!empty($this->formConfig['hideSenderInfo'])) {
            // Remove default sender info fields
            $filtered = [];
            foreach ($elements as $el) {
                if (isset($el['group']) && $el['group'] === '__sender__') {
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

        return $elements;
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
            ['hideSenderInfo', 'sendMethod', 'senderInfoHelp']
        );

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

        if (!$viewConfig) {
            return $config;
        }

        if (isset($config['allowLocalOverride'])
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
}
