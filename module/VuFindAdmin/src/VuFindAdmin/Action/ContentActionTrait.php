<?php

/**
 * VuFind Action Feature Trait - helper methods for content management actions.
 *
 * PHP version 8
 *
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindAdmin\Action;

use function count;

/**
 * VuFind Action Feature Trait - helper methods for content management actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait ContentActionTrait
{
    /**
     * Determine which configured context matches the content object the best.
     *
     * @param array $contentObject Content object data
     * @param array $contexts      Contexts
     *
     * @return ?string
     */
    protected function getBestMatchingContext(array $contentObject, array $contexts): ?string
    {
        $context = null;
        $maxMatch = -1;
        $maxMatchAdditionalConfigs = 0;
        $contentObjectConfigMatchKeys = $this->createConfigMatchKeys($contentObject);
        foreach ($contexts as $name => $currentContext) {
            $contextConfigMatchKeys = $this->createConfigMatchKeys($currentContext ?? []);

            $matchingConfigs = count(array_intersect(
                $contextConfigMatchKeys,
                $contentObjectConfigMatchKeys,
            ));
            $additionalConfigs = count(array_diff(
                $contextConfigMatchKeys,
                $contentObjectConfigMatchKeys
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
     * Get languages enabled for the form.
     *
     * @param array $formConfig Form config
     *
     * @return array
     */
    protected function getFormLanguages(array $formConfig): array
    {
        $defaultLanguages = $this->localeSettings->getEnabledLocales();
        if (isset($formConfig['languages'])) {
            return array_filter(
                $formConfig['languages'],
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
