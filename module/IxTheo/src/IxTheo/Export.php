<?php

// Custom logic for handling export output as parameter to external link

namespace IxTheo;

use Zend\Config\Config;

class Export extends \VuFind\Export
{
    /**
     * Constructor
     *
     * @param Config $mainConfig   Main VuFind configuration
     * @param Config $exportConfig Export-specific configuration
     */
    public function __construct(Config $mainConfig, Config $exportConfig)
    {
        parent::__construct($mainConfig, $exportConfig);
    }


    /**
     * Build callback URL for export.
     *
     * @param string $format   Export format being used
     * @param string $callback Callback URL for retrieving record(s)
     *
     * @return string
     */
    public function getRedirectUrl($format, $callback)
    {
        // Fill in special tokens in template:
        $template = $this->exportConfig->$format->redirectUrl;
        preg_match_all('/\{([^}]+)\}/', $template, $matches);
        foreach ($matches[1] as $current) {
            $parts = explode('|', $current);
            switch ($parts[0]) {
            case 'config':
            case 'encodedConfig':
                if (isset($this->mainConfig->{$parts[1]}->{$parts[2]})) {
                    $value = $this->mainConfig->{$parts[1]}->{$parts[2]};
                } else {
                    $value = $parts[3];
                }
                if ($parts[0] == 'encodedConfig') {
                    $value = urlencode($value);
                }
                $template = str_replace('{' . $current . '}', $value, $template);
                break;
            case 'encodedCallback':
                $template = str_replace(
                    '{' . $current . '}', urlencode($callback), $template
                );
                break;
            case 'useExportOutputAsParameter':
                $template = str_replace(
                    '{' . $current . '}', $callback, $template
                );
                break;
            }
        }
        return $template;
    }


   /**
     * Does the requested format require direcly use the callback as Redirect parameter
     *
     * @param string $format Format to check
     *
     * @return bool
     */
    public function useExportOutputAsParameter($format)
    {
         return isset($this->exportConfig->$format->useExportOutputAsParameter);
    }


}
?>
