<?php

/**
 * Field group builder for record driver data formatting view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2020-2023.
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
     * @param array  $options  Additional group options (optional):
     *                         - context
     *                         Context array containing data made available to
     *                         group templates.
     *                         - lineContext
     *                         Context array containing data made available to
     *                         field templates.
     *                         - skipGroup
     *                         Set to true to skip rendering of the group. This
     *                         can e.g. be used to skip rendering unused lines.
     *
     * @return void
     */
    public function addGroup($label, $lines, $template, $options = [])
    {
        $options['context'] ??= [];
        $options['lineContext'] ??= [];
        $options['skipGroup'] ??= false;

        if (!empty($options['lineContext'])) {
            foreach ($lines as &$line) {
                $line['context'] = array_merge_recursive(
                    $line['context'] ?? [],
                    $options['lineContext']
                );
            }
        }

        $this->groups[] = [
            'label' => $label,
            'lines' => $lines,
            'template' => $template,
            'options' => $options,
        ];
    }

    /**
     * Helper method for setting multiple groups at once.
     *
     * @param array  $groups        Array specifying the groups. See
     *                              FieldGroupBuilder::addGroup() for details.
     * @param array  $lines         All lines used in the groups. If this contains
     *                              lines not specified in $groups, all unused lines
     *                              will be appended as their own group.
     * @param string $template      Default group template to use if not
     *                              specified for a group.
     * @param array  $options       Additional options to be merged with group
     *                              specific additional options (optional). See
     *                              FieldGroupBuilder::addGroup() for details.
     * @param array  $unusedOptions Additional options for the unused lines group
     *                              (optional). See FieldGroupBuilder::addGroup()
     *                              for details.
     *
     * @return void
     */
    public function setGroups(
        $groups,
        $lines,
        $template,
        $options = [],
        $unusedOptions = null
    ) {
        $unusedOptions ??= $options;
        $allUsed = [];
        foreach ($groups as $group) {
            if (!isset($group['lines'])) {
                continue;
            }
            $groupLabel = $group['label'] ?? false;
            $groupTemplate = $group['template'] ?? $template;
            $groupOptions = array_merge_recursive(
                $options,
                $group['options'] ?? []
            );

            // Get group lines from provided lines array and use group spec
            // array order for line pos values.
            $groupLines = [];
            $pos = 0;
            foreach ($group['lines'] as $key) {
                if (!($groupLine = $lines[$key] ?? null)) {
                    continue;
                }
                $pos += 100;
                $groupLine['pos'] = $pos;

                // If there is a group line context, merge it here since we are
                // already looping through the lines.
                if (!empty($groupOptions['lineContext'])) {
                    $groupLine['context'] = array_merge_recursive(
                        $groupLine['context'] ?? [],
                        $groupOptions['lineContext']
                    );
                }

                $groupLines[$key] = $groupLine;
            }
            unset($groupOptions['lineContext']);

            $allUsed = array_merge($allUsed, $groupLines);
            $this->addGroup($groupLabel, $groupLines, $groupTemplate, $groupOptions);
        }
        $allUnused = array_diff_key($lines, $allUsed);
        $unusedTemplate = $unusedOptions['template'] ?? $template;
        $this->addGroup(
            false,
            $allUnused,
            $unusedTemplate,
            $unusedOptions
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
