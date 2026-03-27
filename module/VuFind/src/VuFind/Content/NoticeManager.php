<?php

/**
 * Notice Manager.
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
 * @package  Content
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Content;

use VuFind\Condition\Manager as ConditionManager;
use VuFind\I18n\Locale\LocaleSettingsAwareInterface;
use VuFind\I18n\Locale\LocaleSettingsAwareTrait;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Notice Manager.
 *
 * @category VuFind
 * @package  Content
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class NoticeManager implements LocaleSettingsAwareInterface
{
    use LocaleSettingsAwareTrait;

    /**
     * Notices cache.
     *
     * @var ?array
     */
    protected ?array $notices = null;

    /**
     * Constructor.
     *
     * @param array            $config           Config
     * @param ConditionManager $conditionManager Condition manager
     */
    public function __construct(
        #[Autowire(config: 'Notices', configType: 'yaml')]
        protected array $config,
        #[Autowire(service: ConditionManager::class)]
        protected ConditionManager $conditionManager,
    ) {
    }

    /**
     * Get notice config.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get all active notices.
     *
     * @param ?string $position Optionally filter by this position
     *
     * @return array
     */
    public function getActiveList(?string $position = null): array
    {
        $activeNotices = [];
        foreach ($this->getNotices() as $notice) {
            if (
                ($position === null || $position === ($notice['position'] ?? 'default'))
                && $this->conditionManager->evaluateConditions($notice['conditions'] ?? [])
            ) {
                $activeNotices[] = $notice;
            }
        }
        return $activeNotices;
    }

    /**
     * Get notices.
     *
     * @return array
     */
    protected function getNotices(): array
    {
        if ($this->notices === null) {
            $this->loadNotices();
        }
        return $this->notices;
    }

    /**
     * Load notices.
     *
     * @return void
     */
    protected function loadNotices(): void
    {
        $this->notices = [];
        foreach ($this->config['notices'] ?? [] as $notice) {
            if (!isset($notice['content'])) {
                $content = $this->getActiveTranslation($notice['translations'] ?? [], true);
                if ($content !== null) {
                    $notice['content'] = $content;
                }
            }
            $this->notices[] = $notice;
        }
    }
}
