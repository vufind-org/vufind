package org.tuefind.index;

import org.marc4j.marc.Record;

public class BiblicalStudies extends IxTheo {
    public String getIsBiblicalStudies(final Record record) {
        return record.getVariableFields("BIB").isEmpty() ? "false" : "true";
    }
}
