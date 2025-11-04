<?php

/**
 * Console service for verifying record links, resources and ratings.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2024.
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
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace FinnaConsole\Command\Util;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Finna\Db\Entity\RatingsEntityInterface;
use Finna\Db\Service\CommentsServiceInterface;
use Finna\Db\Service\FinnaCommentsRecordServiceInterface;
use Finna\Db\Service\RatingsServiceInterface;
use Finna\Db\Service\RecordServiceInterface;
use Finna\Db\Service\ResourceServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Record\Loader as RecordLoader;
use VuFindSearch\Backend\Solr\Backend as SolrBackend;

use function assert;
use function count;
use function in_array;

/**
 * Console service for verifying record links, resources and ratings.
 *
 * @category VuFind
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'util/verify_record_links'
)]
class VerifyRecordLinks extends AbstractUtilCommand
{
    /**
     * Record batch size to process at a time
     *
     * @var int
     */
    protected $batchSize = 1000;

    /**
     * Constructor
     *
     * @param EntityManagerInterface              $entityManager              Entity manager
     * @param RecordServiceInterface              $recordService              Record database service
     * @param CommentsServiceInterface            $commentsService            Comments service
     * @param FinnaCommentsRecordServiceInterface $finnaCommentsRecordService Comments service
     * @param RatingsServiceInterface             $ratingsService             Ratings service
     * @param ResourceServiceInterface            $resourceService            Resource service
     * @param SolrBackend                         $solr                       Search backend
     * @param RecordLoader                        $recordLoader               Record loader
     * @param array                               $searchConfig               Search config
     */
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected RecordServiceInterface $recordService,
        protected CommentsServiceInterface $commentsService,
        protected FinnaCommentsRecordServiceInterface $finnaCommentsRecordService,
        protected RatingsServiceInterface $ratingsService,
        protected ResourceServiceInterface $resourceService,
        protected \VuFindSearch\Backend\Solr\Backend $solr,
        protected RecordLoader $recordLoader,
        protected \VuFind\Config\Config $searchConfig
    ) {
        $recordLoader->setCacheContext(\VuFind\Record\Cache::CONTEXT_DISABLED);

        parent::__construct();
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setDescription('Verify and update record links in the database')
            ->addOption(
                'resources',
                null,
                InputOption::VALUE_NEGATABLE,
                'Whether to process saved resources (records) -- default is true',
                true
            )
            ->addOption(
                'comments',
                null,
                InputOption::VALUE_NEGATABLE,
                'Whether to process comments -- default is true',
                true
            )
            ->addOption(
                'ratings',
                null,
                InputOption::VALUE_NEGATABLE,
                'Whether to process ratings -- default is true',
                true
            );
    }

    /**
     * Run the command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $this->msg('Record link verification started');

        if ($input->getOption('comments')) {
            $this->checkCommentLinks();
        }

        if ($input->getOption('ratings')) {
            $this->checkRatingLinks();
        }
        if ($input->getOption('resources')) {
            $this->checkResources();
        }

        return 0;
    }

    /**
     * Check comment links
     *
     * @return void
     */
    protected function checkCommentLinks(): void
    {
        $this->msg('Checking comments');
        $count = $fixed = 0;
        $lastId = null;
        $batch = [];
        do {
            $comments = $this->commentsService->getEntityBatch($lastId, $this->batchSize);
            $lastId = null;

            foreach ($comments as $comment) {
                $lastId = $comment->getId();
                $resource = $comment->getResource();
                if (!$resource || 'Solr' !== $resource->getSource()) {
                    continue;
                }
                $batch[] = [
                    'comment' => $comment,
                    'recordId' => $resource->getRecordId(),
                ];
                if (count($batch) < 100) {
                    continue;
                }
                $fixed += $this->verifyCommentLinkBatch($batch);
                $count += count($batch);
                $batch = [];
                $msg = "$count comments checked, $fixed links fixed";
                if ($count % 1000 == 0) {
                    $this->msg($msg);
                } else {
                    $this->msg($msg, OutputInterface::VERBOSITY_VERY_VERBOSE);
                }
            }
        } while (null !== $lastId);
        if ($batch) {
            $fixed += $this->verifyCommentLinkBatch($batch);
            $count += count($batch);
        }
        $this->msg("Comment check completed with $count comments checked, $fixed links fixed");
    }

    /**
     * Verify comment links for a batch of comments
     *
     * @param array $batch Batch to process
     *
     * @return int Number of comments fixed
     */
    protected function verifyCommentLinkBatch(array $batch): int
    {
        $recordIds = array_column($batch, 'recordId');
        $allIds = $this->getDedupRecordIds($recordIds);

        $fixed = 0;
        foreach ($batch as $current) {
            $comment = $current['comment'];
            $recordId = $current['recordId'];
            // This preserves the comment-record links for a comment when all
            // links point to non-existent records. Dangling links have no
            // effect in the UI. If a record was temporarily unavailable and
            // gets re-added to the index with the same ID, the comment is shown
            // in the UI again.
            $recordIds = $allIds[$recordId] ?? [$recordId];

            $linkedRecordIds = [];

            // Remove any orphaned links
            $links = $this->finnaCommentsRecordService->findByComment($current['comment']);
            foreach ($links as $link) {
                if (!in_array($link->getRecordId(), $recordIds)) {
                    $this->entityManager->remove($link);
                    ++$fixed;
                } else {
                    $linkedRecordIds[] = $link->record_id;
                }
            }

            // Add missing links
            $missingRecordIds = array_diff($recordIds, $linkedRecordIds);
            foreach ($missingRecordIds as $recordId) {
                $link = $this->finnaCommentsRecordService->createEntity();
                $link->setComment($comment)
                    ->setRecordId($recordId);
                $this->entityManager->persist($link);
                ++$fixed;
            }
        }
        $this->entityManager->flush();
        return $fixed;
    }

    /**
     * Check rating links
     *
     * @return void
     */
    protected function checkRatingLinks(): void
    {
        $this->msg('Checking ratings');
        $count = $fixed = 0;
        $startDate = new DateTime();
        $lastId = null;
        $batch = [];
        do {
            $ratings = $this->ratingsService->getEntityBatch($lastId, $this->batchSize);
            $lastId = null;

            foreach ($ratings as $current) {
                $rating = $current['rating'];
                assert($rating instanceof RatingsEntityInterface);
                // Re-read the record since since it may have changed:
                $this->entityManager->refresh($rating);
                $lastId = $rating->getId();
                if ($rating->getFinnaChecked() >= $startDate) {
                    continue;
                }

                $resource = $rating->getResource();
                if ('Solr' !== $resource->getSource()) {
                    continue;
                }
                $batch[] = [
                    'rating' => $rating,
                    'recordId' => $resource->getRecordId(),
                ];
                if (count($batch) < 100) {
                    continue;
                }
                $fixed += $this->verifyRatingLinkBatch($batch);
                $count += count($batch);
                $batch = [];
                $msg = "$count ratings checked, $fixed links fixed";
                if ($count % 1000 == 0) {
                    $this->msg($msg);
                } else {
                    $this->msg($msg, OutputInterface::VERBOSITY_VERY_VERBOSE);
                }
            }
        } while (null !== $lastId);
        if ($batch) {
            $fixed += $this->verifyRatingLinkBatch($batch);
            $count += count($batch);
        }
        $this->msg("Rating check completed with $count ratings checked, $fixed links fixed");
    }

    /**
     * Verify ratings
     *
     * @param array $batch Batch of rating + recordId
     *
     * @return int Number of ratings fixed
     */
    protected function verifyRatingLinkBatch(array $batch): int
    {
        $recordIds = array_column($batch, 'recordId');
        $allIds = $this->getDedupRecordIds($recordIds);
        $fixed = 0;
        foreach ($batch as $current) {
            $rating = $current['rating'];
            $recordId = $current['recordId'];
            $ids = $allIds[$recordId] ?? [];
            if (!$allIds) {
                continue;
            }
            foreach ($ids as $id) {
                if ($id === $recordId) {
                    continue;
                }
                $resource = $this->resourceService->getResourceByRecordId($id, 'Solr');
                if (!$resource) {
                    continue;
                }

                $targetRating = $this->ratingsService->getByResourceAndUser($resource, $rating->getUser());
                if ($targetRating) {
                    if ($targetRating->getRating() !== $rating->getRating()) {
                        // Count as fixed (actual update below):
                        ++$fixed;
                    }
                } else {
                    ++$fixed;
                    $targetRating = $this->ratingsService->createEntity();
                    $targetRating->setUser($rating->getUser())
                        ->setResource($rating->getResource());
                }
                $targetRating->setRating($rating->getRating());
                // Don't set creation date to indicate that this is a generated entry
                $targetRating->setFinnaChecked(new DateTime());
                $this->entityManager->persist($targetRating);
            }
            $rating->setFinnaChecked(new DateTime());
            $this->entityManager->persist($rating);
        }
        $this->entityManager->flush();
        return $fixed;
    }

    /**
     * Check resources (records)
     *
     * @return void
     */
    protected function checkResources(): void
    {
        $this->msg('Checking saved Solr resources for moved records');
        $count = $fixed = 0;
        $lastId = null;
        $batch = [];
        do {
            $resources = $this->resourceService->getEntityBatch($lastId, $this->batchSize);
            $lastId = null;

            foreach ($resources as $resource) {
                $lastId = $resource->getid();
                $batch[] = $resource;
                if (count($batch) < 100) {
                    continue;
                }
                $fixed += $this->verifyResourceIds($batch);
                $count += count($batch);
                $batch = [];
                $msg = "$count resources checked, $fixed id's updated";
                if ($count % 1000 == 0) {
                    $this->msg($msg);
                } else {
                    $this->msg($msg, OutputInterface::VERBOSITY_VERY_VERBOSE);
                }
            }
        } while (null !== $lastId);
        if ($batch) {
            $fixed += $this->verifyResourceIds($batch);
            $count += count($batch);
        }
        $this->msg("Resource checking completed with $count resources checked, $fixed id's fixed");
    }

    /**
     * Verify resource ids
     *
     * @param array $resources Resources to verify
     *
     * @return int Number of fixed resources
     */
    protected function verifyResourceIds(array $resources): int
    {
        $ids = [];
        foreach ($resources as $resource) {
            $ids[] = [
                'id' => $resource->getRecordId(),
                'source' => $resource->getSource(),
            ];
        }
        // Try to load the records. The resources for any changed records are updated automatically.
        $records = $this->recordLoader->loadBatch($ids, true);

        // Report results:
        $fixed = 0;
        foreach ($records as $idx => $record) {
            $resource = $resources[$idx];
            $resourceId = $resource->getId();
            if ($record instanceof \VuFind\RecordDriver\Missing) {
                $recId = $resource->getSource() . ':' . $resource->getRecordid();
                $this->msg(
                    "Record missing for resource $resourceId (record $recId)",
                    OutputInterface::VERBOSITY_VERBOSE
                );
            }

            $id = $record->getUniqueId();
            if ($id != $resource->getRecordId()) {
                $oldRecordId = $resource->getRecordId();
                $this->msg("Resource $resourceId record ID updated from $oldRecordId to $id");
                ++$fixed;
            }
        }
        return $fixed;
    }

    /**
     * Get IDs of duplicate records (including the given record)
     *
     * @param array $recordIds Record IDs
     *
     * @return array Associative array of arrays with record ID as the key
     */
    protected function getDedupRecordIds(array $recordIds): array
    {
        // Search directly in Solr to avoid any listeners or filters from interfering
        $escapedIds = array_map(
            function ($i) {
                return '"' . addcslashes($i, '"') . '"';
            },
            $recordIds
        );

        $query = new \VuFindSearch\Query\Query();
        $params = new \VuFindSearch\ParamBag(
            [
                'hl' => 'false',
                'spellcheck' => 'false',
                'sort' => '',
                'q' => 'local_ids_str_mv:(' . implode(' OR ', $escapedIds) . ')',
            ]
        );
        $records = $this->solr->search($query, 0, 1000, $params)->getRecords();

        $result = [];
        foreach ($records as $record) {
            $localIds = $record->getLocalIds();
            foreach ($recordIds as $id) {
                if (in_array($id, $localIds)) {
                    $result[$id] = $localIds;
                    break;
                }
            }
        }
        return $result;
    }
}
