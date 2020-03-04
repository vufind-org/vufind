<?php
/**
 * Field group builder for record driver data formatting view helper
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @package  View_Helpers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\View\Helper\Root\RecordDataFormatter;

/**
 * Field group builder for record driver data formatting view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class FieldGroupBuilder
{
    /**
     * Groups.
     *
     * @var array
     */
    protected $groups = [];

    /**
     * FieldGroupBuilder constructor.
     *
     * @param array $groups Existing field groups (optional).
     */
    public function __construct($groups = [])
    {
        $this->groups = $groups;
    }

    /**
     * Add a group.
     *
     * @param string $label    Label for this group or false for no label.
     * @param array  $lines    Lines belonging to the group.
     * @param string $template Template used to render the lines in the group.
     * @param array  $options  Additional options (optional).
     *
     * @return void
     */
    public function addGroup($label, $lines, $template, $options = [])
    {
        $options['label'] = $label;
        $options['lines'] = $lines;
        $options['template'] = $template;
        if (!isset($options['context'])) {
            $options['context'] = [];
        }
        $this->groups[] = $options;
    }

    /**
     * Helper method for setting multiple groups at once.
     *
     * @param array  $groups        Array specifying the groups.
     * @param array  $lines         All lines used in the groups.
     * @param string $template      Default group template to use if not
     *                              specified for a group.
     * @param array  $options       Additional options to use if not specified
     *                              for a group (optional).
     * @param array  $unusedOptions Additional options for unused lines
     *                              (optional).
     *
     * @return void
     */
    public function setGroups($groups, $lines, $template, $options = [],
        $unusedOptions = []
    ) {
        $allUsed = [];
        foreach ($groups as $group) {
            if (!isset($group['lines'])) {
                continue;
            }
            $groupLabel = $group['label'] ?? false;
            $groupTemplate = $group['template'] ?? $template;
            $groupOptions = $group['options'] ?? $options;

            // Get group lines from provided lines array and use group spec
            // array order for line pos values.
            $groupLines = [];
            $pos = 0;
            foreach ($group['lines'] as $key) {
                $groupLine = $lines[$key];
                $pos += 100;
                $groupLine['pos'] = $pos;
                $groupLines[$key] = $groupLine;
            }

            $allUsed = array_merge($allUsed, $groupLines);
            $this->addGroup($groupLabel, $groupLines, $groupTemplate, $groupOptions);
        }
        $allUnused = array_diff_key($lines, $allUsed);
        $unusedTemplate = $unusedOptions['template'] ?? $template;
        $this->addGroup(
            false, $allUnused, $unusedTemplate, $unusedOptions
        );
    }

    /**
     * Get the group spec.
     *
     * @return array
     */
    public function getArray()
    {
        return $this->groups;
    }
}
