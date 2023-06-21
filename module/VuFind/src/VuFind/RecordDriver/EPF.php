<?php

namespace VuFind\RecordDriver;

class EPF extends EDS
{

    public function getUniqueId() 
    {
        return $this->fields['Header']['PublicationId'];
    }

    public function getThumbnail($size = 'small')
    {
        // Override EDS parent class and get default implementation
        return DefaultRecord::getThumbnail($size);
    }

}