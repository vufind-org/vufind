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
use VuFind\I18n\Translator\TranslatorAwareInterface;
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
class IndexReservesCommand extends AbstractSolrAndIlsCommand implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

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
                    'instructor' => $instructors[$instructorId] ?? $this->translate('no_instructor_listed'),
                    'course_id' => $courseId,
                    'course' => $courses[$courseId] ?? $this->translate('no_course_listed'),
                    'department_id' => $departmentId,
                    'department' => $departments[$departmentId] ?? $this->translate('no_department_listed'),
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
     * Print the message to the provided output stream prefixed with a timestamp.
     *
     * @param OutputInterface $output  Output object
     * @param string          $message Message to display
     *
     * @return null
     */
    protected function writeln(OutputInterface $output, string $message)
    {
        $output->writeln(date('Y-m-d H:i:s') . ' ' . $message);
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
        $this->writeln($output, 'Starting reserves processing');
        $startTime = date('Y-m-d H:i:s');
        // Check time limit; increase if necessary:
        if (ini_get('max_execution_time') < 3600) {
            ini_set('max_execution_time', '3600');
        }

        $delimiter = $input->getOption('delimiter');
        $template = $input->getOption('template');

        if ($file = $input->getOption('filename')) {
            try {
                $reader = $this->getCsvReader($file, $delimiter, $template);
                $this->writeln($output, 'Retrieving instructors');
                $instructors = $reader->getInstructors();
                $this->writeln($output, 'Found instructor count: ' . count($instructors));
                $this->writeln($output, 'Retrieving courses');
                $courses = $reader->getCourses();
                $this->writeln($output, 'Found course count: ' . count($courses));
                $this->writeln($output, 'Retrieving departments');
                $departments = $reader->getDepartments();
                $this->writeln($output, 'Found department count: ' . count($departments));
                $this->writeln($output, 'Retrieving reserves');
                $reserves = $reader->getReserves();
                $this->writeln($output, 'Found reserve count: ' . count($reserves));
            } catch (\Exception $e) {
                $this->writeln($output, $e->getMessage());
                return 1;
            }
        } elseif ($delimiter !== $this->defaultDelimiter) {
            $this->writeln($output, '-d (delimiter) is meaningless without -f (filename)');
            return 1;
        } elseif ($template !== $this->defaultTemplate) {
            $this->writeln($output, '-t (template) is meaningless without -f (filename)');
            return 1;
        } else {
            try {
                // Connect to ILS and load data:
                $this->writeln($output, 'Retrieving instructors');
                $instructors = $this->catalog->getInstructors();
                $this->writeln($output, 'Found instructor count: ' . count($instructors ?? []));
                $this->writeln($output, 'Retrieving courses');
                $courses = $this->catalog->getCourses();
                $this->writeln($output, 'Found course count: ' . count($courses ?? []));
                $this->writeln($output, 'Retrieving departments');
                $departments = $this->catalog->getDepartments();
                $this->writeln($output, 'Found department count: ' . count($departments ?? []));
                $this->writeln($output, 'Retrieving reserves');
                $reserves = $this->catalog->findReserves('', '', '');
                $this->writeln($output, 'Found reserve count: ' . count($reserves ?? []));
            } catch (\Exception $e) {
                $this->writeln($output, $e->getMessage());
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
            $this->writeln($output, 'Clearing existing reserves');
            $this->solr->deleteAll('SolrReserves');

            // Build and Save the index
            $this->writeln($output, 'Building new reserves index');
            $index = $this->buildReservesIndex(
                $instructors,
                $courses,
                $departments,
                $reserves
            );
            $this->writeln($output, 'Writing new reserves index');
            $this->solr->save('SolrReserves', $index);

            // Commit and Optimize the Solr Index
            $this->solr->commit('SolrReserves');
            $this->solr->optimize('SolrReserves');

            $this->writeln($output, 'Successfully loaded ' . count($reserves) . ' rows.');
            $endTime = date('Y-m-d H:i:s');
            $this->writeln($output, ' Stated at: ' . $startTime . ' Completed at: ' . $endTime);
            return 0;
        }
        $missing = array_merge(
            empty($instructors) ? ['instructors'] : [],
            empty($courses) ? ['courses'] : [],
            empty($departments) ? ['departments'] : [],
            empty($reserves) ? ['reserves'] : []
        );
        $this->writeln(
            $output,
            'Unable to load data. No data found for: ' . implode(', ', $missing)
        );
        return 1;
    }
}
