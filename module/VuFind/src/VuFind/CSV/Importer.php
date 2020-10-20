<?php
/**
 * VuFind CSV importer
 *
 * PHP version 7
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
 * @package  CSV
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/ Wiki
 */
namespace VuFind\XSLT;

use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFind\Config\Locator as ConfigLocator;
use VuFindSearch\Backend\Solr\Document\RawCSVDocument;
use VuFindSearch\ParamBag;

/**
 * VuFind CSV importer
 *
 * @category VuFind
 * @package  CSV
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/ Wiki
 */
class Importer
{
    /**
     * Service locator
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service manager
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        $this->serviceLocator = $sm;
    }

    /**
     * Save a CSV file to the Solr index using the specified configuration.
     *
     * @param string $csvFile  CSV file to load.
     * @param string $iniFile  INI file.
     * @param string $index    Solr index to use.
     * @param bool   $testMode Are we in test-only mode?
     *
     * @throws \Exception
     * @return string            Transformed XML
     */
    public function save($csvFile, $iniFile, $index = 'Solr',
        $testMode = false
    ) {
        // Process the file:
        list ($fields, $csv) = $this->generateCSV($csvFile, $iniFile);
        $params = new ParamBag(['fieldnames' => $fields]);

        // Save the results (or just display them, if in test mode):
        if (!$testMode) {
            $solr = $this->serviceLocator->get(\VuFind\Solr\Writer::class);
            $solr->save(
                $index,
                new RawCSVDocument($csv), 'csv', 'update', $params
            );
        }
        return $csv;
    }

    /**
     * Transform $csvFile using the provided $iniFile configuration.
     *
     * @param string $csvFile CSV file to load.
     * @param string $iniFile INI file.
     *
     * @throws \Exception
     * @return array
     */
    protected function generateCSV($csvFile, $iniFile)
    {
        // Load properties file:
        $ini = ConfigLocator::getConfigPath($iniFile, 'import');
        if (!file_exists($ini)) {
            throw new \Exception("Cannot load .ini file: {$ini}.");
        }
        $options = parse_ini_file($ini, true);

        $in = fopen($csvFile, 'r');
        if (!$in) {
            throw new \Exception("Cannot open CSV file: {$csvFile}.");
        }
        $out = fopen('php://temp', 'r+');
        while ($line = fgetcsv($in)) {
            fputcsv($out, $this->processLine($line));
        }
        fclose($in);
        rewind($out);
        $csv = '';
        while ($data = fread($out, 1048576)) {
            $csv .= $data;
        }
        fclose($out);

        return [$fields, $csv];
    }
}
