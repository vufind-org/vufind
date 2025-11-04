<?php

/**
 * Console service for importing record comments.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2018-2024.
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
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

namespace FinnaConsole\Command\Util;

use DateTime;
use Finna\Db\Service\FinnaCommentsServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Db\Service\RatingsServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\ResourcePopulator;
use VuFind\Search\SearchRunner;

use function count;

/**
 * Console service for importing record comments.
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
#[AsCommand(
    name: 'util/import_comments'
)]
class ImportComments extends AbstractUtilCommand
{
    /**
     * Log file
     *
     * @var string
     */
    protected $logFile;

    /**
     * Whether to output verbose messages
     *
     * @var bool
     */
    protected $verbose = false;

    /**
     * Constructor
     *
     * @param UserServiceInterface          $userService       User database service
     * @param FinnaCommentsServiceInterface $commentsService   Comments database service
     * @param RatingsServiceInterface       $ratingsService    Ratings database service
     * @param ResourcePopulator             $resourcePopulator Resource populator
     * @param RecordLoader                  $recordLoader      Record loader
     * @param SearchRunner                  $searchRunner      Search runner
     */
    public function __construct(
        protected UserServiceInterface $userService,
        protected FinnaCommentsServiceInterface $commentsService,
        protected RatingsServiceInterface $ratingsService,
        protected ResourcePopulator $resourcePopulator,
        protected RecordLoader $recordLoader,
        protected SearchRunner $searchRunner
    ) {
        parent::__construct();
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setDescription('Import comments and/or ratings from a CSV file.')
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'Datasource ID in the index'
            )
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'CSV file with record id, date, comment and/or rating'
            )
            ->addArgument(
                'log',
                InputArgument::REQUIRED,
                'Log file for results'
            )
            ->addOption(
                'default-date',
                null,
                InputOption::VALUE_REQUIRED,
                'Date to use for records without a valid timestamp (default is current date)'
            )
            ->addOption(
                'user-id',
                null,
                InputOption::VALUE_REQUIRED,
                'User id (id column in database) to associate with the comments (default is none)'
            )
            ->addOption(
                'separator',
                null,
                InputOption::VALUE_REQUIRED,
                'Separator character (default is ,)'
            )
            ->addOption(
                'enclosure',
                null,
                InputOption::VALUE_REQUIRED,
                'Enclosure character (default is ")'
            )
            ->addOption(
                'escape',
                null,
                InputOption::VALUE_REQUIRED,
                'Escape character (default is \\)'
            )
            ->addOption(
                'id-fields',
                null,
                InputOption::VALUE_REQUIRED,
                'A comma-separator list of index fields to use to search for records (default is only id)'
            )
            ->addOption(
                'rating-multiplier',
                null,
                InputOption::VALUE_REQUIRED,
                'Rating multiplier to result in range 0-100'
            )
            ->addOption(
                'id-column',
                null,
                InputOption::VALUE_REQUIRED,
                'ID column number (default is 1)'
            )
            ->addOption(
                'date-column',
                null,
                InputOption::VALUE_REQUIRED,
                'Date column number (default is 2, set to 0 to disable)'
            )
            ->addOption(
                'comment-column',
                null,
                InputOption::VALUE_REQUIRED,
                'Comment column number (default is 3, set to 0 to disable)'
            )
            ->addOption(
                'rating-column',
                null,
                InputOption::VALUE_REQUIRED,
                'Rating column number (default is 4, set to 0 to disable)'
            )
            ->addOption(
                'comment-filter',
                null,
                InputOption::VALUE_REQUIRED,
                'Regular expression used to filter comments. Any filter matching an expression is ignored.'
                . ' Example: --comment-filter="/([^\p{Latin}\pC\pN\pP\pS\pZ]+)/u"'
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
        $sourceId = $input->getArgument('source');
        $importFile = $input->getArgument('file');
        $this->logFile = $input->getArgument('log');
        $defaultDate = $input->getOption('default-date');
        $userId = $input->getOption('user-id');
        $idFields = explode(',', $input->getOption('id-fields') ?? 'id');
        $ratingMultiplier = (float)($input->getOption('rating-multiplier') ?? 1);
        $separator = $input->getOption('separator') ?? ',';
        $enclosure = $input->getOption('enclosure') ?? '"';
        $escape = $input->getOption('escape') ?? '\\';
        $idColumn = (int)($input->getOption('id-column') ?? 1);
        $dateColumn = (int)($input->getOption('date-column') ?? 2);
        $commentColumn = (int)($input->getOption('comment-column') ?? 3);
        $ratingColumn = (int)($input->getOption('rating-column') ?? 4);
        $commentFilter = $input->getOption('comment-filter');
        $this->verbose = $output->isVerbose();

        if (!$idColumn || (!$commentColumn && !$ratingColumn)) {
            $this->log('ID column and at least one of comment or rating columns is required', true);
            return 1;
        }

        $defaultTimestamp = strtotime(
            date('Y-m-d', $defaultDate ? strtotime($defaultDate) : time())
        );
        $user = $userId ? $this->userService->getUserById($userId) : null;

        $this->log("Started import of $importFile", true);
        $this->log('Default date is ' . date('Y-m-d', $defaultTimestamp), true);

        $count = 0;
        $commentCount = 0;
        $ratingCount = 0;

        if (($fh = fopen($importFile, 'r')) === false) {
            $this->log('Could not open import file for reading', true);
            return 1;
        }
        while (($data = fgetcsv($fh, null, $separator, $enclosure, $escape)) !== false) {
            ++$count;
            $num = count($data);
            if ($num < 2) {
                $this->log(
                    "Could not read CSV line $count (only $num elements found):" . var_export($data, true),
                    true
                );
                continue;
            }
            // Prepend an element to align column indexes with data:
            array_unshift($data, false);
            if (null === $id = $data[$idColumn] ?? null) {
                $this->log(
                    "Could not read CSV line $count (id column found)",
                    true
                );
                continue;
            }

            if ($dateColumn) {
                if (!isset($data[$dateColumn])) {
                    $this->log(
                        "Could not read CSV line $count (date column found)",
                        true
                    );
                    continue;
                }
                $timestamp = $data[$dateColumn] === '\N'
                    ? $defaultTimestamp + $count
                    : strtotime($data[$dateColumn]);
            } else {
                $timestamp = $defaultTimestamp;
            }
            $timestampStr = date('Y-m-d H:i:s', $timestamp);
            $timestampDateTime = DateTime::createFromFormat('U', $timestamp);
            if ($ratingColumn) {
                if (!isset($data[$ratingColumn])) {
                    $this->log(
                        "Could not read CSV line $count (rating column found)",
                        true
                    );
                    continue;
                }
                $rating = $data[$ratingColumn];
            } else {
                $rating = null;
            }
            if (!$commentColumn) {
                $commentString = null;
            } else {
                if (!isset($data[$commentColumn])) {
                    $this->log(
                        "Could not read CSV line $count (comment column found)",
                        true
                    );
                    continue;
                }
                $commentString = $data[$commentColumn];
                $commentString = preg_replace('/\\\\([^\\\\])/', '\1', $commentString);
            }
            if (null !== $rating) {
                $rating = round($ratingMultiplier * (float)$rating);
                if ($rating < 0 || $rating > 100) {
                    $this->log("Invalid rating '$rating' on row $count", true);
                    continue;
                }
                if ($rating < 10) {
                    // Minimum is a half star
                    $rating = 10;
                }
            }

            if (!($driver = $this->findRecord($sourceId, $id, $idFields))) {
                $this->log("Identifier $id not found (row $count)");
                continue;
            }
            $recordId = $driver->getUniqueID();
            $resource = $this->resourcePopulator->getOrCreateResourceForRecordId($recordId, $sourceId);

            $skip = false;
            if ($commentString) {
                // Check filter
                if ($commentFilter && preg_match($commentFilter, $commentString, $matches)) {
                    $skip = true;
                    $logMatch = isset($matches[1])
                        ? (' (match: ' . $matches[1] . ' hex: ' . bin2hex($matches[1]) . ')')
                        : '';
                    $this->log("Comment on row $count for $recordId filtered out$logMatch");
                }

                // Check for duplicates
                if (!$skip) {
                    foreach ($this->commentsService->getRecordComments($recordId, $sourceId) as $comment) {
                        if (
                            $comment->getCreated()->format('Y-m-d H:i:s') === $timestampStr
                            && $comment->getComment() === $commentString
                        ) {
                            $this->log("Comment on row $count for $recordId already exists");
                            $skip = true;
                            break;
                        }
                    }
                }

                if (!$skip) {
                    $newComment = $this->commentsService->createEntity()
                        ->setUser($user)
                        ->setComment($commentString)
                        ->setResource($resource)
                        ->setCreated($timestampDateTime);
                    $this->commentsService->persistEntity($newComment);
                    $this->commentsService->addRecordLinks($newComment, [$recordId]);
                    $this->log("Added comment {$newComment->getId()} for record $recordId (row $count)");
                    ++$commentCount;
                }
            }
            if ($rating && !$skip) {
                $newRating = $this->ratingsService->createEntity()
                    ->setResource($resource)
                    ->setCreated($timestampDateTime)
                    ->setRating($rating);
                $this->ratingsService->persistEntity($newRating);
                $this->log("Added rating {$newRating->getId()} for record $recordId (row $count)");
                ++$ratingCount;
            }
            if ($count % 1000 === 0) {
                $this->log("$count rows processed", true);
            }
        }
        fclose($fh);
        $this->log(
            "Import completed with $count rows processed; $commentCount comments and $ratingCount ratings imported",
            true
        );

        return 0;
    }

    /**
     * Find a record
     *
     * @param string $sourceId Source ID
     * @param string $id       Raw ID
     * @param array  $idFields Fields to search
     *
     * @return ?\VuFind\RecordDriver\AbstractBase Record driver or null if not found
     */
    protected function findRecord(
        string $sourceId,
        string $id,
        array $idFields
    ): ?\VuFind\RecordDriver\AbstractBase {
        $recordId = $id;
        $idPrefix = $sourceId . '.';
        if (!str_starts_with($recordId, $idPrefix)) {
            $recordId = $idPrefix . $recordId;
        }

        foreach ($idFields as $field) {
            if ('id' === $field) {
                $driver = $this->recordLoader->load($recordId, DEFAULT_SEARCH_BACKEND, true);
                if (!($driver instanceof \VuFind\RecordDriver\Missing)) {
                    return $driver;
                }
            } else {
                $searchId = $id;
                if ('isbn' === $field) {
                    // Convert to ISBN-13:
                    $isbnObj = new \VuFindCode\ISBN($id);
                    $searchId = $isbnObj->get13();
                }

                $request = [
                    'lookfor' => "$field:\"" . addcslashes($searchId, '"') . '"',
                    'filter' => [
                        'source_str_mv:"' . addcslashes($sourceId, '"') . '"',
                        'finna.deduplication:0',
                    ],
                ];
                $results = $this->searchRunner->run($request);
                if ($results->getResultTotal() > 0) {
                    return $results->getResults()[0];
                }
            }
        }
        return null;
    }

    /**
     * Write a log message
     *
     * @param string $msg    Message
     * @param bool   $screen Whether to output the message on screen too
     *
     * @return void
     */
    protected function log($msg, $screen = false)
    {
        if ($this->logFile) {
            if (false === file_put_contents($this->logFile, date('Y-m-d H:i:s') . " $msg\n", FILE_APPEND)) {
                die("Failed to write to log file\n");
            }
        }
        if ($screen || $this->verbose) {
            $this->msg($msg);
        }
    }
}
