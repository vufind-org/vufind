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
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Content\NoticeManager;
use VuFind\Exception\BadRequest;
use VuFind\Exception\NotFound;
use VuFind\I18n\Locale\LocaleSettingsAwareInterface;
use VuFind\I18n\Locale\LocaleSettingsAwareTrait;
use VuFind\ServiceManager\Factory\Autowire;

use function count;
use function in_array;

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
        return $this->getHelper(RedirectHelper::class)->redirectToRoute($this->response, 'admin/notices');
    }

    /**
     * Get notice by the query parameter "notice_id".
     *
     * @return array
     */
    protected function getNoticeByQueryParam(): array
    {
        $noticeId = $this->getQueryParam('notice_id');
        if (!$noticeId) {
            throw new BadRequest('Query parameter "notice_id" is missing.');
        }

        $notice = $this->noticeManager->getByDatabaseId($noticeId);
        if ($notice === null) {
            throw new NotFound('Notice does not exist');
        }
        return $notice;
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
        $noticeConfig = $this->noticeManager->getNoticeConfig();
        $noticeFormConfig = $noticeConfig['adminForm'] ?? [];
        $formData = [];

        $contexts = $noticeFormConfig['contexts'] ?? [];
        $formData['contexts'] = array_keys($contexts);
        if ($notice && $activeContext = $this->getBestMatchingContext($notice, $contexts)) {
            $formData['activeContext'] = $activeContext;
        }

        // check which contexts have date or time related conditions. The respective restrictions
        // will be disabled for those.
        $formData['dateTimeContexts'] = $this->getDateTimeContexts();

        $formData['date_time_types'] = $noticeFormConfig['dateTimeTypes'] ?? [];
        if ($notice) {
            $dateTimeRestrictions = $this->getDateTimeRestrictions($notice);
            if (array_intersect(['start_date_time', 'end_date_time'], array_keys($dateTimeRestrictions))) {
                $formData['activeDateTimeType'] = 'date_time';
            } elseif (array_intersect(['start_date', 'end_date'], array_keys($dateTimeRestrictions))) {
                $formData['activeDateTimeType'] = 'date';
            } elseif (array_intersect(['start_time', 'end_time'], array_keys($dateTimeRestrictions))) {
                $formData['activeDateTimeType'] = 'time';
            }
            foreach ($dateTimeRestrictions as $name => $value) {
                $dateTimeType = str_replace(['start_', 'end_'], '', $name);
                $dateTimeRestrictions[$name] = $this->dateTimeToInput($value, $dateTimeType);
            }
            $formData = array_merge($formData, $dateTimeRestrictions);
        }

        $formData['contentTypes'] = $noticeFormConfig['contentTypes'] ?? [];
        if ($activeContentType = $notice['contentType'] ?? null) {
            $formData['activeContentType'] = $activeContentType;
        }

        foreach ($this->getFormLanguages() as $language) {
            $formData['translations'][$language] = $notice['translations'][$language] ?? null;
        }

        $styles = $noticeConfig['styles'] ?? [];
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
     * Determine which configured context matches the notice the best.
     *
     * @param array $notice   Notice data
     * @param array $contexts Contexts
     *
     * @return ?string
     */
    protected function getBestMatchingContext(array $notice, array $contexts): ?string
    {
        $context = null;
        $maxMatch = -1;
        $maxMatchAdditionalConfigs = 0;
        $noticeConfigMatchKeys = $this->createConfigMatchKeys($notice);
        foreach ($contexts as $name => $currentContext) {
            $contextConfigMatchKeys = $this->createConfigMatchKeys($currentContext ?? []);

            $matchingConfigs = count(array_intersect(
                $contextConfigMatchKeys,
                $noticeConfigMatchKeys,
            ));
            $additionalConfigs = count(array_diff(
                $contextConfigMatchKeys,
                $noticeConfigMatchKeys
            ));

            // select preset that has most matching configs and as few additional configs as possible as decider
            if (
                $maxMatch < $matchingConfigs
                || ($maxMatch === $matchingConfigs && $additionalConfigs < $maxMatchAdditionalConfigs)
            ) {
                $context = $name;
                $maxMatch = $matchingConfigs;
                $maxMatchAdditionalConfigs = $additionalConfigs;
            }
        }
        return $context;
    }

    /**
     * Create keys for context on which it can be compared to another one.
     *
     * @param array $context Context
     *
     * @return string[]
     */
    protected function createConfigMatchKeys(array $context): array
    {
        $configMatchKeys = array_map(
            function ($condition) {
                $values = $condition['values'] ?? [];
                sort($values);
                return 'cond_' . $condition['type'] . '_' . $condition['comparator'] . '_' . implode(',', $values);
            },
            $context['conditions'] ?? []
        );
        if ($position = $context['position'] ?? null) {
            $configMatchKeys[] = 'pos_' . $position;
        }
        return $configMatchKeys;
    }

    /**
     * Get contexts with date or time related conditions.
     *
     * @return array
     */
    protected function getDateTimeContexts(): array
    {
        $dateTimeContexts = [];
        foreach ($this->noticeManager->getNoticeConfig()['adminForm']['contexts'] ?? [] as $name => $context) {
            foreach ($context['conditions'] ?? [] as $condition) {
                if (str_contains($condition['type'], 'date') || str_contains($condition['type'], 'time')) {
                    $dateTimeContexts[] = $name;
                    break;
                }
            }
        }
        return $dateTimeContexts;
    }

    /**
     * Get date and time related restrictions.
     *
     * @param array $notice Notice data
     *
     * @return array
     */
    protected function getDateTimeRestrictions(array $notice): array
    {
        $restrictions = [];
        $dateTimeTypes = $this->noticeManager->getNoticeConfig()['adminForm']['dateTimeTypes'] ?? [];
        foreach ($notice['conditions'] as $condition) {
            if (
                in_array($condition['type'], $dateTimeTypes)
                && $date = $condition['checkedValues'][0] ?? null
            ) {
                switch ($condition['comparator']) {
                    case '>=':
                        $restrictions['start_' . $condition['type']] = $date;
                        break;
                    case '<=':
                        $restrictions['end_' . $condition['type']] = $date;
                }
            }
        }
        return $restrictions;
    }

    /**
     * Map form data to notice array format.
     *
     * @return array
     */
    protected function formDataToNotice(): array
    {
        $notice = $this->noticeManager->getDefaults();
        $noticeFormConfig = $this->noticeManager->getNoticeConfig()['adminForm'] ?? [];
        $useDateTimeInput = true;
        if ($context = $this->getPostParam('context_fieldset')['context'] ?? null) {
            if (in_array($context, $this->getDateTimeContexts())) {
                $useDateTimeInput = false;
            }
            $contextConfig = $noticeFormConfig['contexts'][$context] ?? [];
            $notice['position'] = $contextConfig['position'] ?? 'default';
            $notice['conditions'] = $contextConfig['conditions'] ?? [];
        }

        $dateTimeType = $this->getPostParam('date_time_type_fieldset')['date_time_type']
            ?? $noticeFormConfig['dateTimeTypes'][0]
            ?? null;
        if ($useDateTimeInput && $dateTimeType) {
            if (
                $start = $this->inputToDateTime(
                    $this->getPostParam('start_' . $dateTimeType),
                    $dateTimeType
                )
            ) {
                $notice['conditions'][] =  [
                    'type' => $dateTimeType,
                    'comparator' => '>=',
                    'checkedValues' => [$start],
                ];
            }
            if (
                $end = $this->inputToDateTime(
                    $this->getPostParam('end_' . $dateTimeType),
                    $dateTimeType
                )
            ) {
                $notice['conditions'][] =  [
                    'type' => $dateTimeType,
                    'comparator' => '<=',
                    'checkedValues' => [$end],
                ];
            }
        }

        if ($contentType = $this->getPostParam('content_type_fieldset')['content_type'] ?? null) {
            $notice['contentType'] = $contentType;
        }

        $notice['translations'] = $this->getPostParam('translations');

        if ($style = $this->getPostParam('style_fieldset')['style'] ?? null) {
            $notice['style'] = $style;
        }

        return $notice;
    }

    /**
     * Parse date and time input to standard format.
     *
     * @param ?string $input        Input
     * @param string  $dateTimeType Date and time type
     *
     * @return ?string
     */
    protected function inputToDateTime(?string $input, string $dateTimeType): ?string
    {
        if (!$input) {
            return null;
        }
        switch ($dateTimeType) {
            case 'date_time':
                $dateTime = \DateTime::createFromFormat('Y-m-d\TH:i', $input);
                return $dateTime->format('Y-m-d H:i:s');
            case 'time':
                if (substr_count(':', $input) === 1) {
                    $input .= ':00';
                }
                return $input;
        }
        return $input;
    }

    /**
     * Parse date and time to input format.
     *
     * @param ?string $value        Value
     * @param string  $dateTimeType Date and time type
     *
     * @return ?string
     */
    protected function dateTimeToInput(?string $value, string $dateTimeType): ?string
    {
        if (!$value) {
            return null;
        }
        switch ($dateTimeType) {
            case 'date_time':
                $dateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
                return $dateTime->format('Y-m-d\TH:i');
            case 'time':
                return preg_replace('/^(\d+:\d+).*/', '$1', $value);
        }
        return $value;
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
