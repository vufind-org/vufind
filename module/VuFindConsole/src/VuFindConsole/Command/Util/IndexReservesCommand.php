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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
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
                    'instructor' => $instructors[$instructorId] ?? '',
                    'course_id' => $courseId,
                    'course' => $courses[$courseId] ?? '',
                    'department_id' => $departmentId,
                    'department' => $departments[$departmentId] ?? '',
                ];
            }
            if (!in_array($record['BIB_ID'], $index[$id]['bib_id'])) {
                $index[$id]['bib_id'][] = $record['BIB_ID'];
            }
        }

        $updates = new UpdateDocument();
        foreach ($index as $id => $data) {
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
     * Run the command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Check time limit; increase if necessary:
        if (ini_get('max_execution_time') < 3600) {
            ini_set('max_execution_time', '3600');
        }

        $delimiter = $input->getOption('delimiter');
        $template = $input->getOption('template');

        if ($file = $input->getOption('filename')) {
            try {
                $reader = $this->getCsvReader($file, $delimiter, $template);
                $instructors = $reader->getInstructors();
                $courses = $reader->getCourses();
                $departments = $reader->getDepartments();
                $reserves = $reader->getReserves();
            } catch (\Exception $e) {
                $output->writeln($e->getMessage());
                return 1;
            }
        } elseif ($delimiter !== $this->defaultDelimiter) {
            $output->writeln('-d (delimiter) is meaningless without -f (filename)');
            return 1;
        } elseif ($template !== $this->defaultTemplate) {
            $output->writeln('-t (template) is meaningless without -f (filename)');
            return 1;
        } else {
            try {
                // Connect to ILS and load data:
                $instructors = $this->catalog->getInstructors();
                $courses = $this->catalog->getCourses();
                $departments = $this->catalog->getDepartments();
                $reserves = $this->catalog->findReserves('', '', '');
            } catch (\Exception $e) {
                $output->writeln($e->getMessage());
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
            $this->solr->deleteAll('SolrReserves');

            // Build and Save the index
            $index = $this->buildReservesIndex(
                $instructors,
                $courses,
                $departments,
                $reserves
            );
            $this->solr->save('SolrReserves', $index);

            // Commit and Optimize the Solr Index
            $this->solr->commit('SolrReserves');
            $this->solr->optimize('SolrReserves');

            $output->writeln('Successfully loaded ' . count($reserves) . ' rows.');
            return 0;
        }
        $missing = array_merge(
            empty($instructors) ? ['instructors'] : [],
            empty($courses) ? ['courses'] : [],
            empty($departments) ? ['departments'] : [],
            empty($reserves) ? ['reserves'] : []
        );
        $output->writeln(
            'Unable to load data. No data found for: ' . implode(', ', $missing)
        );
        return 1;
    }
}
