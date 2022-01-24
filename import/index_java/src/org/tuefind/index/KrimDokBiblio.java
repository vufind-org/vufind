package org.tuefind.index;

import java.util.Arrays;
import java.util.List;
import java.util.Set;
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

    public Set<String> getAllTopicsCloud(final Record record) {
        final Set<String> topics = getAllSubfieldsBut(record, "600:610:611:630:650:653:656:689a", "0");
        final List<String> excludeIndicators = Arrays.asList("rv", "bk");
        topics.addAll(getAllSubfieldsBut(record, "936a", "0", excludeIndicators));
        topics.addAll(getLocal689Topics(record));
        return topics;
    }
}
