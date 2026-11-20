<?php

/**
 * Database service for ContentPage.
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
use VuFind\Db\Entity\ContentPageEntityInterface;
use VuFind\Db\Entity\ContentPageTranslationEntityInterface;
use VuFind\Exception\NotFound;
use VuFind\Log\LoggerAwareTrait;

/**
 * Database service for ContentPage.
 *
 * @category VuFind
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class ContentPageService extends AbstractDbService implements
    ContentPageServiceInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Create a content page entity object.
     *
     * @return ContentPageEntityInterface
     */
    public function createEntity(): ContentPageEntityInterface
    {
        return $this->entityPluginManager->get(ContentPageEntityInterface::class);
    }

    /**
     * Get a content page from the database based on HRID.
     *
     * @param string $hrid HRID
     *
     * @return ?ContentPageEntityInterface
     */
    public function getByHrid(string $hrid): ?ContentPageEntityInterface
    {
        $dql = 'SELECT cp '
            . 'FROM ' . ContentPageEntityInterface::class . ' cp '
            . 'WHERE cp.hrid = :hrid';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('hrid', $hrid);
        return $query->getOneOrNullResult();
    }

    /**
     * Get complete list of content pages from the database.
     *
     * @return ContentPageEntityInterface[]
     */
    public function getContentPages(): array
    {
        $dql = 'SELECT cp '
            . 'FROM ' . ContentPageEntityInterface::class . ' cp';
        $query = $this->entityManager->createQuery($dql);
        try {
            return $query->getResult();
        } catch (Exception $e) {
            $this->logError('Could not fetch custom links from the database: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Set data on a content page entity.
     *
     * @param ContentPageEntityInterface $contentPage Content page entity
     * @param array                      $data        Data
     *
     * @return void
     */
    protected function setContentPageData(ContentPageEntityInterface $contentPage, array $data): void
    {
        if (isset($data['enabled'])) {
            $contentPage->setEnabled($data['enabled']);
        }
        if (isset($data['contentType'])) {
            $contentPage->setContentType($data['contentType']);
        }
        if (isset($data['conditions'])) {
            $contentPage->setConditions($data['conditions']);
        }
    }

    /**
     * Set translations on a content page entity.
     *
     * @param ContentPageEntityInterface $contentPage     Content page entity
     * @param array                      $translationData Translation data (keys are language
     * codes and values are the translations)
     *
     * @return void
     */
    protected function setTranslations(ContentPageEntityInterface $contentPage, array $translationData): void
    {
        $translations = $contentPage->getTranslations();

        // update already existing languages
        foreach ($translations as $translation) {
            if (isset($translationData[$translation->getLanguage()])) {
                $content = $translationData[$translation->getLanguage()];
                $translation->setHeadline($content['headline'] ?? null);
                $translation->setContent($content['content'] ?? null);
                $this->persistEntity($translation);
                unset($translationData[$translation->getLanguage()]);
            } else {
                $this->deleteEntity($translation);
            }
        }

        // create translations for new languages
        foreach ($translationData as $language => $content) {
            $translation = $this->entityPluginManager->get(ContentPageTranslationEntityInterface::class);
            $translation->setContentPage($contentPage);
            $translation->setLanguage($language);
            $translation->setHeadline($content['headline'] ?? null);
            $translation->setContent($content['content'] ?? null);
            $this->persistEntity($translation);
        }
    }

    /**
     * Insert a new content page into the database.
     *
     * @param array $data Content page data
     *
     * @return ContentPageEntityInterface
     */
    public function insert(array $data): ContentPageEntityInterface
    {
        $contentPage = $this->createEntity();

        // create HRID based on headline
        $headline = array_values(array_filter(
            array_map(
                fn ($translation) => $translation['headline'] ?? null,
                $data['translations']
            )
        ))[0] ?? 'content-page';
        $hrid = preg_replace('/[^a-zA-Z0-9]/', '-', $headline);

        // if HRID is already being used and append number
        $dql = 'SELECT COUNT(cp) '
            . 'FROM ' . ContentPageEntityInterface::class . ' cp '
            . 'WHERE cp.hrid LIKE :hridPrefix';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('hridPrefix', $hrid . '%');
        $count = $query->getSingleScalarResult();
        if ($count > 0) {
            $hrid .= '-' . ($count + 1);
        }
        $contentPage->setHrid($hrid);

        $this->setContentPageData($contentPage, $data);

        $contentPage->setCreated(new DateTime());
        $this->persistEntity($contentPage);

        $this->setTranslations($contentPage, $data['translations']);

        return $contentPage;
    }

    /**
     * Update an existing content page by HRID.
     *
     * @param string $hrid Content page HRID
     * @param array  $data Content page data
     *
     * @return ContentPageEntityInterface
     */
    public function updateByHrid(string $hrid, array $data): ContentPageEntityInterface
    {
        $contentPage = $this->getByHrid($hrid);
        if ($contentPage === null) {
            throw new NotFound('Content page not found');
        }
        $this->setContentPageData($contentPage, $data);
        $this->persistEntity($contentPage);

        $this->setTranslations($contentPage, $data['translations']);

        return $contentPage;
    }

    /**
     * Delete an existing content page by HRID.
     *
     * @param string $hrid Content page ID
     *
     * @return void
     */
    public function deleteByHrid(string $hrid): void
    {
        $contentPage = $this->getByHrid($hrid);

        if ($contentPage === null) {
            throw new NotFound('Content page not found');
        }

        $translations = $contentPage->getTranslations();
        foreach ($translations as $translation) {
            $this->deleteEntity($translation);
        }

        $this->deleteEntity($contentPage);
    }
}
