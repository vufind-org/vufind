<?php

namespace IxTheo\View\Helper\Root;

class RecordDataFormatter extends \VuFind\View\Helper\Root\RecordDataFormatter {
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