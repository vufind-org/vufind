<?php

namespace VuFind\RecordTab;

class OverdriveHoldings extends AbstractBase implements TabInterface
{
    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return "Holdings";
    }

}
