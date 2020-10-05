<?php
/**
 * Configurable R2 form.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2018-2020.
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
 * Configurable R2 form.
 *
 * @category VuFind
 * @package  Form
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class R2Form extends Form
{
    /**
     * R2 registration form id
     *
     * @var string
     */
    const R2_REGISTER_FORM = 'R2Register';

    /**
     * R2 returning user registration form id
     *
     * @var string
     */
    const R2_REGISTER_RETURNING_USER_FORM = 'R2RegisterReturningUser';

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
        $confName = 'R2FeedbackForms.yaml';
        $localConfig = $this->yamlReader->get($confName, true, true);
        return $localConfig['forms'][$formId] ?? null;
    }

    /**
     * Check if the given form is a R2 registration form.
     *
     * @param string $formId Form id
     *
     * @return bool
     */
    public static function isR2RegisterForm($formId)
    {
        return in_array(
            $formId,
            [self::R2_REGISTER_FORM, self::R2_REGISTER_RETURNING_USER_FORM]
        );
    }

    /**
     * Get display string.
     *
     * @param string $translationKey Translation key
     * @param bool   $escape         Whether to escape the output.
     * Default behaviour is to escape when the translation key does
     * not end with '_html'.
     *
     * @return string|null
     */
    public function getDisplayString($translationKey, $escape = null)
    {
        // R2 registration form help texts
        switch ($translationKey) {
        case 'R2_register_form_usage_link_html':
        case 'R2_register_form_help_returninguser_pre_html':
            $url = $this->viewHelperManager->get('url')
                ->__invoke('content-page', ['page' => 'tutkijasali']);
            return $this->translate($translationKey, ['%%url%%' => $url]);

        case 'R2_register_form_help_post_html':
            $url = $this->viewHelperManager->get('url')
                ->__invoke('content-page', ['page' => 'privacy']);
            return $this->translate($translationKey, ['%%url%%' => $url]);

        case 'R2_register_form_usage_help_html':
            $help = $this->translate('R2_register_form_usage_help_tooltip_html');
            return $this->translate($translationKey, ['%%title%%' => $help]);
        }

        return parent::getDisplayString($translationKey, $escape);
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
        $elements = parent::parseConfig($formId, $config, $params);

        // Set name fields to readonly. This will still post the fields
        // (in contrast to disabled)
        foreach (['firstname', 'lastname'] as $field) {
            foreach ($elements as &$el) {
                if ($el['name'] !== $field) {
                    continue;
                }
                $el['settings']['readonly'] = 'readonly';
            }
        }

        return $elements;
    }
}
