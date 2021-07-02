package org.tuefind.index;

import java.util.List;
import org.marc4j.marc.Record;
import org.marc4j.marc.VariableField;

public class KrimDok extends TuelibBiblioMixin {
    public String isAvailableForPDA(final Record record) {
        final List<VariableField> fields = record.getVariableFields("PDA");
        return Boolean.toString(!fields.isEmpty());
    }

    public String getFullTextElasticsearch(final Record record) {
        return extractFullTextFromJSON(fulltext_server_hits, "" /* empty to catch all text types */);
    }
}
