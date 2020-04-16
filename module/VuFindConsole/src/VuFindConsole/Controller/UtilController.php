<?php
/**
 * CLI Controller Module
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFindConsole\Controller;

use File_MARC;
use File_MARCXML;
use Laminas\Console\Console;
use VuFindSearch\Backend\Solr\Document\UpdateDocument;
use VuFindSearch\Backend\Solr\Record\SerializableRecord;

/**
 * This controller handles various command-line tools
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class UtilController extends AbstractBase
{
    /**
     * Display help for the index reserves action.
     *
     * @param string $msg Extra message to display
     *
     * @return \Laminas\Console\Response
     */
    protected function indexReservesHelp($msg = '')
    {
        if (!empty($msg)) {
            foreach (explode("\n", $msg) as $line) {
                Console::writeLine($line);
            }
            Console::writeLine('');
        }

        Console::writeLine('Course reserves index builder');
        Console::writeLine('');
        Console::writeLine(
            'If run with no options, this will attempt to load data from your ILS.'
        );
        Console::writeLine('');
        Console::writeLine(
            'Switches may be used to index from delimited files instead:'
        );
        Console::writeLine('');
        Console::writeLine(
            ' -f [filename] loads a file (may be repeated for multiple files)'
        );
        Console::writeLine(
            ' -d [delimiter] specifies a delimiter (comma is default)'
        );
        Console::writeLine(
            ' -t [template] provides a template showing where important values'
        );
        Console::writeLine(
            '     can be found within the file.  The template is a comma-'
        );
        Console::writeLine('     separated list of values.  Choose from:');
        Console::writeLine('          BIB_ID     - bibliographic ID');
        Console::writeLine('          COURSE     - course name');
        Console::writeLine('          DEPARTMENT - department name');
        Console::writeLine('          INSTRUCTOR - instructor name');
        Console::writeLine('          SKIP       - ignore data in this position');
        Console::writeLine(
            '     Default template is BIB_ID,COURSE,INSTRUCTOR,DEPARTMENT'
        );
        Console::writeLine(' -h or --help display this help information.');

        return $this->getFailureResponse();
    }

    /**
     * Build the Reserves index.
     *
     * @return \Laminas\Console\Response
     */
    public function indexreservesAction()
    {
        ini_set('memory_limit', '50M');
        ini_set('max_execution_time', '3600');

        $request = $this->getRequest();

        if ($request->getParam('h') || $request->getParam('help')) {
            return $this->indexReservesHelp();
        } elseif ($file = $request->getParam('f')) {
            try {
                $delimiter = $request->getParam('d', ',');
                $template = $request->getParam('t');
                $reader = new \VuFind\Reserves\CsvReader(
                    $file, $delimiter, $template
                );
                $instructors = $reader->getInstructors();
                $courses = $reader->getCourses();
                $departments = $reader->getDepartments();
                $reserves = $reader->getReserves();
            } catch (\Exception $e) {
                return $this->indexReservesHelp($e->getMessage());
            }
        } elseif ($request->getParam('d')) {
            return $this->indexReservesHelp('-d is meaningless without -f');
        } elseif ($request->getParam('t')) {
            return $this->indexReservesHelp('-t is meaningless without -f');
        } else {
            try {
                // Connect to ILS and load data:
                $catalog = $this->getILS();
                $instructors = $catalog->getInstructors();
                $courses = $catalog->getCourses();
                $departments = $catalog->getDepartments();
                $reserves = $catalog->findReserves('', '', '');
            } catch (\Exception $e) {
                return $this->indexReservesHelp($e->getMessage());
            }
        }

        // Make sure we have reserves and at least one of: instructors, courses,
        // departments:
        if ((!empty($instructors) || !empty($courses) || !empty($departments))
            && !empty($reserves)
        ) {
            // Setup Solr Connection
            $solr = $this->serviceLocator->get(\VuFind\Solr\Writer::class);

            // Delete existing records
            $solr->deleteAll('SolrReserves');

            // Build and Save the index
            $index = $this->buildReservesIndex(
                $instructors, $courses, $departments, $reserves
            );
            $solr->save('SolrReserves', $index);

            // Commit and Optimize the Solr Index
            $solr->commit('SolrReserves');
            $solr->optimize('SolrReserves');

            Console::writeLine('Successfully loaded ' . count($reserves) . ' rows.');
            return $this->getSuccessResponse();
        }
        return $this->indexReservesHelp('Unable to load data.');
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
    protected function buildReservesIndex($instructors, $courses, $departments,
        $reserves
    ) {
        $requiredKeys = ['INSTRUCTOR_ID', 'COURSE_ID', 'DEPARTMENT_ID'];
        foreach ($reserves as $record) {
            $requiredKeysFound
                = count(array_intersect(array_keys($record), $requiredKeys));
            if ($requiredKeysFound < count($requiredKeys)) {
                throw new \Exception(
                    implode(' and/or ', $requiredKeys) . ' fields ' .
                    'not present in reserve records. Please update ILS driver.'
                );
            }
            $instructor_id = $record['INSTRUCTOR_ID'];
            $course_id = $record['COURSE_ID'];
            $department_id = $record['DEPARTMENT_ID'];
            $id = $course_id . '|' . $instructor_id . '|' . $department_id;

            if (!isset($index[$id])) {
                $index[$id] = [
                    'id' => $id,
                    'bib_id' => [],
                    'instructor_id' => $instructor_id,
                    'instructor' => $instructors[$instructor_id] ?? '',
                    'course_id' => $course_id,
                    'course' => $courses[$course_id] ?? '',
                    'department_id' => $department_id,
                    'department' => $departments[$department_id] ?? ''
                ];
            }
            $index[$id]['bib_id'][] = $record['BIB_ID'];
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
     * Command-line tool to delete suppressed records from the index.
     *
     * @return \Laminas\Console\Response
     */
    public function suppressedAction()
    {
        $request = $this->getRequest();
        if ($request->getParam('help') || $request->getParam('h')) {
            Console::writeLine('Available switches:');
            Console::writeLine(
                '--authorities =>'
                . ' Delete authority records instead of bibliographic records'
            );
            Console::writeLine('--help or -h => Show this message');
            Console::writeLine(
                '--outfile=[/path/to/file] => Write the ID list to the specified'
                . ' file instead of updating Solr (optional)'
            );
            return $this->getFailureResponse();
        }

        // Setup Solr Connection
        $backend = $request->getParam('authorities') ? 'SolrAuth' : 'Solr';

        // Make ILS Connection
        try {
            $catalog = $this->getILS();
            $result = ($backend == 'SolrAuth')
                ? $catalog->getSuppressedAuthorityRecords()
                : $catalog->getSuppressedRecords();
        } catch (\Exception $e) {
            Console::writeLine("ILS error -- " . $e->getMessage());
            return $this->getFailureResponse();
        }

        // Validate result:
        if (!is_array($result)) {
            Console::writeLine("Could not obtain suppressed record list from ILS.");
            return $this->getFailureResponse();
        } elseif (empty($result)) {
            Console::writeLine("No suppressed records to delete.");
            return $this->getSuccessResponse();
        }

        // If 'outfile' set, write the list
        if ($file = $request->getParam('outfile')) {
            if (!file_put_contents($file, implode("\n", $result))) {
                Console::writeLine("Problem writing to $file");
                return $this->getFailureResponse();
            }
        } else {
            // Default behavior: Get Suppressed Records and Delete from index
            $solr = $this->serviceLocator->get(\VuFind\Solr\Writer::class);
            $solr->deleteRecords($backend, $result);
            $solr->commit($backend);
            $solr->optimize($backend);
        }
        return $this->getSuccessResponse();
    }

    /**
     * Tool to auto-fill hierarchy cache.
     *
     * @return \Laminas\Console\Response
     */
    public function createhierarchytreesAction()
    {
        $request = $this->getRequest();
        if ($request->getParam('help') || $request->getParam('h')) {
            $scriptName = $this->getRequest()->getScriptName();
            if (substr($scriptName, -9) === 'index.php') {
                $scriptName .= ' util createHierarchyTrees';
            }
            Console::writeLine(
                'Usage: ' . $scriptName
                . ' [<backend>] [--skip-xml or -sx] [--skip-json or -sj]'
                . ' [--help or -h]'
            );
            Console::writeLine(
                "\t<backend> => Search backend, e.g. " . DEFAULT_SEARCH_BACKEND
                . " (default) or Search2"
            );
            Console::writeLine("\t--skip-xml or -sx => Skip the XML cache");
            Console::writeLine("\t--skip-json or -sj => Skip the JSON cache");
            Console::writeLine("\t--help or -h => Show this message");
            return $this->getFailureResponse();
        }
        $skipJson = $request->getParam('skip-json') || $request->getParam('sj');
        $skipXml = $request->getParam('skip-xml') || $request->getParam('sx');
        $backendId = $request->getParam('backend') ?? DEFAULT_SEARCH_BACKEND;
        $recordLoader = $this->serviceLocator->get(\VuFind\Record\Loader::class);
        $hierarchies = $this->serviceLocator
            ->get(\VuFind\Search\Results\PluginManager::class)->get($backendId)
            ->getFullFieldFacets(['hierarchy_top_id']);
        if (!isset($hierarchies['hierarchy_top_id']['data']['list'])) {
            $hierarchies['hierarchy_top_id']['data']['list'] = [];
        }
        foreach ($hierarchies['hierarchy_top_id']['data']['list'] as $hierarchy) {
            $recordid = $hierarchy['value'];
            $count = $hierarchy['count'];
            if (empty($recordid)) {
                continue;
            }
            Console::writeLine(
                "\tBuilding tree for " . $recordid . '... '
                . number_format($count) . ' records'
            );
            try {
                $driver = $recordLoader->load($recordid, $backendId);
                // Only do this if the record is actually a hierarchy type record
                if ($driver->getHierarchyType()) {
                    // JSON
                    if (!$skipJson) {
                        Console::writeLine("\t\tJSON cache...");
                        $driver->getHierarchyDriver()->getTreeSource()->getJSON(
                            $recordid, ['refresh' => true]
                        );
                    } else {
                        Console::writeLine("\t\tJSON skipped.");
                    }
                    // XML
                    if (!$skipXml) {
                        Console::writeLine("\t\tXML cache...");
                        $driver->getHierarchyDriver()->getTreeSource()->getXML(
                            $recordid, ['refresh' => true]
                        );
                    } else {
                        Console::writeLine("\t\tXML skipped.");
                    }
                }
            } catch (\VuFind\Exception\RecordMissing $e) {
                Console::writeLine(
                    'WARNING! - Caught exception: ' . $e->getMessage() . "\n"
                );
            }
        }
        Console::writeLine(
            count($hierarchies['hierarchy_top_id']['data']['list']) . ' files'
        );

        return $this->getSuccessResponse();
    }
}
