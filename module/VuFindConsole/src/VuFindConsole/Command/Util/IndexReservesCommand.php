<?php

/**
 * Console command: index course reserves into Solr.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\Util;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Reserves\CsvReader;
use VuFindSearch\Backend\Solr\Document\UpdateDocument;
use VuFindSearch\Backend\Solr\Record\SerializableRecord;

use function count;
use function in_array;
use function ini_get;
use function sprintf;

/**
 * Console command: index course reserves into Solr.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'util/index_reserves',
    description: 'Course reserves index builder'
)]
class IndexReservesCommand extends AbstractSolrAndIlsCommand
{
    /**
     * Output interface
     *
     * @var OutputInterface
     */
    protected $output;

    /**
     * Default delimiter for reading files
     *
     * @var string
     */
    protected $defaultDelimiter = ',';

    /**
     * Default template for reading files
     *
     * @var string
     */
    protected $defaultTemplate = 'BIB_ID,COURSE,INSTRUCTOR,DEPARTMENT';

    /**
     * Keys required in the data to create a valid reserves index.
     *
     * @var string[]
     */
    protected $requiredKeys = ['INSTRUCTOR_ID', 'COURSE_ID', 'DEPARTMENT_ID'];

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setHelp(
                'This tool populates your course reserves Solr index. If run with'
                . ' no options, it will attempt to load data from your ILS.'
                . ' Switches may be used to index from delimited files instead.'
            )->addOption(
                'filename',
                'f',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'file(s) containing delimited values'
            )->addOption(
                'delimiter',
                'd',
                InputOption::VALUE_REQUIRED,
                'specifies the delimiter used in file(s)',
                $this->defaultDelimiter
            )->addOption(
                'template',
                't',
                InputOption::VALUE_REQUIRED,
                'provides a template showing where important values can be found '
                . "within the file.\nThe template is a comma-separated list of "
                . "values. Choose from:\n"
                . "BIB_ID     - bibliographic ID\n"
                . "COURSE     - course name\n"
                . "DEPARTMENT - department name\n"
                . "INSTRUCTOR - instructor name\n"
                . "SKIP       - ignore data in this position\n",
                $this->defaultTemplate
            );
    }

    /**
     * Build the reserves index from date returned by the ILS driver,
     * specifically: getInstructors, getDepartments, getCourses, findReserves
     *
     * @param array $instructors Array of instructors $instructor_id => $instructor
     * @param array $courses     Array of courses     $course_id => $course
     * @param array $departments Array of department  $dept_id => $department
     * @param array $reserves    Array of reserves records from driver's
     * findReserves.
     *
     * @return UpdateDocument
     */
    protected function buildReservesIndex(
        $instructors,
        $courses,
        $departments,
        $reserves
    ) {
        $index = [];
        foreach ($reserves as $record) {
            $requiredKeysFound
                = count(array_intersect(array_keys($record), $this->requiredKeys));
            if ($requiredKeysFound < count($this->requiredKeys)) {
                throw new \Exception(
                    implode(' and/or ', $this->requiredKeys) . ' fields ' .
                    'not present in reserve records. Please update ILS driver.'
                );
            }
            $instructorId = $record['INSTRUCTOR_ID'];
            $courseId = $record['COURSE_ID'];
            $departmentId = $record['DEPARTMENT_ID'];
            $id = $courseId . '|' . $instructorId . '|' . $departmentId;

            if (!isset($index[$id])) {
                $index[$id] = [
                    'id' => $id,
                    'bib_id' => [],
                    'instructor_id' => $instructorId,
                    'instructor' => $instructors[$instructorId] ?? 'no_instructor_listed',
                    'course_id' => $courseId,
                    'course' => $courses[$courseId] ?? 'no_course_listed',
                    'department_id' => $departmentId,
                    'department' => $departments[$departmentId] ?? 'no_department_listed',
                ];
            }
            if (!in_array($record['BIB_ID'], $index[$id]['bib_id'])) {
                $index[$id]['bib_id'][] = $record['BIB_ID'];
            }

            // Show a warning if the any of the IDs were set, but was not found in the resulting data
            if (!empty($instructorId) && !isset($instructors[$instructorId])) {
                $this->showTimestampedMessage(
                    sprintf(
                        'WARNING! The instructor (ID: %s) for the course: %s (ID: %s) ' .
                        'and department: %s (ID: %s) did not match any found instructors.',
                        $index[$id]['instructor_id'],
                        $index[$id]['course'],
                        $index[$id]['course_id'],
                        $index[$id]['department'],
                        $index[$id]['department_id']
                    )
                );
            }
            if (!empty($departmentId) && !isset($departments[$departmentId])) {
                $this->showTimestampedMessage(
                    sprintf(
                        'WARNING! The department (ID: %s) for the course: %s (ID: %s) ' .
                        'and instructor: %s (ID: %s) did not match any found departments.',
                        $index[$id]['department_id'],
                        $index[$id]['course'],
                        $index[$id]['course_id'],
                        $index[$id]['instructor'],
                        $index[$id]['instructor_id']
                    )
                );
            }
            if (!empty($courseId) && !isset($courses[$courseId])) {
                $this->showTimestampedMessage(
                    sprintf(
                        'WARNING! The course (ID: %s) for the instructor: %s (ID: %s) ' .
                        'and department: %s (ID: %s) did not match any found courses.',
                        $index[$id]['course_id'],
                        $index[$id]['instructor'],
                        $index[$id]['instructor_id'],
                        $index[$id]['department'],
                        $index[$id]['department_id']
                    )
                );
            }
        }

        $updates = new UpdateDocument();
        foreach ($index as $data) {
            if (!empty($data['bib_id'])) {
                $updates->addRecord(new SerializableRecord($data));
            }
        }
        return $updates;
    }

    /**
     * Construct a CSV reader.
     *
     * @param array|string $files     Array of files to load (or single filename).
     * @param string       $delimiter Delimiter used by file(s).
     * @param string       $template  Template showing field positions within
     * file(s). Comma-separated list containing BIB_ID, INSTRUCTOR, COURSE,
     * DEPARTMENT and/or SKIP. Default = BIB_ID,COURSE,INSTRUCTOR,DEPARTMENT
     *
     * @return CsvReader
     */
    protected function getCsvReader(
        $files,
        string $delimiter,
        string $template
    ): CsvReader {
        return new CsvReader($files, $delimiter, $template);
    }

    /**
     * Print the message to the provided output stream prefixed with a timestamp.
     *
     * @param string $message Message to display
     *
     * @return null
     */
    protected function showTimestampedMessage(string $message)
    {
        $this->output->writeln(date('Y-m-d H:i:s') . ' ' . $message);
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
        $startTime = date('Y-m-d H:i:s');
        // Check time limit; increase if necessary:
        if (ini_get('max_execution_time') < 3600) {
            ini_set('max_execution_time', '3600');
        }

        $delimiter = $input->getOption('delimiter');
        $template = $input->getOption('template');

        if ($file = $input->getOption('filename')) {
            try {
                $this->showTimestampedMessage('Starting reserves processing from file');

                $reader = $this->getCsvReader($file, $delimiter, $template);
                $this->showTimestampedMessage('Retrieving instructors');
                $instructors = $reader->getInstructors();
                $this->showTimestampedMessage('Found instructor count: ' . count($instructors));
                $this->showTimestampedMessage('Retrieving courses');
                $courses = $reader->getCourses();
                $this->showTimestampedMessage('Found course count: ' . count($courses));
                $this->showTimestampedMessage('Retrieving departments');
                $departments = $reader->getDepartments();
                $this->showTimestampedMessage('Found department count: ' . count($departments));
                $this->showTimestampedMessage('Retrieving reserves');
                $reserves = $reader->getReserves();
                $this->showTimestampedMessage('Found reserve count: ' . count($reserves));
            } catch (\Exception $e) {
                $this->showTimestampedMessage($e->getMessage());
                return 1;
            }
        } elseif ($delimiter !== $this->defaultDelimiter) {
            $this->output->writeln('-d (delimiter) is meaningless without -f (filename)');
            return 1;
        } elseif ($template !== $this->defaultTemplate) {
            $this->output->writeln('-t (template) is meaningless without -f (filename)');
            return 1;
        } else {
            try {
                $this->showTimestampedMessage('Starting reserves processing from ILS');

                // Connect to ILS and load data:
                $this->showTimestampedMessage('Retrieving instructors');
                $instructors = $this->catalog->getInstructors();
                $this->showTimestampedMessage('Found instructor count: ' . count($instructors ?? []));
                $this->showTimestampedMessage('Retrieving courses');
                $courses = $this->catalog->getCourses();
                $this->showTimestampedMessage('Found course count: ' . count($courses ?? []));
                $this->showTimestampedMessage('Retrieving departments');
                $departments = $this->catalog->getDepartments();
                $this->showTimestampedMessage('Found department count: ' . count($departments ?? []));
                $this->showTimestampedMessage('Retrieving reserves');
                $reserves = $this->catalog->findReserves('', '', '');
                $this->showTimestampedMessage('Found reserve count: ' . count($reserves ?? []));
            } catch (\Exception $e) {
                $this->showTimestampedMessage($e->getMessage());
                return 1;
            }
        }

        // Make sure we have reserves and at least one of: instructors, courses,
        // departments:
        if (
            (!empty($instructors) || !empty($courses) || !empty($departments))
            && !empty($reserves)
        ) {
            // Delete existing records
            $this->showTimestampedMessage('Clearing existing reserves');
            $this->solr->deleteAll('SolrReserves');

            // Build and Save the index
            $this->showTimestampedMessage('Building new reserves index');
            $index = $this->buildReservesIndex(
                $instructors,
                $courses,
                $departments,
                $reserves
            );
            $this->showTimestampedMessage('Writing new reserves index');
            $this->solr->save('SolrReserves', $index);

            // Commit and Optimize the Solr Index
            $this->solr->commit('SolrReserves');
            $this->solr->optimize('SolrReserves');

            $this->showTimestampedMessage('Successfully loaded ' . count($reserves) . ' rows.');
            $endTime = date('Y-m-d H:i:s');
            $this->showTimestampedMessage('Started at: ' . $startTime . ' Completed at: ' . $endTime);
            return 0;
        }
        $missing = array_merge(
            empty($instructors) ? ['instructors'] : [],
            empty($courses) ? ['courses'] : [],
            empty($departments) ? ['departments'] : [],
            empty($reserves) ? ['reserves'] : []
        );
        $this->showTimestampedMessage('Unable to load data. No data found for: ' . implode(', ', $missing));
        return 1;
    }
}
