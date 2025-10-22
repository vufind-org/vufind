<?php

/**
 * Additional functionality for Finna and Primo records.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library 2015-2025.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */

namespace Finna\RecordDriver\Feature;

use Finna\RecordDriver\RenderContext;

use function count;
use function in_array;
use function is_array;
use function is_bool;
use function is_callable;

/**
 * Additional functionality for Finna and Primo records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
trait FinnaRecordTrait
{
    /**
     * Preferred language for display strings
     *
     * @var string
     */
    protected $preferredLanguage = null;

    /**
     * Search settings
     *
     * @var array
     */
    protected $datasourceSettings = null;

    /**
     * Default specs class used for the records.
     *
     * @var string
     */
    protected $defaultRecordSpecsClass = 'DefaultRecord';

    /**
     * Maximum limit of images to get in search results per record.
     *
     * @var int
     */
    protected int $maxImagesInSearch = 20;

    /**
     * Current record render context
     *
     * @var RenderContext
     */
    protected RenderContext $renderContext = RenderContext::RECORD;

    /**
     * Current amount of images
     *
     * @var int
     */
    protected int $imagesCount = 0;

    /**
     * Set current record render context
     *
     * @param string $context Record render context
     *
     * @return void
     */
    public function setRenderContext(string $context): void
    {
        $this->renderContext = RenderContext::from($context);
    }

    /**
     * Has the record exceeded maximum amount of images for its current context?
     * Amount of images allowed when renderContext is search is 20.
     *
     * @return bool
     */
    public function maxAmountOfImages(): bool
    {
        return $this->renderContext === RenderContext::SEARCH && $this->imagesCount >= $this->maxImagesInSearch;
    }

    /**
     * Get amount of images allowed to be rendered in current context.
     *
     * @return int Current images render limit or -1 for all.
     */
    public function getImagesRenderLimit(): int
    {
        if ($this->renderContext === RenderContext::SEARCH) {
            return $this->maxImagesInSearch;
        }
        return -1;
    }

    /**
     * Get the total image count for the record. Value is populated after calling the getAllImages function.
     *
     * @return int
     */
    public function getTotalAmountOfImages(): int
    {
        return $this->imagesCount;
    }

    /**
     * Get inappropriate comments for this record reported by the given user.
     *
     * @param ?int $userId Reporter ID or null to use current session
     *
     * @return array
     */
    public function getInappropriateComments($userId)
    {
        $table = $this->getDbTable('CommentsInappropriate');
        return $table->getForRecord(
            $userId,
            $this->getUniqueID()
        );
    }

    /**
     * Get OpenURL parameters for a book.
     *
     * @return array
     */
    protected function getBookOpenUrlParams()
    {
        $params = parent::getBookOpenUrlParams();
        if ($mmsId = $this->tryMethod('getAlmaMmsId')) {
            $params['rft.mms_id'] = $mmsId;
        }
        $params['rft.au'] = $this->getOpenUrlAuthor();

        return $params;
    }

    /**
     * Get OpenURL parameters for a book section.
     *
     * @return array
     */
    protected function getBookSectionOpenUrlParams()
    {
        $params = $this->getBookOpenUrlParams();
        $params['rft.volume'] = $this->getContainerVolume();
        $params['rft.issue'] = $this->getContainerIssue();
        $params['rft.spage'] = $this->getContainerStartPage();
        unset($params['rft.title']);
        $params['rft.btitle'] = $this->getContainerTitle();
        $params['rft.atitle'] = $this->getTitle();
        if ($mmsId = $this->tryMethod('getAlmaMmsId')) {
            $params['rft.mms_id'] = $mmsId;
        }
        $params['rft.au'] = $this->getOpenUrlAuthor();

        return $params;
    }

    /**
     * Get OpenURL parameters for a journal.
     *
     * @return array
     */
    protected function getJournalOpenUrlParams()
    {
        $params = parent::getJournalOpenUrlParams();
        if ($objectId = $this->tryMethod('getSfxObjectId')) {
            $params['rft.object_id'] = $objectId;
        }
        if ($mmsId = $this->tryMethod('getAlmaMmsId')) {
            $params['rft.mms_id'] = $mmsId;
        }
        $params['rft.au'] = $this->getOpenUrlAuthor();

        return $params;
    }

    /**
     * Get OpenURL parameters for an article.
     *
     * @return array
     */
    protected function getArticleOpenUrlParams()
    {
        $params = parent::getArticleOpenUrlParams();
        if ($doiFull = $this->tryMethod('getCleanDOI')) {
            $doi = preg_replace('/^doi:|^info:doi\//', '', $doiFull);
            $doi = 'info:doi/' . $doi;

            $params['rft_id'] = (array)($params['rft_id'] ?? []);
            $params['rft_id'][] = $doi;
        }
        if ($mmsId = $this->tryMethod('getAlmaMmsId')) {
            $params['rft.mms_id'] = $mmsId;
        }
        $params['rft.au'] = $this->getOpenUrlAuthor();

        return $params;
    }

    /**
     * Get OpenURL parameters for an unknown format.
     *
     * @param string $format Name of format
     *
     * @return array
     */
    protected function getUnknownFormatOpenUrlParams($format = 'UnknownFormat')
    {
        $params = $this->getDefaultOpenUrlParams();
        $resolver = strtolower($this->mainConfig->OpenURL->resolver ?? '');
        // Alma does not support rft_val_fmt 'info:ofi/fmt:kev:mtx:dc', so
        // use the 'info:ofi/fmt:kev:mtx:book' format instead
        if ('alma' === $resolver) {
            $params['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:book';
            // Don't set genre. It seems to cause Alma to ignore date and author.
            // $params['rft.genre'] = 'unknown';
            $params['rft.au'] = $this->getOpenUrlAuthor();
        } else {
            $params['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:dc';
            $params['rft.creator'] = $this->getOpenUrlAuthor();
            $params['rft.format'] = $format;
            $langs = $this->getLanguages();
            if (count($langs) > 0) {
                $params['rft.language'] = $langs[0];
            }
        }
        $publishers = $this->getPublishers();
        if (count($publishers) > 0) {
            $params['rft.pub'] = $publishers[0];
        }
        if ($mmsId = $this->tryMethod('getAlmaMmsId')) {
            $params['rft.mms_id'] = $mmsId;
        }

        return $params;
    }

    /**
     * Get an author for OpenURL
     *
     * @return string
     */
    protected function getOpenUrlAuthor()
    {
        $authors = $this->tryMethod('getNonPresenterAuthors');
        if (!empty($authors[0]['name'])) {
            return $authors[0]['name'];
        }
        $authors = $this->tryMethod('getPresenters');
        if (!empty($authors['presenters'][0]['name'])) {
            return $authors['presenters'][0]['name'];
        }
        if ($author = $this->getPrimaryAuthor()) {
            return trim($author, ' .');
        }
        if ($authors = $this->getSecondaryAuthors()) {
            return trim($authors[0], ' .');
        }

        return '';
    }

    /**
     * Get saved time associated with this record in a user list.
     *
     * @param int $list_id List id
     * @param int $user_id List owner id
     *
     * @return timestamp
     */
    public function getListSavedDate($list_id, $user_id)
    {
        $db = $this->getDbTable('UserResource');
        $data = $db->getSavedData(
            $this->getUniqueId(),
            $this->getSourceIdentifier(),
            $list_id,
            $user_id
        );
        foreach ($data as $current) {
            return $current->saved;
        }
        return null;
    }

    /**
     * Set preferred language for display strings.
     *
     * @param string $language Language
     *
     * @return void
     */
    public function setPreferredLanguage($language)
    {
        $this->preferredLanguage = $language;
    }

    /**
     * Allow record image to be downloaded?
     *
     * @param array $image Image to check
     *
     * @return bool
     */
    public function allowRecordImageDownload(array $image = []): bool
    {
        // Check rights from index if they would not allow the download
        $indexRights = $this->tryMethod('getUsageRights', [], []);
        if (empty($indexRights) || in_array('usage_F', $indexRights)) {
            return false;
        }
        if (empty($image)) {
            return true;
        }
        if (!empty($this->mainConfig->FileDownload->excludeRights)) {
            $restrictions
                = $this->mainConfig->FileDownload->excludeRights->toArray();
            $copyright = mb_strtoupper($image['rights']['copyright'] ?? '', 'UTF-8');
            if (in_array($copyright, $restrictions)) {
                return false;
            }
        }
        // PDF key can be either boolean or an array containing booleans
        $pdf = $image['pdf'] ?? false;
        if (!is_bool($pdf)) {
            $pdf = is_array($pdf) && array_search(true, $image['pdf']) !== false;
        }
        if (
            $pdf
            && !empty($this->mainConfig->Content->pdfCoverImageDownload)
        ) {
            return !empty(
                array_intersect(
                    explode(',', $this->mainConfig->Content->pdfCoverImageDownload),
                    $this->getFormats()
                )
            );
        }
        return true;
    }

    /**
     * Is authority functionality enabled?
     *
     * @param string $type Authority type
     *
     * @return bool
     */
    public function isAuthorityEnabled($type = '*')
    {
        return !empty($this->getAuthoritySource($type));
    }

    /**
     * Whether the record has related records declared in metadata.
     * (used by RecordDriverRelated related module).
     *
     * @return bool
     */
    public function hasRelatedRecords()
    {
        return false;
    }

    /**
     * Format authority id by prefixing the given id with authority record source.
     *
     * @param string $id   Authority id
     * @param string $type Authority type (e.g. author)
     *
     * @return null|string
     */
    public function getAuthorityId($id, $type = '*')
    {
        if (!$id) {
            return $id;
        }
        if (preg_match('/^https?:/', $id)) {
            // Never prefix http(s) url's
            return $id;
        }

        if (!$this->datasourceSettings || !is_callable([$this, 'getDatasource'])) {
            return $id;
        }

        $recordSource = $this->getDataSource();
        if (!($authSrc = $this->getAuthoritySource($type))) {
            return null;
        }

        $idRegex
            = $this->datasourceSettings[$recordSource]['authority_id_regex'][$type]
            ?? $this->datasourceSettings[$recordSource]['authority_id_regex']['*']
            ?? null;

        if ($idRegex && preg_match($idRegex, $id)) {
            if (str_starts_with($id, "$authSrc.")) {
                return $id;
            }
            return "$authSrc.$id";
        }

        $plainIdRegex
            = $this->datasourceSettings[$recordSource]['authority_plain_id_regex'][$type]
            ?? $this->datasourceSettings[$recordSource]['authority_plain_id_regex']['*']
            ?? null;
        if ($plainIdRegex && preg_match($plainIdRegex, $id)) {
            return $id;
        }

        if (!$idRegex && !$plainIdRegex) {
            if (str_starts_with($id, "$authSrc.")) {
                return $id;
            }
            return "$authSrc.$id";
        }

        return null;
    }

    /**
     * Attach datasource settings to the driver.
     *
     * @param array $settings Settings
     *
     * @return void
     */
    public function attachDatasourceSettings($settings)
    {
        $this->datasourceSettings = $settings;
    }

    /**
     * Get authority record source.
     *
     * @param string $type Authority type
     *
     * @return string|null
     */
    protected function getAuthoritySource($type = '*')
    {
        if (!is_callable([$this, 'getDatasource'])) {
            return null;
        }
        $recordSource = $this->getDataSource();
        return $this->datasourceSettings[$recordSource]['authority'][$type]
            ?? $this->datasourceSettings[$recordSource]['authority']['*']
            ?? null;
    }

    /**
     * Whether to show record labels for this record.
     *
     * @return boolean
     */
    public function getRecordLabelsEnabled()
    {
        $labelsConfig = $this->mainConfig->RecordLabels->showLabels ?? null;
        if (!$labelsConfig) {
            return false;
        }
        $backend = $this->getSourceIdentifier();
        return $labelsConfig[$backend]
            ?? $labelsConfig['*']
            ?? false;
    }

    /**
     * Get class name for RecordDataFormatter spec.
     *
     * @return ?string
     */
    public function getRecordDataFormatterSpecClass(): ?string
    {
        $defaultSpecsClass = 'Finna\\RecordDataFormatter\\Specs\\' . $this->defaultRecordSpecsClass;
        $dataSource = $this->tryMethod('getDataSource');
        if (!$dataSource) {
            return $defaultSpecsClass;
        }
        $datasourceSpecsClass = $this->datasourceSettings[$dataSource]['record']['record_field_specs']
            ?? '';
        if ($datasourceSpecsClass === 'CollectionRecord' && $this->tryMethod('getChildCollections')) {
            return 'Finna\\RecordDataFormatter\\Specs\\' . $datasourceSpecsClass;
        }
        return $defaultSpecsClass;
    }
}
