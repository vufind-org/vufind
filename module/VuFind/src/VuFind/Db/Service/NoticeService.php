<?php

/**
 * Database service for Notice.
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
use VuFind\Db\Entity\Notice;
use VuFind\Db\Entity\NoticeEntityInterface;
use VuFind\Db\Entity\NoticeTranslationEntityInterface;
use VuFind\Exception\NotFound;

use VuFind\Log\LoggerAwareTrait;
use function intval;

/**
 * Database service for Notice.
 *
 * @category VuFind
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class NoticeService extends AbstractDbService implements
    NoticeServiceInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Create a notice entity object.
     *
     * @return NoticeEntityInterface
     */
    public function createEntity(): NoticeEntityInterface
    {
        return $this->entityPluginManager->get(NoticeEntityInterface::class);
    }

    /**
     * Get a notice from the database based on id.
     *
     * @param int $id Notice id
     *
     * @return ?NoticeEntityInterface
     */
    public function getById(int $id): ?NoticeEntityInterface
    {
        return $this->entityManager->find(Notice::class, $id);
    }

    /**
     * Get complete list of notices from the database.
     *
     * @return NoticeEntityInterface[]
     */
    public function getNotices(): array
    {
        $dql = 'SELECT b '
            . 'FROM ' . NoticeEntityInterface::class . ' b '
            . 'ORDER BY b.displayOrder ASC, b.id ASC';
        $query = $this->entityManager->createQuery($dql);
        try {
            return $query->getResult();
        } catch (Exception $e) {
            $this->logError('Could not fetch notices from the database: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Set data on a notice entity.
     *
     * @param NoticeEntityInterface $notice Notice entity
     * @param array                 $data   Data
     *
     * @return void
     */
    protected function setNoticeData(NoticeEntityInterface $notice, array $data): void
    {
        if (isset($data['enabled'])) {
            $notice->setEnabled($data['enabled']);
        }
        if (isset($data['displayOrder'])) {
            $notice->setDisplayOrder($data['displayOrder']);
        }
        if (isset($data['position'])) {
            $notice->setPosition($data['position']);
        }
        if (isset($data['style'])) {
            $notice->setStyle($data['style']);
        }
        if (isset($data['contentType'])) {
            $notice->setContentType($data['contentType']);
        }
        if (isset($data['conditions'])) {
            $notice->setConditions($data['conditions']);
        }
    }

    /**
     * Set translations on a notice entity.
     *
     * @param NoticeEntityInterface $notice          Notice entity
     * @param array                 $translationData Translation data (keys are language
     * codes and values are the translations)
     *
     * @return void
     */
    protected function setTranslations(NoticeEntityInterface $notice, array $translationData): void
    {
        $translations = $notice->getTranslations();

        // update already existing languages
        foreach ($translations as $translation) {
            if (isset($translationData[$translation->getLanguage()])) {
                $translation->setContent($translationData[$translation->getLanguage()]);
                $this->persistEntity($translation);
                unset($translationData[$translation->getLanguage()]);
            } else {
                $this->deleteEntity($translation);
            }
        }

        // create translations for new languages
        foreach ($translationData as $language => $content) {
            $translation = $this->entityPluginManager->get(NoticeTranslationEntityInterface::class);
            $translation->setNotice($notice);
            $translation->setLanguage($language);
            $translation->setContent($content);
            $this->persistEntity($translation);
        }
    }

    /**
     * Insert a new notice into the database.
     *
     * @param array $data Notice data
     *
     * @return NoticeEntityInterface
     */
    public function insert(array $data): NoticeEntityInterface
    {
        $notice = $this->createEntity();

        // Set display order to last position in notice list.
        $dql = 'SELECT COUNT(b) '
            . 'FROM ' . NoticeEntityInterface::class . ' b';
        $query = $this->entityManager->createQuery($dql);

        $displayOrder = intval($query->getSingleScalarResult());

        $notice->setDisplayOrder($displayOrder);

        $this->setNoticeData($notice, $data);

        $notice->setCreated(new DateTime());
        $this->persistEntity($notice);

        $this->setTranslations($notice, $data['translations']);

        return $notice;
    }

    /**
     * Update an existing notice.
     *
     * @param int   $id   Notice id
     * @param array $data Notice data
     *
     * @return NoticeEntityInterface
     */
    public function update(int $id, array $data): NoticeEntityInterface
    {
        $notice = $this->getById($id);
        if ($notice === null) {
            throw new NotFound('Notice not found');
        }
        $this->setNoticeData($notice, $data);
        $this->persistEntity($notice);

        $this->setTranslations($notice, $data['translations']);

        return $notice;
    }

    /**
     * Delete an existing notice.
     *
     * @param int $id Notice id
     *
     * @return void
     */
    public function delete(int $id): void
    {
        $notice = $this->getById($id);

        if ($notice === null) {
            throw new NotFound('Notice not found');
        }

        $translations = $notice->getTranslations();
        foreach ($translations as $translation) {
            $this->deleteEntity($translation);
        }

        $this->deleteEntity($notice);
    }
}
