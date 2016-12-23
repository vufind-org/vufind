<?php
/**
 * CLI Controller Module
 *
 * PHP version 5
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
use File_MARC, File_MARCXML, VuFind\Sitemap\Generator as Sitemap;
use VuFind\Config\Locator as ConfigLocator;
use VuFind\Config\Writer as ConfigWriter;
use VuFindSearch\Backend\Solr\Document\UpdateDocument;
use VuFindSearch\Backend\Solr\Record\SerializableRecord;
use Zend\Console\Console;
use Zend\Crypt\Symmetric\Mcrypt,
    Zend\Crypt\BlockCipher as BlockCipher;

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
     * @return \Zend\Console\Response
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
     * @return \Zend\Console\Response
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
            $solr = $this->getServiceLocator()->get('VuFind\Solr\Writer');

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
        foreach ($reserves as $record) {
            if (!isset($record['INSTRUCTOR_ID']) || !isset($record['COURSE_ID'])
                || !isset($record['DEPARTMENT_ID'])
            ) {
                throw new \Exception(
                    'INSTRUCTOR_ID and/or COURSE_ID and/or DEPARTMENT_ID fields ' .
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
                    'instructor' => isset($instructors[$instructor_id])
                        ? $instructors[$instructor_id] : '',
                    'course_id' => $course_id,
                    'course' => isset($courses[$course_id])
                        ? $courses[$course_id] : '',
                    'department_id' => $department_id,
                    'department' => isset($departments[$department_id])
                        ? $departments[$department_id] : ''
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
     * Commit the Solr index.
     *
     * @return \Zend\Console\Response
     */
    public function commitAction()
    {
        return $this->performCommit();
    }

    /**
     * Optimize the Solr index.
     *
     * @return \Zend\Console\Response
     */
    public function optimizeAction()
    {
        return $this->performCommit(true);
    }

    /**
     * Commit (and possibly optimize) the Solr index.
     *
     * @param bool $optimize Should we optimize?
     *
     * @return \Zend\Console\Response
     */
    protected function performCommit($optimize = false)
    {
        ini_set('memory_limit', '50M');
        ini_set('max_execution_time', '3600');

        // Setup Solr Connection -- Allow core to be specified from command line.
        $core = $this->getRequest()->getParam('core', 'Solr');

        // Commit and Optimize the Solr Index
        $solr = $this->getServiceLocator()->get('VuFind\Solr\Writer');
        $solr->commit($core);
        if ($optimize) {
            $solr->optimize($core);
        }
        return $this->getSuccessResponse();
    }

    /**
     * Generate a Sitemap
     *
     * @return \Zend\Console\Response
     */
    public function sitemapAction()
    {
        // Build sitemap and display appropriate warnings if needed:
        $configLoader = $this->getServiceLocator()->get('VuFind\Config');
        $generator = new Sitemap(
            $this->getServiceLocator()->get('VuFind\Search\BackendManager'),
            $configLoader->get('config')->Site->url, $configLoader->get('sitemap')
        );
        $generator->generate();
        foreach ($generator->getWarnings() as $warning) {
            Console::writeLine("$warning");
        }
        return $this->getSuccessResponse();
    }

    /**
     * Command-line tool to batch-delete records from the Solr index.
     *
     * @return \Zend\Console\Response
     */
    public function deletesAction()
    {
        // Parse the command line parameters -- check verbosity, see if we are in
        // "flat file" mode, find out what file we are reading in, and determine
        // the index we are affecting!
        $request = $this->getRequest();
        $verbose = $request->getParam('verbose');
        $filename = $request->getParam('filename');
        $mode = $request->getParam('format', 'marc');
        $index = $request->getParam('index', 'Solr');

        // No filename specified?  Give usage guidelines:
        if (empty($filename)) {
            $scriptName = $this->getRequest()->getScriptName();
            if (substr($scriptName, -9) === 'index.php') {
                $scriptName .= ' util deletes';
            }
            Console::writeLine("Delete records from VuFind's index.");
            Console::writeLine('');
            Console::writeLine(
                'Usage: ' . $scriptName . ' [--verbose] FILENAME FORMAT INDEX'
            );
            Console::writeLine('');
            Console::writeLine(
                'The optional --verbose switch turns on detailed feedback.'
            );
            Console::writeLine(
                'FILENAME is the file containing records to delete.'
            );
            Console::writeLine(
                'FORMAT is the format of the file -- '
                . 'it may be one of the following:'
            );
            Console::writeLine(
                "\tflat - flat text format "
                . '(deletes all IDs in newline-delimited file)'
            );
            Console::writeLine(
                "\tmarc - binary MARC format (delete all record IDs from 001 fields)"
            );
            Console::writeLine(
                "\tmarcxml - MARC-XML format (delete all record IDs from 001 fields)"
            );
            Console::writeLine(
                '"marc" is used by default if no format is specified.'
            );
            Console::writeLine('INDEX is the index to use (default = Solr)');
            return $this->getFailureResponse();
        }

        // File doesn't exist?
        if (!file_exists($filename)) {
            Console::writeLine("Cannot find file: {$filename}");
            return $this->getFailureResponse();
        }

        // Build list of records to delete:
        $ids = [];

        // Flat file mode:
        if ($verbose) {
            Console::writeLine("Loading IDs in {$mode} mode.");
        }
        if ($mode == 'flat') {
            foreach (explode("\n", file_get_contents($filename)) as $id) {
                $id = trim($id);
                if (!empty($id)) {
                    $ids[] = $id;
                }
            }
        } else {
            // MARC file mode...  We need to load the MARC record differently if it's
            // XML or binary:
            $collection = ($mode == 'marcxml')
                ? new File_MARCXML($filename) : new File_MARC($filename);

            // Once the records are loaded, the rest of the logic is always the same:
            $missingIdCount = 0;
            while ($record = $collection->next()) {
                $idField = $record->getField('001');
                if ($idField) {
                    $ids[] = (string)$idField->getData();
                } else {
                    $missingIdCount++;
                }
            }
            if ($verbose && $missingIdCount) {
                Console::writeLine(
                    "Encountered $missingIdCount record(s) without IDs."
                );
            }
        }

        // Delete, Commit and Optimize if necessary:
        if (!empty($ids)) {
            if ($verbose) {
                Console::writeLine(
                    'Attempting to delete ' . count($ids) . ' record(s): '
                    . implode(', ', $ids)
                );
            }
            $writer = $this->getServiceLocator()->get('VuFind\Solr\Writer');
            $writer->deleteRecords($index, $ids);
            if ($verbose) {
                Console::writeLine('Delete operation completed.');
            }
        } elseif ($verbose) {
            Console::writeLine('Nothing to delete.');
        }

        return $this->getSuccessResponse();
    }

    /**
     * Command-line tool to clear unwanted entries
     * from record cache table.
     *
     * @return \Zend\Console\Response
     */
    public function cleanuprecordcacheAction()
    {
        $request = $this->getRequest();
        if ($request->getParam('help') || $request->getParam('h')) {
            Console::writeLine('Clean up unused cached records from the database.');
            return $this->getFailureResponse();
        }

        $recordTable = $this->getServiceLocator()->get('VuFind\DbTablePluginManager')
            ->get('Record');

        $count = $recordTable->cleanup();

        Console::writeLine("$count records deleted.");
        return $this->getSuccessResponse();
    }

    /**
     * Display help for the search or session expiration actions
     *
     * @param string $rows Plural name of records to delete
     *
     * @return \Zend\Console\Response
     */
    protected function expirationHelp($rows)
    {
        Console::writeLine("Expire old $rows in the database.");
        Console::writeLine('');
        Console::writeLine(
            'Optional parameters: [--batch=size] [--sleep=time] [age]'
        );
        Console::writeLine('');
        Console::writeLine(
            '  batch: number of records to delete in a single batch'
            . ' (default 1000)'
        );
        Console::writeLine(
            '  sleep: milliseconds to sleep between batches (default 100)'
        );

        Console::writeLine(
            "  age: the age (in days) of $rows to expire (default 2)"
        );
        Console::writeLine('');
        Console::writeLine(
            "By default, $rows more than 2 days old will be removed."
        );
        return $this->getFailureResponse();
    }

    /**
     * Command-line tool to clear unwanted entries
     * from search history database table.
     *
     * @return \Zend\Console\Response
     */
    public function expiresearchesAction()
    {
        $request = $this->getRequest();
        if ($request->getParam('help') || $request->getParam('h')) {
            return $this->expirationHelp('searches');
        }

        return $this->expire(
            'Search',
            '%%count%% expired searches deleted.',
            'No expired searches to delete.'
        );
    }

    /**
     * Command-line tool to clear unwanted entries
     * from session database table.
     *
     * @return \Zend\Console\Response
     */
    public function expiresessionsAction()
    {
        $request = $this->getRequest();
        if ($request->getParam('help') || $request->getParam('h')) {
            return $this->expirationHelp('sessions');
        }

        return $this->expire(
            'Session',
            '%%count%% expired sessions deleted.',
            'No expired sessions to delete.'
        );
    }

    /**
     * Command-line tool to clear unwanted entries
     * from external_session database table.
     *
     * @return \Zend\Console\Response
     */
    public function expireExternalSessionsAction()
    {
        $request = $this->getRequest();
        if ($request->getParam('help') || $request->getParam('h')) {
            return $this->expirationHelp('external sessions');
        }

        return $this->expire(
            'ExternalSession',
            '%%count%% expired external sessions deleted.',
            'No expired external sessions to delete.'
        );
    }

    /**
     * Command-line tool to delete suppressed records from the index.
     *
     * @return \Zend\Console\Response
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
        } else if (empty($result)) {
            Console::writeLine("No suppressed records to delete.");
            return $this->getSuccessResponse();
        }

        // Get Suppressed Records and Delete from index
        $solr = $this->getServiceLocator()->get('VuFind\Solr\Writer');
        $solr->deleteRecords($backend, $result);
        $solr->commit($backend);
        $solr->optimize($backend);
        return $this->getSuccessResponse();
    }

    /**
     * Tool to auto-fill hierarchy cache.
     *
     * @return \Zend\Console\Response
     */
    public function createhierarchytreesAction()
    {
        $request = $this->getRequest();
        if ($request->getParam('help') || $request->getParam('h')) {
            Console::writeLine('Available switches:');
            Console::writeLine('--skip-xml or -sx => Skip the XML cache');
            Console::writeLine('--skip-json or -sj => Skip the JSON cache');
            Console::writeLine('--help or -h => Show this message');
            return $this->getFailureResponse();
        }
        $skipJson = $request->getParam('skip-json') || $request->getParam('sj');
        $skipXml = $request->getParam('skip-xml') || $request->getParam('sx');
        $recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        $hierarchies = $this->getServiceLocator()
            ->get('VuFind\SearchResultsPluginManager')->get('Solr')
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
                $driver = $recordLoader->load($recordid);
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

    /**
     * Compile CSS files from LESS.
     *
     * @return \Zend\Console\Response
     */
    public function cssbuilderAction()
    {
        $opts = new \Zend\Console\Getopt([]);
        $compiler = new \VuFindTheme\LessCompiler(true);
        $cacheManager = $this->getServiceLocator()->get('VuFind\CacheManager');
        $cacheDir = $cacheManager->getCacheDir() . 'less/';
        $compiler->setTempPath($cacheDir);
        $compiler->compile(array_unique($opts->getRemainingArgs()));
        return $this->getSuccessResponse();
    }

    /**
     * Abstract delete method.
     *
     * @param string $tableName     Table to operate on.
     * @param string $successString String for reporting success.
     * @param string $failString    String for reporting failure.
     * @param int    $minAge        Minimum age allowed for expiration (also used
     * as default value).
     *
     * @return mixed
     */
    protected function expire($tableName, $successString, $failString, $minAge = 2)
    {
        // Get command-line arguments
        $request = $this->getRequest();

        // Use command line value as expiration age, or default to $minAge.
        $daysOld = intval($request->getParam('daysOld', $minAge));

        // Use command line values for batch size and sleep time if specified.
        $batchSize = $request->getParam('batch', 1000);
        $sleepTime = $request->getParam('sleep', 100);

        // Abort if we have an invalid expiration age.
        if ($daysOld < 2) {
            Console::writeLine(
                str_replace(
                    '%%age%%', $minAge,
                    'Expiration age must be at least %%age%% days.'
                )
            );
            return $this->getFailureResponse();
        }

        // Delete the expired rows--this cleans up any junk left in the database
        // e.g. from old searches or sessions that were not caught by the session
        // garbage collector.
        $table = $this->getTable($tableName);
        if (!method_exists($table, 'getExpiredIdRange')) {
            throw new \Exception("$tableName does not support getExpiredIdRange()");
        }
        if (!method_exists($table, 'deleteExpired')) {
            throw new \Exception("$tableName does not support deleteExpired()");
        }

        $idRange = $table->getExpiredIdRange($daysOld);
        if (false === $idRange) {
            $this->timestampedMessage($failString);
            return $this->getSuccessResponse();
        }

        // Delete records in batches
        for ($batch = $idRange[0]; $batch <= $idRange[1]; $batch += $batchSize) {
            $count = $table->deleteExpired(
                $daysOld, $batch, $batch + $batchSize - 1
            );
            $this->timestampedMessage(
                str_replace('%%count%%', $count, $successString)
            );
            // Be nice to others and wait between batches
            if ($batch + $batchSize <= $idRange[1]) {
                usleep($sleepTime * 1000);
            }
        }
        return $this->getSuccessResponse();
    }

    /**
     * Print a message with a time stamp to the console
     *
     * @param string $msg Message
     *
     * @return void
     */
    protected function timestampedMessage($msg)
    {
        Console::writeLine('[' . date('Y-m-d H:i:s') . '] ' . $msg);
    }

    /**
     * Convert hash algorithms
     * Expected parameters: oldmethod:oldkey (or none) newmethod:newkey
     *
     * @return \Zend\Console\Response
     */
    public function switchdbhashAction()
    {
        // Validate command line arguments:
        $request = $this->getRequest();
        $newhash = $request->getParam('newhash');
        if (empty($newhash)) {
            Console::writeLine(
                'Expected parameters: newmethod [newkey]'
            );
            return $this->getFailureResponse();
        }

        // Pull existing encryption settings from the configuration:
        $config = $this->getConfig();
        if (!isset($config->Authentication->encrypt_ils_password)
            || !isset($config->Authentication->ils_encryption_key)
            || !$config->Authentication->encrypt_ils_password
        ) {
            $oldhash = 'none';
            $oldkey = null;
        } else {
            $oldhash = isset($config->Authentication->ils_encryption_algo)
                ? $config->Authentication->ils_encryption_algo : 'blowfish';
            $oldkey = $config->Authentication->ils_encryption_key;
        }

        // Pull new encryption settings from arguments:
        $newkey = $request->getParam('newkey', $oldkey);

        // No key specified AND no key on file = fatal error:
        if ($newkey === null) {
            Console::writeLine('Please specify a key as the second parameter.');
            return $this->getFailureResponse();
        }

        // If no changes were requested, abort early:
        if ($oldkey == $newkey && $oldhash == $newhash) {
            Console::writeLine('No changes requested -- no action needed.');
            return $this->getSuccessResponse();
        }

        // Initialize Mcrypt first, so we can catch any illegal algorithms before
        // making any changes:
        try {
            if ($oldhash != 'none') {
                $oldCrypt = new Mcrypt(['algorithm' => $oldhash]);
            }
            $newCrypt = new Mcrypt(['algorithm' => $newhash]);
        } catch (\Exception $e) {
            Console::writeLine($e->getMessage());
            return $this->getFailureResponse();
        }

        // Next update the config file, so if we are unable to write the file,
        // we don't go ahead and make unwanted changes to the database:
        $configPath = ConfigLocator::getLocalConfigPath('config.ini', null, true);
        Console::writeLine("\tUpdating $configPath...");
        $writer = new ConfigWriter($configPath);
        $writer->set('Authentication', 'encrypt_ils_password', true);
        $writer->set('Authentication', 'ils_encryption_algo', $newhash);
        $writer->set('Authentication', 'ils_encryption_key', $newkey);
        if (!$writer->save()) {
            Console::writeLine("\tWrite failed!");
            return $this->getFailureResponse();
        }

        // Now do the database rewrite:
        $userTable = $this->getServiceLocator()->get('VuFind\DbTablePluginManager')
            ->get('User');
        $users = $userTable->select(
            function ($select) {
                $select->where->isNotNull('cat_username');
            }
        );
        Console::writeLine("\tConverting hashes for " . count($users) . ' user(s).');
        foreach ($users as $row) {
            $pass = null;
            if ($oldhash != 'none' && isset($row['cat_pass_enc'])) {
                $oldcipher = new BlockCipher($oldCrypt);
                $oldcipher->setKey($oldkey);
                $pass = $oldcipher->decrypt($row['cat_pass_enc']);
            } else {
                $pass = $row['cat_password'];
            }
            $newcipher = new BlockCipher($newCrypt);
            $newcipher->setKey($newkey);
            $row['cat_password'] = null;
            $row['cat_pass_enc'] = $newcipher->encrypt($pass);
            $row->save();
        }

        // If we got this far, all went well!
        Console::writeLine("\tFinished.");
        return $this->getSuccessResponse();
    }
}
