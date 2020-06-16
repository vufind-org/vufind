<?php
/**
 * Console service for importing record comments.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2018.
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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Exception\RecordMissing as RecordMissingException;

/**
 * Console service for importing record comments.
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class ImportComments extends AbstractUtilCommand
{
    /**
     * The name of the command (the part after "public/index.php")
     *
     * @var string
     */
    protected static $defaultName = 'util/import_comments';

    /**
     * Comments table
     *
     * @var \Finna\Db\Table\Comments
     */
    protected $commentsTable;

    /**
     * CommentsRecord table
     *
     * @var \Finna\Db\Table\CommentsRecord
     */
    protected $commentsRecordTable;

    /**
     * Resource table
     *
     * @var \Finna\Db\Table\Comments
     */
    protected $resourceTable;

    /**
     * Log file
     *
     * @var string
     */
    protected $logFile;

    /**
     * Constructor
     *
     * @param Finna\Db\Table\Comments       $comments       Comments table
     * @param Finna\Db\Table\CommentsRecord $commentsRecord CommentsRecord table
     * @param Finna\Db\Table\Resource       $resource       Resource table
     */
    public function __construct(\Finna\Db\Table\Comments $comments,
        \Finna\Db\Table\CommentsRecord $commentsRecord,
        \Finna\Db\Table\Resource $resource
    ) {
        $this->commentsTable = $comments;
        $this->commentsRecordTable = $commentsRecord;
        $this->resourceTable = $resource;
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
            ->setDescription('Import comments from a CSV file.')
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'Datasource ID in the index'
            )
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'CSV file with record id, date, comment and optional rating'
            )
            ->addArgument(
                'log',
                InputArgument::REQUIRED,
                'Log file for results'
            )
            ->addArgument(
                'defaultdate',
                InputArgument::OPTIONAL,
                'Date to use for records without a valid timestamp (default is'
                    . 'current date)'
            )
            ->addOption(
                'onlyratings',
                null,
                InputOption::VALUE_NONE,
                'The file contains only ratings'
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
        $onlyRatings = $input->getOption('onlyratings');

        $defaultDate = $input->getArgument('defaultdate');
        $defaultTimestamp = strtotime(
            date('Y-m-d', $defaultDate ? strtotime($defaultDate) : time())
        );

        $this->log("Started import of $importFile", true);
        $this->log('Default date is ' . date('Y-m-d', $defaultTimestamp), true);

        $count = 0;
        $imported = 0;

        if (($fh = fopen($importFile, 'r')) === false) {
            $this->log('Could not open import file for reading', true);
            return 1;
        }
        $idPrefix = $sourceId . '.';
        while (($data = fgetcsv($fh)) !== false) {
            ++$count;
            $num = count($data);
            if ($num < 3) {
                $this->log(
                    "Could not read CSV line $count (only $num elements found)", true
                );
                return 1;
            }
            $recordId = $data[0];
            $timestamp = $data[1] === '\N'
                ? $defaultTimestamp + $count
                : strtotime($data[1]);
            $timestampStr = date('Y-m-d H:i:s', $timestamp);
            if ($onlyRatings) {
                $commentString = '';
                $rating = $data[2] ?? null;
            } else {
                $commentString = $data[2];
                $commentString = str_replace("\r", '', $commentString);
                $commentString
                    = preg_replace('/\\\\([^\\\\])/', '\1', $commentString);
                $rating = $data[3] ?? null;
            }
            if (null !== $rating && ($rating < 0 || $rating > 5)) {
                $this->log("Invalid rating $rating on row $count", true);
                return 1;
            }
            list($recordId, $timestamp, $comment) = $data;

            if (strncmp($recordId, $idPrefix, strlen($idPrefix)) !== 0) {
                $recordId = $idPrefix . $recordId;
            }

            try {
                $resource = $this->resourceTable->findResource($recordId);
            } catch (RecordMissingException $e) {
                $this->log("Record $recordId not found");
                continue;
            }

            // Check for duplicates
            if (!$onlyRatings) {
                $comments = $this->commentsTable->getForResource($recordId);
                foreach ($comments as $comment) {
                    if ($comment->created == $timestampStr
                        && $comment->comment == $commentString
                    ) {
                        $this->log(
                            "Comment on row $count for $recordId already exists"
                        );
                        continue 2;
                    }
                }
            }

            $row = $this->commentsTable->createRow();
            $row->resource_id = $resource->id;
            $row->comment = $commentString ?? '';
            $row->created = $timestampStr;
            if (null !== $rating) {
                $row->finna_rating = $rating;
            }
            $row->save();

            $cr = $this->commentsRecordTable->createRow();
            $cr->record_id = $recordId;
            $cr->comment_id = $row->id;
            $cr->save();

            $imported++;
            $this->log("Added comment {$row->id} for record $recordId");
        }
        fclose($fh);
        $this->log(
            "Import completed with $count comments processed and $imported imported",
            true
        );

        return true;
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
        $msg = date('Y-m-d H:i:s') . " $msg";
        if (false === file_put_contents($this->logFile, "$msg\n", FILE_APPEND)) {
            die("Failed to write to log file\n");
        }
        if ($screen) {
            $this->msg($msg);
        }
    }
}
