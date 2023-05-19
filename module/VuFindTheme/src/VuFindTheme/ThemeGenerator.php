<?php

/**
 * Class to generate a new theme from a template and reconfigure VuFind to use it.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017.
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
 * @package  Theme
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindTheme;

use Laminas\Config\Config;
use VuFind\Config\Locator as ConfigLocator;
use VuFind\Config\PathResolver;
use VuFind\Config\Writer as ConfigWriter;

/**
 * Class to generate a new theme from a template and reconfigure VuFind to use it.
 *
 * @category VuFind
 * @package  Theme
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ThemeGenerator extends AbstractThemeUtility implements GeneratorInterface
{
    use \VuFindConsole\ConsoleOutputTrait;

    /**
     * Config file path resolver
     *
     * @var PathResolver
     */
    protected $pathResolver;

    /**
     * Constructor
     *
     * @param ThemeInfo    $info         Theme info object
     * @param PathResolver $pathResolver Config file path resolver
     */
    public function __construct(ThemeInfo $info, PathResolver $pathResolver = null)
    {
        parent::__construct($info);
        $this->pathResolver = $pathResolver;
    }

    /**
     * Generate a new theme from a template.
     *
     * @param string $name          Name of theme to generate.
     * @param string $themeTemplate Name of template theme
     *
     * @return bool
     */
    public function generate($name, $themeTemplate = 'local_theme_example')
    {
        // Check for existing theme
        $baseDir = $this->info->getBaseDir() . '/';
        if (realpath($baseDir . $name)) {
            return $this->setLastError('Theme "' . $name . '" already exists');
        }
        $this->writeln('Creating new theme: "' . $name . '"');
        $source = $baseDir . $themeTemplate;
        $dest = $baseDir . $name;
        $this->writeln("\tCopying $themeTemplate");
        $this->writeln("\t\tFrom: " . $source);
        $this->writeln("\t\tTo: " . $dest);
        return $this->copyDir($source, $dest);
    }

    /**
     * Configure the specified theme as VuFind's new default theme (and one of
     * the alternatives).
     *
     * @param Config $config Existing VuFind configuration
     * @param string $name   Theme name to add to configuration.
     *
     * @return bool
     */
    public function configure(Config $config, $name)
    {
        // Enable theme
        $configPath = $this->pathResolver
            ? $this->pathResolver->getLocalConfigPath('config.ini', null, true)
            : ConfigLocator::getLocalConfigPath('config.ini', null, true);
        if (!file_exists($configPath)) {
            return $this
                ->setLastError("Expected configuration file missing: $configPath");
        }
        $this->writeln("\tUpdating $configPath...");
        $this->writeln("\t\t[Site] > theme = $name");
        $writer = new ConfigWriter($configPath);
        $writer->set('Site', 'theme', $name);
        // Enable dropdown
        $settingPrefixes = [
            'bootstrap' => 'bs3',
            'custom' => strtolower(str_replace(' ', '', $name)),
        ];
        // - Set alternate_themes
        $this->writeln("\t\t[Site] > alternate_themes");
        $altSetting = [];
        if (isset($config->Site->alternate_themes)) {
            $alts = explode(',', $config->Site->alternate_themes);
            foreach ($alts as $a) {
                $parts = explode(':', $a);
                if ($parts[1] === 'bootstrap3') {
                    $settingPrefixes['bootstrap'] = $parts[0];
                } elseif ($parts[1] === $name) {
                    $settingPrefixes['custom'] = $parts[0];
                } else {
                    $altSetting[] = $a;
                }
            }
        }
        $altSetting[] = $settingPrefixes['bootstrap'] . ':bootstrap3';
        $altSetting[] = $settingPrefixes['custom'] . ':' . $name;
        $writer->set('Site', 'alternate_themes', implode(',', $altSetting));
        // - Set selectable_themes
        $this->writeln("\t\t[Site] > selectable_themes");
        $dropSetting = [
            $settingPrefixes['bootstrap'] . ':Bootstrap',
            $settingPrefixes['custom'] . ':' . ucwords($name),
        ];
        if (isset($config->Site->selectable_themes)) {
            $themes = explode(',', $config->Site->selectable_themes);
            foreach ($themes as $t) {
                $parts = explode(':', $t);
                if (
                    $parts[0] !== $settingPrefixes['bootstrap']
                    && $parts[0] !== $settingPrefixes['custom']
                ) {
                    $dropSetting[] = $t;
                }
            }
        }
        $writer->set('Site', 'selectable_themes', implode(',', $dropSetting));
        // Save
        if (!$writer->save()) {
            return $this->setLastError("\tConfiguration saving failed!");
        }
        return true;
    }
}
