package org.tuefind.index;

import java.util.List;
import java.util.logging.Logger;
import org.marc4j.marc.Record;
import org.marc4j.marc.VariableField;

public class KrimDokBiblio extends TueFindBiblio {

    public String isAvailableForPDA(final Record record) {
        final List<VariableField> fields = record.getVariableFields("PDA");
        return Boolean.toString(!fields.isEmpty());
    }

    public String getFullTextElasticsearch(final Record record) throws Exception {
        return extractFullTextFromJSON(getFullTextServerHits(record), "" /* empty to catch all text types */);
    }
}
