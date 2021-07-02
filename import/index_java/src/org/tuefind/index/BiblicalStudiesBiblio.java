package org.tuefind.index;

import java.util.logging.Logger;
import org.marc4j.marc.Record;

public class BiblicalStudiesBiblio extends IxTheoBiblio {
    public String getIsBiblicalStudies(final Record record) {
        return record.getVariableFields("BIB").isEmpty() ? "false" : "true";
    }
}
