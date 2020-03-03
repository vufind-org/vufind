<?php
/**
 * CLI Controller Module (scheduled search tools)
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2019.
 * Copyright (C) The National Library of Finland 2015-2020.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace FinnaConsole\Controller;

/**
 * CLI Controller Module (scheduled search tools)
 *
 * This service works in three phases:
 *
 * 1. If VUFIND_LOCAL_DIR environment variable is undefined,
 *    it is set to master VuFind configuration directory
 *    and the script is called again.
 *
 * 2. If no view URL (field 'notification_base_url' in table search)
 *    to process scheduled alerts for is supplied, all distinct view
 *    URLs are retrieved, and the script is called again for each URL.
 *
 * 3. Scheduled alerts for a given view are processed.
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class ScheduledSearchController
    extends \VuFindConsole\Controller\ScheduledSearchController
{
    use \FinnaConsole\Service\ConsoleLoggerTrait;
    use \FinnaConsole\Service\ViewPathTrait;

    /**
     * Local configuration directory name
     *
     * @var string
     */
    protected $confDir = 'local';

    /**
     * Set the request for params
     *
     * @param \Zend\Console\Request $request Request
     *
     * @return void
     */
    public function setRequest(\Zend\Console\Request $request)
    {
        $this->request = $request;
    }

    /**
     * Send notifications.
     *
     * @return \Zend\Console\Response
     */
    public function notifyAction()
    {
        $this->initLogging();
        $this->collectScriptArguments();

        try {
            if (!$this->localDir = getenv('VUFIND_LOCAL_DIR')) {
                $this->msg('Switching to VuFind configuration');
                $this->switchInstitution($this->baseDir);
            } elseif (!$this->scheduleBaseUrl) {
                $this->processAlerts();
                exit(0);
            } else {
                $this->processViewAlerts();
                exit(0);
            }
        } catch (\Exception $e) {
            $this->err(
                "Exception: " . $e->getMessage(),
                'Exception occurred'
            );
            while ($e = $e->getPrevious()) {
                $this->err("  Previous exception: " . $e->getMessage());
            }
            exit(1);
        }

        $this->processViewAlerts();
        return $this->getSuccessResponse();
    }

    /**
     * Collect script parameters and print usage
     * information when all required parameters were not given.
     *
     * @return void
     */
    protected function collectScriptArguments()
    {
        $arguments = $this->getRequest()->getParams()->toArray();
        $argv = array_splice($arguments, 2, -2);

        // Base directory for all views.
        $this->viewBaseDir = $argv[0] ?? '..';
        // Current view local configuration directory
        $this->baseDir = $argv[1] ?? false;
        // Schedule base url for alerts to send
        $this->scheduleBaseUrl = $argv[2] ?? false;

        if (!$this->viewBaseDir || !$this->baseDir) {
            echo $this->usage();
            exit(1);
        }
    }

    /**
     * Get script usage information.
     *
     * @return string
     */
    protected function usage()
    {
        $appPath = APPLICATION_PATH;
        return <<<EOT
Usage:
  php $appPath/util/scheduled_alerts.php <view_base> <local_conf>

  Sends scheduled alerts.
    view_base  View base directory
    local_conf VuFind local configuration directory

For example:
  php $appPath/util/scheduled_alerts.php /tmp/finna /tmp/NDL-VuFind2/local

EOT;
    }

    /**
     * Process all scheduled alerts grouped by view URLs.
     *
     * @return void
     */
    protected function processAlerts()
    {
        $baseDirs = $this->getTable('search')->getScheduleBaseUrls();
        $this->msg('Processing alerts for ' . count($baseDirs) . ' views: ');
        $this->msg('  ' . implode(', ', $baseDirs));
        foreach ($baseDirs as $url) {
            $parts = parse_url($url);
            $host = explode('.', $parts['host']);
            $hostCnt = count($host);

            if ($hostCnt < 2 || $hostCnt > 4) {
                $this->err("Invalid base URL $url", '=');
                continue;
            }

            $institution = $host[0];

            if ($hostCnt == 4 && $institution == 'www') {
                // www.[organisation].finna.fi
                $institution = $host[1];
            } elseif ($hostCnt == 2 || ($hostCnt == 3 && $institution == 'www')) {
                // finna.fi and www.finna.fi
                $institution = 'national';
            }
            $view = isset($parts['path']) ? substr($parts['path'], 1) : false;

            if (!$path = $this->resolveViewPath($institution, $view)) {
                $this->err("Skipping alerts for view $url", '=');
                continue;
            }
            $this->switchInstitution("$path/{$this->confDir}", $url);
        }
    }

    /**
     * Switch application configuration by calling this script from a
     * view's directory and using local configuration of the view.
     *
     * @param string $localDir        View local configuration directory.
     * @param string $scheduleBaseUrl View URL to send scheduled alerts for.
     *                                (optional)
     *
     * @return void
     */
    protected function switchInstitution($localDir, $scheduleBaseUrl = false)
    {
        $appDir = substr($localDir, 0, strrpos($localDir, "/{$this->confDir}"));
        $script = "$appDir/public/index.php";

        $args = ['util', 'scheduled_alerts', $this->viewBaseDir, $localDir];
        if ($scheduleBaseUrl) {
            $args[] = "'$scheduleBaseUrl'";
        }

        $cmd = "VUFIND_LOCAL_DIR='$localDir'";
        $cmd .= " php -d short_open_tag=1 -d display_errors=1 '$script' "
            . implode(' ', $args);
        $this->msg("  Switching to institution configuration $localDir");
        $this->msg("    $cmd");
        system($cmd, $retval);
        if ($retval !== 0) {
            $this->err("Error calling: $cmd", '=');
        }
    }

    /**
     * Send scheduled alerts for a view.
     *
     * @return void
     */
    protected function processViewAlerts()
    {
        $todayTime = new \DateTime();
        $scheduled = $this->getTable('search')
            ->getScheduledSearches($this->scheduleBaseUrl);
        $this->msg(sprintf('Processing %d searches', count($scheduled)));
        foreach ($scheduled as $s) {
            $lastTime = new \DateTime($s->last_notification_sent);
            if (!$this->validateSchedule($todayTime, $lastTime, $s)
                || !($user = $this->getUserForSearch($s))
                || !($searchObject = $this->getObjectForSearch($s))
                || !($newRecords = $this->getNewRecords($searchObject, $lastTime))
            ) {
                continue;
            }
            // Set email language
            $this->setLanguage($user->last_language);

            // Prepare email content
            $message = $this->buildEmail($s, $user, $searchObject, $newRecords);
            if (!$this->sendEmail($user, $message)) {
                // If email send failed, move on to the next user without updating
                // the database table.
                continue;
            }
            $searchTime = date('Y-m-d H:i:s');
            if ($s->setLastExecuted($searchTime) === 0) {
                $this->err("Error updating last_executed date for search $searchId");
            }
        }
        $this->msg('Done processing searches');
    }
}
