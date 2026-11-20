<?php

/**
 * Custom Link Manager.
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

use VuFind\Db\Entity\CustomLinkEntityInterface;
use VuFind\Db\Service\CustomLinkServiceInterface;
use VuFind\I18n\Locale\LocaleSettingsAwareInterface;
use VuFind\I18n\Locale\LocaleSettingsAwareTrait;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Custom Link Manager.
 *
 * @category VuFind
 * @package  Content
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CustomLinkManager implements LocaleSettingsAwareInterface
{
    use LocaleSettingsAwareTrait;

    /**
     * Custom links cache.
     *
     * @var ?array
     */
    protected ?array $customLinks = null;

    /**
     * Constructor.
     *
     * @param array                      $customLinkConfig  Custom link config
     * @param CustomLinkServiceInterface $customLinkService Custom link service
     */
    public function __construct(
        #[Autowire(config: 'CustomLinks', configType: 'yaml')]
        protected array $customLinkConfig,
        #[Autowire(container: \VuFind\Db\Service\PluginManager::class)]
        protected CustomLinkServiceInterface $customLinkService
    ) {
    }

    /**
     * Get custom link admin form config.
     *
     * @return array
     */
    public function getCustomLinkConfig(): array
    {
        return $this->customLinkConfig;
    }

    /**
     * Get custom link defaults.
     *
     * @return array
     */
    public function getDefaults(): array
    {
        $customLinkFormConfig = $this->customLinkConfig['adminForm'] ?? [];
        $contexts = $customLinkFormConfig['contexts'] ?? [];
        $context = $contexts[array_key_first($contexts)] ?? [];
        return [
            'position' => $context['position'] ?? 'default',
            'type' => $customLinkFormConfig['types'][0] ?? 'url',
        ];
    }

    /**
     * Get custom link list to be changed by admins.
     *
     * @return array
     */
    public function getAdminList(): array
    {
        return $this->getCustomLinksFromDatabase();
    }

    /**
     * Get all active custom links.
     *
     * @param ?string $position Optionally filter by this position
     *
     * @return array
     */
    public function getActiveList(?string $position = null): array
    {
        $activeCustomLinks = [];
        foreach ($this->getCustomLinks() as $customLink) {
            if (($position === null || $position === ($customLink['position'] ?? 'default'))) {
                $activeCustomLinks[] = $customLink;
            }
        }
        return $activeCustomLinks;
    }

    /**
     * Get custom links.
     *
     * @return array
     */
    protected function getCustomLinks(): array
    {
        if ($this->customLinks === null) {
            $this->loadCustomLinks();
        }
        return $this->customLinks;
    }

    /**
     * Load custom links.
     *
     * @return void
     */
    protected function loadCustomLinks(): void
    {
        $this->customLinks = $this->getCustomLinksFromDatabase();
    }

    /**
     * Get custom links from the database.
     *
     * @return array
     */
    protected function getCustomLinksFromDatabase(): array
    {
        return array_map(
            fn ($customLinkEntity) => $this->customLinkEntityToArray($customLinkEntity),
            $this->customLinkService->getCustomLinks()
        );
    }

    /**
     * Get custom link from the database by id.
     *
     * @param int $id Custom link id
     *
     * @return ?array
     */
    public function getByDatabaseId(int $id): ?array
    {
        $customLinkEntity = $this->customLinkService->getById($id);
        if ($customLinkEntity === null) {
            return null;
        }
        return $this->customLinkEntityToArray($customLinkEntity);
    }

    /**
     * Add custom link to the database.
     *
     * @param array $customLink Custom link data
     *
     * @return void
     */
    public function addCustomLinkToDatabase(array $customLink): void
    {
        $this->customLinkService->insert($customLink);
    }

    /**
     * Edit custom link in the database.
     *
     * @param int   $id         Custom link id
     * @param array $customLink Custom link data
     *
     * @return void
     */
    public function editDatabaseCustomLink(int $id, array $customLink): void
    {
        $this->customLinkService->update($id, $customLink);
    }

    /**
     * Delete custom link from the database by id.
     *
     * @param int $id Custom link id
     *
     * @return void
     */
    public function deleteByDatabaseId(int $id): void
    {
        $this->customLinkService->delete($id);
    }

    /**
     * Map a custom link db entity to the array format.
     *
     * @param CustomLinkEntityInterface $customLinkEntity Custom link entity
     *
     * @return array
     */
    protected function customLinkEntityToArray(CustomLinkEntityInterface $customLinkEntity): array
    {
        $translations = [];
        foreach ($customLinkEntity->getTranslations() as $translationEntity) {
            $translations[$translationEntity->getLanguage()] = [
                'title' => $translationEntity->getTitle(),
                'url' => $translationEntity->getUrl(),
            ];
        }
        $type = $customLinkEntity->getType();
        $customLink = [
            'id' => $customLinkEntity->getId(),
            'position' => $customLinkEntity->getPosition(),
            'type' => $type,
            'page' => $customLinkEntity->getPage(),
            'translations' => $translations,
        ];

        $title = $this->getActiveTranslation(array_map(
            fn ($translation) => $translation['title'] ?? null,
            $translations
        ));
        if ($title !== null) {
            $customLink['title'] = $title;
        }

        $url = $this->getActiveTranslation(array_map(
            fn ($translation) => $translation['url'] ?? null,
            $translations
        ));
        if ($url !== null) {
            $customLink['url'] = $url;
        }

        if ($type == 'url') {
            $customLink['htmlAttributes']['target'] = '_blank';
        }

        return $customLink;
    }
}
