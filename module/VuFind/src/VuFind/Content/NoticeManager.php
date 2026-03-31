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
use VuFind\Db\Entity\NoticeEntityInterface;
use VuFind\Db\Service\NoticeServiceInterface;
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
     * @param array                  $noticeConfig     Notice config
     * @param ConditionManager       $conditionManager Condition manager
     * @param NoticeServiceInterface $noticeService    Notice service
     */
    public function __construct(
        #[Autowire(config: 'Notices', configType: 'yaml')]
        protected array $noticeConfig,
        #[Autowire(service: ConditionManager::class)]
        protected ConditionManager $conditionManager,
        #[Autowire(container: \VuFind\Db\Service\PluginManager::class)]
        protected NoticeServiceInterface $noticeService
    ) {
    }

    /**
     * Get notice config.
     *
     * @return array
     */
    public function getNoticeConfig(): array
    {
        return $this->noticeConfig;
    }

    /**
     * Get notice defaults.
     *
     * @return array
     */
    public function getDefaults(): array
    {
        $style = array_keys($this->noticeConfig['styles'] ?? [])[0] ?? null;
        return compact('style');
    }

    /**
     * Get notice list to be changed by admins.
     *
     * @return array
     */
    public function getAdminList(): array
    {
        return $this->getNoticesFromDatabase();
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
        foreach ($this->noticeConfig['notices'] ?? [] as $index => $notice) {
            $notice['id'] = 'config_' . $index;
            if (!isset($notice['content'])) {
                $content = $this->getActiveTranslation($notice['translations'] ?? [], true);
                if ($content !== null) {
                    $notice['content'] = $content;
                }
            }
            $this->notices[] = $notice;
        }
        $this->notices = array_merge($this->notices, $this->getNoticesFromDatabase());
    }

    /**
     * Get notices from the database.
     *
     * @return array
     */
    protected function getNoticesFromDatabase(): array
    {
        return array_map(
            fn ($noticeEntity) => $this->noticeEntityToArray($noticeEntity),
            $this->noticeService->getNotices()
        );
    }

    /**
     * Get notice from the database by id.
     *
     * @param int $id Notice id
     *
     * @return ?array
     */
    public function getById(int $id): ?array
    {
        $noticeEntity = $this->noticeService->getById($id);
        if ($noticeEntity === null) {
            return null;
        }
        return $this->noticeEntityToArray($noticeEntity);
    }

    /**
     * Add notice to the database.
     *
     * @param array $notice Notice data
     *
     * @return void
     */
    public function addNotice(array $notice): void
    {
        $this->noticeService->insert($notice);
    }

    /**
     * Edit notice in the database.
     *
     * @param int   $id     Notice id
     * @param array $notice Notice data
     *
     * @return void
     */
    public function editNotice(int $id, array $notice): void
    {
        $this->noticeService->update($id, $notice);
    }

    /**
     * Delete notice from the database by id.
     *
     * @param string $id Notice id
     *
     * @return void
     */
    public function deleteById(string $id): void
    {
        $this->noticeService->delete($id);
    }

    /**
     * Map a notice db entity to the array format.
     *
     * @param NoticeEntityInterface $noticeEntity Notice entity
     *
     * @return array
     */
    protected function noticeEntityToArray(NoticeEntityInterface $noticeEntity): array
    {
        $translations = [];
        foreach ($noticeEntity->getTranslations() as $translationEntity) {
            $translations[$translationEntity->getLanguage()] = $translationEntity->getContent();
        }
        $notice = [
            'id' => $noticeEntity->getId(),
            'enabled' => $noticeEntity->isEnabled(),
            'position' => $noticeEntity->getPosition(),
            'style' => $noticeEntity->getStyle(),
            'contentType' => $noticeEntity->getContentType(),
            'conditions' => $noticeEntity->getConditions() ?? [],
            'translations' => $translations,
        ];
        $content = $this->getActiveTranslation($translations);
        if ($content !== null) {
            $notice['content'] = $content;
        }
        return $notice;
    }
}
