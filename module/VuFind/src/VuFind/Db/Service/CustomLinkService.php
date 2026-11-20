<?php

/**
 * Database service for CustomLink.
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
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use DateTime;
use Exception;
use Psr\Log\LoggerAwareInterface;
use VuFind\Db\Entity\CustomLinkEntityInterface;
use VuFind\Db\Entity\CustomLinkTranslationEntityInterface;
use VuFind\Exception\NotFound;
use VuFind\Log\LoggerAwareTrait;

use function intval;

/**
 * Database service for CustomLink.
 *
 * @category VuFind
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class CustomLinkService extends AbstractDbService implements
    CustomLinkServiceInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Create a custom link entity object.
     *
     * @return CustomLinkEntityInterface
     */
    public function createEntity(): CustomLinkEntityInterface
    {
        return $this->entityPluginManager->get(CustomLinkEntityInterface::class);
    }

    /**
     * Get a custom link from the database based on id.
     *
     * @param int $id Custom link id
     *
     * @return ?CustomLinkEntityInterface
     */
    public function getById(int $id): ?CustomLinkEntityInterface
    {
        return $this->entityManager->find(CustomLinkEntityInterface::class, $id);
    }

    /**
     * Get complete list of custom links from the database.
     *
     * @return CustomLinkEntityInterface[]
     */
    public function getCustomLinks(): array
    {
        $dql = 'SELECT cl '
            . 'FROM ' . CustomLinkEntityInterface::class . ' cl '
            . 'ORDER BY cl.position, cl.displayOrder ASC, cl.id ASC';
        $query = $this->entityManager->createQuery($dql);
        try {
            return $query->getResult();
        } catch (Exception $e) {
            $this->logError('Could not fetch custom links from the database: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Set data on a custom link entity.
     *
     * @param CustomLinkEntityInterface $customLink Custom link entity
     * @param array                     $data       Data
     *
     * @return void
     */
    protected function setCustomLinkData(CustomLinkEntityInterface $customLink, array $data): void
    {
        if (isset($data['enabled'])) {
            $customLink->setEnabled($data['enabled']);
        }
        if (isset($data['displayOrder'])) {
            $customLink->setDisplayOrder($data['displayOrder']);
        }
        if (isset($data['position'])) {
            $customLink->setPosition($data['position']);
        }
        if (isset($data['type'])) {
            $customLink->setType($data['type']);
        }
        if (isset($data['page'])) {
            $customLink->setPage($data['page']);
        }
        if (isset($data['conditions'])) {
            $customLink->setConditions($data['conditions']);
        }
    }

    /**
     * Set translations on a custom link entity.
     *
     * @param CustomLinkEntityInterface $customLink      Custom link entity
     * @param array                     $translationData Translation data (keys are language
     * codes and values are the translations)
     *
     * @return void
     */
    protected function setTranslations(CustomLinkEntityInterface $customLink, array $translationData): void
    {
        $translations = $customLink->getTranslations();

        // update already existing languages
        foreach ($translations as $translation) {
            if (isset($translationData[$translation->getLanguage()])) {
                $content = $translationData[$translation->getLanguage()];
                $translation->setTitle($content['title'] ?? null);
                $translation->setUrl($content['url'] ?? null);
                $this->persistEntity($translation);
                unset($translationData[$translation->getLanguage()]);
            } else {
                $this->deleteEntity($translation);
            }
        }

        // create translations for new languages
        foreach ($translationData as $language => $content) {
            $translation = $this->entityPluginManager->get(CustomLinkTranslationEntityInterface::class);
            $translation->setCustomLink($customLink);
            $translation->setLanguage($language);
            $translation->setTitle($content['title'] ?? null);
            $translation->setUrl($content['url'] ?? null);
            $this->persistEntity($translation);
        }
    }

    /**
     * Insert a new custom link into the database.
     *
     * @param array $data Custom link data
     *
     * @return CustomLinkEntityInterface
     */
    public function insert(array $data): CustomLinkEntityInterface
    {
        $customLink = $this->createEntity();

        if (!isset($data['displayOrder'])) {
            // Set display order to last place in a position if not already provided.
            $dql = 'SELECT COUNT(cl) '
                . 'FROM ' . CustomLinkEntityInterface::class . ' cl';
            $query = $this->entityManager->createQuery($dql);
            $data['displayOrder'] = intval($query->getSingleScalarResult());
        }

        $this->setCustomLinkData($customLink, $data);

        $customLink->setCreated(new DateTime());
        $this->persistEntity($customLink);

        $this->setTranslations($customLink, $data['translations']);

        return $customLink;
    }

    /**
     * Update an existing custom link.
     *
     * @param int   $id   Custom link id
     * @param array $data Custom link data
     *
     * @return CustomLinkEntityInterface
     */
    public function update(int $id, array $data): CustomLinkEntityInterface
    {
        $customLink = $this->getById($id);
        if ($customLink === null) {
            throw new NotFound('Custom link not found');
        }
        $this->setCustomLinkData($customLink, $data);
        $this->persistEntity($customLink);

        $this->setTranslations($customLink, $data['translations']);

        return $customLink;
    }

    /**
     * Delete an existing custom link.
     *
     * @param int $id Custom link id
     *
     * @return void
     */
    public function delete(int $id): void
    {
        $customLink = $this->getById($id);

        if ($customLink === null) {
            throw new NotFound('Custom link not found');
        }

        $translations = $customLink->getTranslations();
        foreach ($translations as $translation) {
            $this->deleteEntity($translation);
        }

        $this->deleteEntity($customLink);
    }
}
