<?php

namespace TueFind\View\Helper\Root;

class RecordDataFormatter extends \VuFind\View\Helper\Root\RecordDataFormatter {

    /**
     * Extend parent. Also return options in sub-array, so we are able
     * to pass additional information from Factory to core e.g. core template.
     */
    protected function render($driver, $field, $data, $options)
    {
        $result = parent::render($driver, $field, $data, $options);
        if (!is_array($result)) {
            return $result;
        } else {
            $augmentedResult = [];
            foreach ($result as $entry) {
                $entry['options'] = $options;
                $augmentedResult[] = $entry;
            }
            return $augmentedResult;
        }
    }
}