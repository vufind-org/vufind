<?php

/**
 * Abstract notices action.
 *
 * PHP version 8
 *
 * Copyright (C) effective WEBWORK GmbH 2023.
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * @package  Action
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindAdmin\Action\Notices;

use Psr\Http\Message\ResponseInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\Content\NoticeManager;
use VuFind\I18n\Locale\LocaleSettingsAwareInterface;
use VuFind\I18n\Locale\LocaleSettingsAwareTrait;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Abstract notices action.
 *
 * @category VuFind
 * @package  Action
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractNoticeAction extends AbstractTemplateRenderingAction implements LocaleSettingsAwareInterface
{
    use LocaleSettingsAwareTrait;

    /**
     * Constructor.
     *
     * @param NoticeManager $noticeManager Notice manager
     */
    #[Autowire]
    public function __construct(
        protected NoticeManager $noticeManager,
    ) {
        parent::__construct();
    }

    /**
     * Redirect to notices admin overview.
     *
     * @return ResponseInterface
     */
    protected function returnToNoticesAdminHome(): ResponseInterface
    {
        return $this->getRedirectResponse($this->response, $this->getUrlFromRoute('admin/notices'));
    }

    /**
     * Get form data optionally based on existing notice.
     *
     * @param ?array $notice Notice data (optional)
     *
     * @return array
     */
    protected function getFormData(?array $notice = null): array
    {
        $formData = [];
        foreach ($this->getFormLanguages() as $language) {
            $formData['translations'][$language] = $notice['translations'][$language] ?? null;
        }
        $styles = $this->noticeManager->getNoticeConfig()['styles'] ?? [];
        $activeStyle = $notice['style'] ?? array_keys($styles)[0] ?? null;
        foreach ($styles as $style => $attributes) {
            if ($activeStyle === $style) {
                $attributes['active'] = true;
            }
            $formData['styles'][$style] = $attributes;
        }
        return $formData;
    }

    /**
     * Map form data to notice array format.
     *
     * @return array
     */
    protected function formDataToNotice(): array
    {
        $notice = $this->noticeManager->getDefaults();

        $notice['translations'] = $this->getPostParam('translations');

        if ($style = $this->getPostParam('style_fieldset')['style'] ?? null) {
            $notice['style'] = $style;
        }

        return $notice;
    }

    /**
     * Get languages enabled for the form.
     *
     * @return array
     */
    public function getFormLanguages(): array
    {
        $defaultLanguages = $this->localeSettings->getEnabledLocales();
        $config = $this->noticeManager->getNoticeConfig()['adminForm'] ?? [];
        if (isset($config['languages'])) {
            return array_filter(
                $config['languages'],
                fn ($language) => isset($defaultLanguages[$language])
            );
        }
        unset($defaultLanguages['debug']);
        // move default language into first position
        $defaultLanguage = $this->localeSettings->getDefaultLocale();
        unset($defaultLanguages[$defaultLanguage]);
        $languages = array_keys($defaultLanguages);
        array_unshift($languages, $defaultLanguage);
        return $languages;
    }
}
