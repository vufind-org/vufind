<?php

/**
 * Database service for Records.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace Finna\Db\Service;

use Closure;
use Finna\Db\Table\CommentsRecord;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Date\Converter as DateConverter;
use VuFind\Db\Table\Ratings;
use VuFind\Db\Table\Resource;
use VuFind\Exception\RecordMissing as RecordMissingException;
use VuFind\Record\Loader as RecordLoader;

use function count;

/**
 * Database service for Records.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class RecordService extends \VuFind\Db\Service\RecordService implements FinnaRecordServiceInterface
{
    /**
     * Record batch size to process at a time
     *
     * @var int
     */
    protected $batchSize = 1000;

    /**
     * Check comment links
     *
     * @param Closure $getDedupRecordIds Callback to get dedup record IDs
     * @param Closure $msgCallback       Progress message callback
     *
     * @return void
     */
    public function checkCommentLinks(Closure $getDedupRecordIds, Closure $msgCallback): void
    {
        $msgCallback('Checking comments');
        $count = $fixed = 0;
        $lastId = null;
        $batch = [];
        $commentsTable = $this->getDbTable('comments');
        $resourceTable = $this->getDbTable('resource');
        $commentsRecordTable = $this->getDbTable(\Finna\Db\Table\CommentsRecord::class);
        do {
            $callback = function ($select) use ($lastId) {
                if (null !== $lastId) {
                    $select->where->greaterThan('id', $lastId);
                }
                $select->order('id');
                $select->limit($this->batchSize);
                $select->columns(['id', 'resource_id']);
            };
            // Callback now has the old value of $lastId, reset it:
            $lastId = null;
            $comments = $commentsTable->select($callback);
            foreach ($comments as $comment) {
                $lastId = $comment->id;
                $resource = $resourceTable->select(['id' => $comment->resource_id])->current();
                if (!$resource || 'Solr' !== $resource->source) {
                    continue;
                }
                $batch[] = [
                    'commentId' => $comment->id,
                    'recordId' => $resource->record_id,
                ];
                if (count($batch) < 100) {
                    continue;
                }
                $fixed += $this->verifyCommentLinkBatch($batch, $commentsRecordTable, $getDedupRecordIds);
                $count += count($batch);
                $batch = [];
                $msg = "$count comments checked, $fixed links fixed";
                if ($count % 1000 == 0) {
                    $msgCallback($msg);
                } else {
                    $msgCallback($msg, OutputInterface::VERBOSITY_VERY_VERBOSE);
                }
            }
        } while (null !== $lastId);
        if ($batch) {
            $fixed += $this->verifyCommentLinkBatch($batch, $commentsRecordTable, $getDedupRecordIds);
            $count += count($batch);
        }
        $msgCallback("Comment check completed with $count comments checked, $fixed links fixed");
    }

    /**
     * Check rating links
     *
     * @param Closure $getDedupRecordIds Callback to get dedup record IDs
     * @param Closure $msgCallback       Progress message callback
     *
     * @return void
     */
    public function checkRatingLinks(Closure $getDedupRecordIds, Closure $msgCallback): void
    {
        $msgCallback('Checking ratings');
        $count = $fixed = 0;
        $startDate = date('Y-m-d');
        $lastId = null;
        $batch = [];
        $ratingsTable = $this->getDbTable('ratings');
        $resourceTable = $this->getDbTable('resource');
        do {
            $callback = function ($select) use ($lastId) {
                if (null !== $lastId) {
                    $select->where->greaterThan('id', $lastId);
                }
                $select->where->notEqualTo('created', '2000-01-01 00:00:00');
                $select->order('id');
                $select->limit($this->batchSize);
                $select->columns(['id']);
            };
            // Callback now has the old value of $lastId, reset it:
            $lastId = null;
            $ratings = $ratingsTable->select($callback);
            foreach ($ratings as $current) {
                // Re-read the record since since may have changed:
                $rating = $ratingsTable->select(['id' => $current->id])->current();
                $lastId = $rating->id;
                if ($rating->finna_checked >= $startDate) {
                    continue;
                }
                $resource = $resourceTable->select(['id' => $rating->resource_id])->current();
                if (!$resource || 'Solr' !== $resource->source) {
                    continue;
                }
                $batch[] = [
                    'rating' => $rating,
                    'recordId' => $resource->record_id,
                ];
                if (count($batch) < 100) {
                    continue;
                }
                $fixed += $this->verifyRatingLinkBatch($batch, $ratingsTable, $resourceTable, $getDedupRecordIds);
                $count += count($batch);
                $batch = [];
                $msg = "$count ratings checked, $fixed links fixed";
                if ($count % 1000 == 0) {
                    $msgCallback($msg);
                } else {
                    $msgCallback($msg, OutputInterface::VERBOSITY_VERY_VERBOSE);
                }
            }
        } while (null !== $lastId);
        if ($batch) {
            $fixed += $this->verifyRatingLinkBatch($batch, $ratingsTable, $resourceTable, $getDedupRecordIds);
            $count += count($batch);
        }
        $msgCallback("Rating check completed with $count ratings checked, $fixed links fixed");
    }

    /**
     * Check resources (records)
     *
     * @param RecordLoader $recordLoader Record loader
     * @param Closure      $msgCallback  Progress message callback
     *
     * @return void
     */
    public function checkResources(RecordLoader $recordLoader, Closure $msgCallback): void
    {
        $msgCallback('Checking saved Solr resources for moved records');
        $count = $fixed = 0;
        $lastId = null;
        $batch = [];
        $resourceTable = $this->getDbTable('resource');
        do {
            $callback = function ($select) use ($lastId) {
                $select->where->equalTo('source', 'Solr');
                if (null !== $lastId) {
                    $select->where->greaterThan('id', $lastId);
                }
                $select->order('id');
                $select->limit($this->batchSize);
            };
            $lastId = null;
            $resources = $resourceTable->select($callback);
            foreach ($resources as $resource) {
                $lastId = $resource->id;
                $batch[] = $resource;
                if (count($batch) < 100) {
                    continue;
                }
                $fixed += $this->verifyResourceIds($batch, $recordLoader, $msgCallback);
                $count += count($batch);
                $batch = [];
                $msg = "$count resources checked, $fixed id's updated";
                if ($count % 1000 == 0) {
                    $msgCallback($msg);
                } else {
                    $msgCallback($msg, OutputInterface::VERBOSITY_VERY_VERBOSE);
                }
            }
        } while (null !== $lastId);
        if ($batch) {
            $fixed += $this->verifyResourceIds($batch, $recordLoader, $msgCallback);
            $count += count($batch);
        }
        $msgCallback("Resource checking completed with $count resources checked, $fixed id's fixed");
    }

    /**
     * Verify resource metadata
     *
     * @param RecordLoader  $recordLoader  Record loader
     * @param DateConverter $dateConverter Date converter
     * @param ?string       $backendId     Optional backend ID to limit to
     * @param Closure       $msgCallback   Progress message callback
     *
     * @return void
     */
    public function verifyResourceMetadata(
        RecordLoader $recordLoader,
        DateConverter $dateConverter,
        ?string $backendId,
        Closure $msgCallback
    ): void {
        $msgCallback('Resource metadata verification started');
        $count = $fixed = 0;
        $callback = function ($select) use ($backendId) {
            if ($backendId) {
                $select->where->equalTo('source', $backendId);
            }
        };
        $resourceTable = $this->getDbTable('resource');
        $resources = $resourceTable->select($callback);
        if (!$resources) {
            $msgCallback('No resources found');
            return;
        }

        $count = 0;
        $fixed = 0;
        $msgCallback($resources->count() . ' records to check');
        $recordLoader->setCacheContext(\VuFind\Record\Cache::CONTEXT_FAVORITE);
        foreach ($resources as $resource) {
            $msgCallback(
                "Checking record $resource->source:$resource->record_id",
                OutputInterface::VERBOSITY_VERBOSE
            );
            try {
                $driver = $recordLoader->load($resource->record_id, $resource->source);
                $original = clone $resource;
                // Reset metadata first, otherwise assignMetadata doesn't do anything
                $resource->title = '';
                $resource->author = '';
                $resource->year = '';
                $resource->assignMetadata($driver, $dateConverter);
                if ($original != $resource) {
                    $resource->save();
                    ++$fixed;
                    $msgCallback(
                        "Updated record $resource->source:$resource->record_id",
                        OutputInterface::VERBOSITY_VERBOSE
                    );
                }
            } catch (\Exception $e) {
                $msgCallback(
                    'Unable to load metadata for record '
                    . "{$resource->source}:{$resource->record_id}: "
                    . $e->getMessage()
                );
            } catch (\TypeError $e) {
                $msgCallback(
                    "Unable to load metadata for record {$resource->source}:{$resource->record_id}: " . $e->getMessage()
                );
            }
            ++$count;
            if ($count % 1000 == 0) {
                $msgCallback("$count resources processed, $fixed fixed");
            }
        }

        $msgCallback("Resource metadata verification completed with $count resources processed, $fixed fixed");
    }

    /**
     * Verify comment links for a batch of comments
     *
     * @param array          $batch               Batch of commentId + recordId
     * @param CommentsRecord $commentsRecordTable CommentsRecord table
     * @param Closure        $getDedupRecordIds   Callback to get dedup record IDs
     *
     * @return int Number of comments fixed
     */
    protected function verifyCommentLinkBatch(
        array $batch,
        CommentsRecord $commentsRecordTable,
        Closure $getDedupRecordIds
    ): int {
        $recordIds = array_column($batch, 'recordId');
        $allIds = $getDedupRecordIds($recordIds);

        $fixed = 0;
        foreach ($batch as $current) {
            $commentId = $current['commentId'];
            $recordId = $current['recordId'];
            // This preserves the comment-record links for a comment when all
            // links point to non-existent records. Dangling links have no
            // effect in the UI. If a record was temporarily unavailable and
            // gets re-added to the index with the same ID, the comment is shown
            // in the UI again.
            $ids = $allIds[$recordId] ?? [$recordId];
            if ($commentsRecordTable->verifyLinks($commentId, $ids)) {
                ++$fixed;
            }
        }
        return $fixed;
    }

    /**
     * Verify ratings
     *
     * @param array    $batch             Batch of rating + recordId
     * @param Ratings  $ratingsTable      Ratings table
     * @param Resource $resourceTable     Resource table
     * @param Closure  $getDedupRecordIds Callback to get dedup record IDs
     *
     * @return int Number of ratings fixed
     */
    protected function verifyRatingLinkBatch(
        array $batch,
        Ratings $ratingsTable,
        Resource $resourceTable,
        Closure $getDedupRecordIds
    ): int {
        $recordIds = array_column($batch, 'recordId');
        $allIds = $getDedupRecordIds($recordIds);

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
                try {
                    $resource = $resourceTable->findResource($id, 'Solr');
                    if (!$resource) {
                        continue;
                    }
                } catch (RecordMissingException $e) {
                    continue;
                }

                $targetRow = $ratingsTable->select(
                    [
                        'resource_id' => $resource->id,
                        'user_id' => $rating->user_id,
                    ]
                )->current();
                if ($targetRow) {
                    if ($targetRow->rating !== $rating->rating) {
                        ++$fixed;
                    }
                } else {
                    ++$fixed;
                    $targetRow = $ratingsTable->createRow();
                    $targetRow->user_id = $rating->user_id;
                    $targetRow->resource_id = $resource->id;
                }
                $targetRow->rating = $rating->rating;
                // Don't set creation date to indicate that this is a generated entry
                $targetRow->finna_checked = date('Y-m-d H:i:s');
                $targetRow->save();
            }
            $rating->finna_checked = date('Y-m-d H:i:s');
            $rating->save();
        }

        return $fixed;
    }

    /**
     * Verify resource ids
     *
     * @param array        $resources    Resources to verify
     * @param RecordLoader $recordLoader Record loader
     * @param Closure      $msgCallback  Progress message callback
     *
     * @return int Number of fixed resources
     */
    protected function verifyResourceIds(array $resources, RecordLoader $recordLoader, Closure $msgCallback)
    {
        $ids = [];
        foreach ($resources as $resource) {
            $ids[] = [
                'id' => $resource->record_id,
                'source' => $resource->source,
            ];
        }
        // Try to load the records. The resources for any changed records are updated automatically.
        $records = $recordLoader->loadBatch($ids, true);

        // Report results:
        $fixed = 0;
        foreach ($records as $idx => $record) {
            $resource = $resources[$idx];
            if ($record instanceof \VuFind\RecordDriver\Missing) {
                $msgCallback(
                    "Record missing for resource {$resource->id} record_id {$resource->source}:{$resource->record_id}",
                    OutputInterface::VERBOSITY_VERBOSE
                );
            }

            $id = $record->getUniqueId();
            if ($id != $resource->record_id) {
                $msgCallback("Resource {$resource->id} record_id updated from {$resource->record_id} to $id");
                ++$fixed;
            }
        }
        return $fixed;
    }
}
