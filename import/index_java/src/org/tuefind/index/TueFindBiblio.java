package org.tuefind.index;

import java.net.InetAddress;
import java.io.BufferedReader;
import java.io.File;
import java.io.FileNotFoundException;
import java.io.FileReader;
import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.text.DateFormat;
import java.text.SimpleDateFormat;
import java.time.Instant;
import java.time.YearMonth;
import java.util.*;
import java.util.Map.Entry;
import java.util.function.Predicate;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
import java.util.stream.Stream;
import org.apache.http.client.methods.CloseableHttpResponse;
import org.apache.http.client.methods.HttpPost;
import org.apache.http.entity.StringEntity;
import org.apache.http.impl.client.CloseableHttpClient;
import org.apache.http.impl.client.HttpClientBuilder;
import org.apache.http.impl.client.HttpClients;
import org.apache.http.impl.conn.PoolingHttpClientConnectionManager;
import org.apache.http.util.EntityUtils;
import org.apache.http.HttpEntity;
import org.json.simple.JSONArray;
import org.json.simple.JSONObject;
import org.json.simple.parser.JSONParser;
import org.json.simple.parser.ParseException;
import org.marc4j.marc.ControlField;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;
import org.solrmarc.index.SolrIndexer;
import org.solrmarc.tools.DataUtil;
import org.solrmarc.tools.PropertyUtils;
import org.solrmarc.tools.Utils;
import org.solrmarc.driver.Boot;
import org.vufind.index.DatabaseManager;
import java.sql.*;
import static java.util.stream.Collectors.joining;

public class TueFindBiblio extends TueFind {
    public final static String UNASSIGNED_STRING = "[Unassigned]";
    public final static Set<String> UNASSIGNED_SET = Collections.singleton(UNASSIGNED_STRING);

    protected final static String UNKNOWN_MATERIAL_TYPE = "Unbekanntes Material";

    protected final static String ISIL_BSZ = "DE-576";
    protected final static String ISIL_GND = "DE-588";
    protected final static String ISIL_K10PLUS = "DE-627";

    protected final static String ISIL_PREFIX_BSZ = "(" + ISIL_BSZ + ")";
    protected final static String ISIL_PREFIX_GND = "(" + ISIL_GND + ")";
    protected final static String ISIL_PREFIX_K10PLUS = "(" + ISIL_K10PLUS + ")";
    protected final static String ISIL_PREFIX_K10PLUS_ESCAPED = "\\(" + ISIL_K10PLUS + "\\)";
    protected final static String ES_FULLTEXT_PROPERTIES_FILE = "es_fulltext.properties";

    protected final static Pattern PAGE_RANGE_PATTERN1 = Pattern.compile("\\s*(\\d+)\\s*-\\s*(\\d+)$", Pattern.UNICODE_CHARACTER_CLASS);
    protected final static Pattern PAGE_RANGE_PATTERN2 = Pattern.compile("\\s*\\[(\\d+)\\]\\s*-\\s*(\\d+)$", Pattern.UNICODE_CHARACTER_CLASS);
    protected final static Pattern PAGE_RANGE_PATTERN3 = Pattern.compile("\\s*(\\d+)\\s*ff", Pattern.UNICODE_CHARACTER_CLASS);
    protected final static Pattern PAGE_MATCH_PATTERN = Pattern.compile("^\\[?(\\d+)\\]?([-–](\\d+))?$");
    protected final static Pattern VALID_FOUR_DIGIT_YEAR_PATTERN = Pattern.compile("\\d{4}");
    protected final static Pattern VALID_YEAR_RANGE_PATTERN = Pattern.compile("^\\d*u*$");
    protected final static Pattern VOLUME_PATTERN = Pattern.compile("^\\s*(\\d+)$", Pattern.UNICODE_CHARACTER_CLASS);
    protected final static Pattern BRACKET_DIRECTIVE_PATTERN = Pattern.compile("\\[(.)(.)\\]");
    protected final static Pattern PPN_WITH_K10PLUS_ISIL_PREFIX_PATTERN = Pattern.compile("\\(" + ISIL_K10PLUS + "\\)(.*)");
    protected final static Pattern SUPERIOR_PPN_WITH_K10PLUS_ISIL_PREFIX_PATTERN = PPN_WITH_K10PLUS_ISIL_PREFIX_PATTERN;
    protected final static Pattern DIFFERENT_CALCULATION_OF_TIME_PATTERN =  Pattern.compile(".*?\\[(.*?)\\=\\s*(\\d+)\\s*\\].*", Pattern.UNICODE_CHARACTER_CLASS);
    protected final static Pattern REMAINS_OR_PARTIAL_REMAINS = Pattern.compile("^(?=Nachlass|Teilnachlass).*");

    // use static instance for better performance
    protected static CreatorTools creatorTools = new CreatorTools();

    // TODO: This should be in a translation mapping file
    protected final static HashMap<String, String> isil_to_department_map = new HashMap<String, String>() {
        {
            this.put("Unknown", "Unknown");
            this.put("DE-21", "Universit\u00E4tsbibliothek T\u00FCbingen");
            this.put("DE-21-1", "Universit\u00E4t T\u00FCbingen, Klinik f\u00FCr Psychatrie und Psychologie");
            this.put("DE-21-3", "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Toxikologie und Pharmakologie");
            this.put("DE-21-4", "Universit\u00E4t T\u00FCbingen, Universit\u00E4ts-Augenklinik");
            this.put("DE-21-10", "Universit\u00E4tsbibliothek T\u00FCbingen, Bereichsbibliothek Geowissenschaften");
            this.put("DE-21-11", "Universit\u00E4tsbibliothek T\u00FCbingen, Bereichsbibliothek Schloss Nord");
            this.put("DE-21-14",
                    "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Ur- und Fr\u00FChgeschichte und Arch\u00E4ologie des Mittelalters, Abteilung j\u00FCngere Urgeschichte und Fr\u00FChgeschichte + Abteilung f\u00FCr Arch\u00E4ologie des Mittelalters");
            this.put("DE-21-17", "Universit\u00E4t T\u00FCbingen, Geographisches Institut");
            this.put("DE-21-18", "Universit\u00E4t T\u00FCbingen, Universit\u00E4ts-Hautklinik");
            this.put("DE-21-19", "Universit\u00E4t T\u00FCbingen, Wirtschaftswissenschaftliches Seminar");
            this.put("DE-21-20", "Universit\u00E4t T\u00FCbingen, Frauenklinik");
            this.put("DE-21-21", "Universit\u00E4t T\u00FCbingen, Universit\u00E4ts-Hals-Nasen-Ohrenklinik, Bibliothek");
            this.put("DE-21-22", "Universit\u00E4t T\u00FCbingen, Kunsthistorisches Institut");
            this.put("DE-21-23", "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Pathologie");
            this.put("DE-21-24", "Universit\u00E4t T\u00FCbingen, Juristisches Seminar");
            this.put("DE-21-25", "Universit\u00E4t T\u00FCbingen, Musikwissenschaftliches Institut");
            this.put("DE-21-26", "Universit\u00E4t T\u00FCbingen, Anatomisches Institut");
            this.put("DE-21-27", "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Anthropologie und Humangenetik");
            this.put("DE-21-28", "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Astronomie und Astrophysik, Abteilung Astronomie");
            this.put("DE-21-31", "Universit\u00E4t T\u00FCbingen, Evangelisch-theologische Fakult\u00E4t");
            this.put("DE-21-32a", "Universit\u00E4t T\u00FCbingen, Historisches Seminar, Abteilung f\u00FCr Alte Geschichte");
            this.put("DE-21-32b", "Universit\u00E4t T\u00FCbingen, Historisches Seminar, Abteilung f\u00FCr Mittelalterliche Geschichte");
            this.put("DE-21-32c", "Universit\u00E4t T\u00FCbingen, Historisches Seminar, Abteilung f\u00FCr Neuere Geschichte");
            this.put("DE-21-34", "Universit\u00E4t T\u00FCbingen, Asien-Orient-Institut, Abteilung f\u00FCr Indologie und Vergleichende Religionswissenschaft");
            this.put("DE-21-35", "Universit\u00E4t T\u00FCbingen, Katholisch-theologische Fakult\u00E4t");
            this.put("DE-21-39", "Universit\u00E4t T\u00FCbingen, Fachbibliothek Mathematik und Physik / Bereich Mathematik");
            this.put("DE-21-37", "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Sportwissenschaft");
            this.put("DE-21-42", "Universit\u00E4t T\u00FCbingen, Asien-Orient-Institut, Abteilung f\u00FCr Orient- uns Islamwissenschaft");
            this.put("DE-21-43", "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Erziehungswissenschaft");
            this.put("DE-21-45", "Universit\u00E4t T\u00FCbingen, Philologisches Seminar");
            this.put("DE-21-46", "Universit\u00E4t T\u00FCbingen, Philosophisches Seminar");
            this.put("DE-21-50", "Universit\u00E4t T\u00FCbingen, Physiologisches Institut");
            this.put("DE-21-51", "Universit\u00E4t T\u00FCbingen, Psychologisches Institut");
            this.put("DE-21-52", "Universit\u00E4t T\u00FCbingen, Ludwig-Uhland-Institut f\u00FCr Empirische Kulturwissenschaft");
            this.put("DE-21-53", "Universit\u00E4t T\u00FCbingen, Asien-Orient-Institut, Abteilung f\u00FCr Ethnologie");
            this.put("DE-21-54", "Universit\u00E4t T\u00FCbingen, Universit\u00E4tsklinik f\u00FCr Zahn-, Mund- und Kieferheilkunde");
            this.put("DE-21-58", "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Politikwissenschaft");
            this.put("DE-21-62", "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Osteurop\u00E4ische Geschichte und Landeskunde");
            this.put("DE-21-63", "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Tropenmedizin");
            this.put("DE-21-64", "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Geschichtliche Landeskunde und Historische Hilfswissenschaften");
            this.put("DE-21-65", "Universit\u00E4t T\u00FCbingen, Universit\u00E4ts-Apotheke");
            this.put("DE-21-74", "Universit\u00E4t T\u00FCbingen, Zentrum f\u00FCr Informations-Technologie");
            this.put("DE-21-78", "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Medizinische Biometrie");
            this.put("DE-21-81", "Universit\u00E4t T\u00FCbingen, Inst. f. Astronomie und Astrophysik/Abt. Geschichte der Naturwiss.");
            this.put("DE-21-85", "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Soziologie");
            this.put("DE-21-86", "Universit\u00E4t T\u00FCbingen, Zentrum f\u00FCr Datenverarbeitung");
            this.put("DE-21-89", "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Arbeits- und Sozialmedizin");
            this.put("DE-21-92", "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Gerichtliche Medizin");
            this.put("DE-21-93", "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Ethik und Geschichte der Medizin");
            this.put("DE-21-95", "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Hirnforschung");
            this.put("DE-21-98", "Universit\u00E4t T\u00FCbingen, Fachbibliothek Mathematik und Physik / Bereich Physik");
            this.put("DE-21-99",
                    "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Ur- und Fr\u00FChgeschichte und Arch\u00E4ologie des Mittelalters, Abteilung f\u00FCr \u00E4ltere Urgeschichteund Quart\u00E4r\u00F6kologie");
            this.put("DE-21-106", "Universit\u00E4t T\u00FCbingen, Seminar f\u00FCr Zeitgeschichte");
            this.put("DE-21-108", "Universit\u00E4t T\u00FCbingen, Fakult\u00E4tsbibliothek Neuphilologie");
            this.put("DE-21-109", "Universit\u00E4t T\u00FCbingen, Asien-Orient-Institut, Abteilung f\u00FCr Sinologie und Koreanistik");
            this.put("DE-21-110", "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Kriminologie");
            this.put("DE-21-112", "Universit\u00E4t T\u00FCbingen, Fakult\u00E4t f\u00FCr Biologie, Bibliothek");
            this.put("DE-21-116", "Universit\u00E4t T\u00FCbingen, Zentrum f\u00FCr Molekularbiologie der Pflanzen, Forschungsgruppe Pflanzenbiochemie");
            this.put("DE-21-117", "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Medizinische Informationsverarbeitung");
            this.put("DE-21-118", "Universit\u00E4t T\u00FCbingen, Universit\u00E4ts-Archiv");
            this.put("DE-21-119", "Universit\u00E4t T\u00FCbingen, Wilhelm-Schickard-Institut f\u00FCr Informatik");
            this.put("DE-21-120", "Universit\u00E4t T\u00FCbingen, Asien-Orient-Institut, Abteilung f\u00FCr Japanologie");
            this.put("DE-21-121", "Universit\u00E4t T\u00FCbingen, Internationales Zentrum f\u00FCr Ethik in den Wissenschaften");
            this.put("DE-21-123", "Universit\u00E4t T\u00FCbingen, Medizinbibliothek");
            this.put("DE-21-124", "Universit\u00E4t T\u00FCbingen, Institut f. Medizinische Virologie und Epidemiologie d. Viruskrankheiten");
            this.put("DE-21-126", "Universit\u00E4t T\u00FCbingen, Institut f\u00FCr Medizinische Mikrobiologie und Hygiene");
            this.put("DE-21-203", "Universit\u00E4t T\u00FCbingen, Sammlung Werner Schweikert - Archiv der Weltliteratur");
            this.put("DE-21-205", "Universit\u00E4t T\u00FCbingen, Zentrum f\u00FCr Islamische Theologie");
            this.put("DE-Frei85", "Freiburg MPI Ausl\u00E4nd.Recht, Max-Planck-Institut f\u00FCr ausl\u00E4ndisches und internationales Strafrecht");
            this.put("DE-2619", "KrimDok - kriminologische Bibliographie");
        }
    };

    // Map used by getPhysicalType().
    protected static final Map<String, String> phys_code_to_full_name_map;

    static {
        Map<String, String> tempMap = new HashMap<>();
        tempMap.put("arbtrans", "Transparency");
        tempMap.put("blindendr", "Braille");
        tempMap.put("bray", "Blu-ray Disc");
        tempMap.put("cdda", "CD");
        tempMap.put("ckop", "Microfiche");
        tempMap.put("cofz", "Online Resource");
        tempMap.put("crom", "CD-ROM");
        tempMap.put("dias", "Slides");
        tempMap.put("disk", "Diskette");
        tempMap.put("druck", "Printed Material");
        tempMap.put("dvda", "Audio DVD");
        tempMap.put("dvdr", "DVD-ROM");
        tempMap.put("dvdv", "Video DVD");
        tempMap.put("gegenst", "Physical Object");
        tempMap.put("handschr", "Longhand Text");
        tempMap.put("kunstbl", "Artistic Works on Paper");
        tempMap.put("lkop", "Mircofilm");
        tempMap.put("medi", "Multiple Media Types");
        tempMap.put("scha", "Record");
        tempMap.put("skop", "Microform");
        tempMap.put("sobildtt", "Audiovisual Carriers");
        tempMap.put("soerd", "Carriers of Other Electronic Data");
        tempMap.put("sott", "Carriers of Other Audiodata");
        tempMap.put("tonbd", "Audiotape");
        tempMap.put("tonks", "Audiocasette");
        tempMap.put("vika", "Videocasette");
        phys_code_to_full_name_map = Collections.unmodifiableMap(tempMap);
    }

    // Must match constants in FullTextCache.h
    protected final static Map<String, String> text_type_to_description_map = new TreeMap<String, String>() {
        {
            this.put("1", "Fulltext");
            this.put("2", "Table of Contents");
            this.put("4", "Abstract");
            this.put("8", "Summary");
            this.put("16", "List of References");
            this.put("0", "Unknown");
        }
    };


    protected static ConcurrentLimitedHashMap<String, Set<String>> isilsCache = new ConcurrentLimitedHashMap<>(100);
    protected static ConcurrentLimitedHashMap<String, Collection<Collection<Topic>>> collectedTopicsCache = new ConcurrentLimitedHashMap<>(100);
    protected static ConcurrentLimitedHashMap<String, JSONArray> fulltextServerHitsCache = new ConcurrentLimitedHashMap<>(100);
    protected static final String fullHostName;
    static {
        String tmp = ""; // Needed for syntactical reasons
        try {
            tmp = InetAddress.getLocalHost().getHostName();
        } catch(java.net.UnknownHostException e) {
            throw new RuntimeException ("Could not determine Hostname", e);
        }
        fullHostName = tmp;
    }


    protected String getTitleFromField(final DataField titleField) {
        if (titleField == null)
            return null;

        final String titleA = (titleField.getSubfield('a') == null) ? null : titleField.getSubfield('a').getData();
        final String titleB = (titleField.getSubfield('b') == null) ? null : titleField.getSubfield('b').getData();
        if (titleA == null && titleB == null)
            return null;

        final StringBuilder completeTitle = new StringBuilder();
        if (titleA == null)
            completeTitle.append(DataUtil.cleanData(titleB));
        else if (titleB == null || !titleA.endsWith(":"))
            completeTitle.append(DataUtil.cleanData(titleA));
        else { // Neither titleA nor titleB are null.
            completeTitle.append(DataUtil.cleanData(titleA));
            if (!titleB.startsWith(" = "))
                completeTitle.append(" : ");
            completeTitle.append(DataUtil.cleanData(titleB));
        }

        final String titleN = (titleField.getSubfield('n') == null) ? null : titleField.getSubfield('n').getData();
        if (titleN != null) {
            completeTitle.append(' ');
            completeTitle.append(DataUtil.cleanData(titleN));
        }
        return completeTitle.toString();
    }

    /**
     * Determine Record Title
     *
     * @param record
     *            the record
     * @return String nicely formatted title
     */
    public String getMainTitle(final Record record) {
        final DataField mainTitleField = (DataField) record.getVariableField("245");
        return getTitleFromField(mainTitleField);
    }

    public Set<String> getOtherTitles(final Record record) {
        final List<VariableField> otherTitleFields = record.getVariableFields("246");

        final Set<String> otherTitles = new TreeSet<>();
        for (final VariableField otherTitleField : otherTitleFields) {
            final DataField dataField = (DataField) otherTitleField;
            if (dataField.getIndicator1() == '3' && dataField.getIndicator2() == '0')
                continue;
            final String otherTitle = getTitleFromField((DataField) otherTitleField);
            if (otherTitle != null)
                otherTitles.add(otherTitle);
        }

        return otherTitles;
    }


    /**
     * Get the local 689 topics
     * LOK = Field |0 689 = Subfield |a Imperialismus = Subfield with local
     * subject
     *
     * @param record
     *            the record
     * @return Set topics
     */

     public Set<String> getLocal689Topics(final Record record) {
         final Set<String> topics = new TreeSet<>();
         for (final VariableField variableField : record.getVariableFields("LOK")) {
             final DataField lokfield = (DataField) variableField;
             final Subfield subfield0 = lokfield.getSubfield('0');
             if (subfield0 == null || !subfield0.getData().equals("689  ")) {
                 continue;
             }
             for (final Subfield subfieldA : lokfield.getSubfields('a')) {
                 if (subfieldA != null && subfieldA.getData() != null && subfieldA.getData().length() > 2) {
                     topics.add(subfieldA.getData());
                 }
             }
         }
         return topics;
     }


    /**
     * get the local subjects from LOK-tagged fields and get subjects from 936k
     * and 689a subfields
     * <p/>
         *
     * @param record
     *            the record
     * @return Set of local subjects
     */
    public Set<String> getAllTopics(final Record record) {
        final Set<String> topics = getAllSubfieldsBut(record, "600:610:611:630:650:653:656:689a:936a", "02");
        topics.addAll(getLocal689Topics(record));
        return topics;
    }

    /**
     * Hole das Sachschlagwort aus 689|a (wenn 689|d != z oder f)
     * und füge auch Schlagwörter aus LOK 689 ein
     *
     * @param record
     *            the record
     * @return Set "topic_facet"
     */
    public Set<String> getFacetTopics(final Record record) {
        final Set<String> result = getAllSubfieldsBut(record, "600x:610x:611x:630x:648x:650a:650x:651x:655x", "0");
        String topic_string;
        // Check 689 subfield a and d
        final List<VariableField> fields = record.getVariableFields("689");
        if (fields != null) {
            DataField dataField;
            for (final VariableField variableField : fields) {
                dataField = (DataField) variableField;
                final Subfield subfieldD = dataField.getSubfield('d');
                if (subfieldD == null) {
                    continue;
                }
                topic_string = subfieldD.getData().toLowerCase();
                if (topic_string.equals("f") || topic_string.equals("z")) {
                    continue;
                }
                final Subfield subfieldA = dataField.getSubfield('a');
                if (subfieldA != null) {
                    result.add(subfieldA.getData());
                }
            }
        }
        result.addAll(getLocal689Topics(record));
        return result;
    }



    // Map used by getPhysicalType().
    protected static final Map<String, String> code_to_material_type_map;

    // Entries are from http://swbtools.bsz-bw.de/cgi-bin/k10plushelp.pl?cmd=kat&val=4960&kattype=Standard#$3
    static {
        Map<String, String> tempMap = new TreeMap<>();
        tempMap.put("01", "Inhaltstext");
        tempMap.put("02", "Kurzbeschreibung");
        tempMap.put("03", "Ausführliche Beschreibung");
        tempMap.put("04", "Inhaltsverzeichnis");
        tempMap.put("07", "Rezension");
        tempMap.put("08", "Rezension (Auszug)");
        tempMap.put("09", "Werbliche Überschrift");
        tempMap.put("10", "Zitat aus einer vorhergehenden Besprechung");
        tempMap.put("11", "Autorenkommentar");
        tempMap.put("12", "Beschreibung für Leser");
        tempMap.put("13", "Autorenbiografie");
        tempMap.put("14", "Beschreibung für Lesegruppen");
        tempMap.put("15", "Fragen für Lesegruppen ");
        tempMap.put("16", "Konkurrierende Titel");
        tempMap.put("17", "Klappentext");
        tempMap.put("18", "Umschlagtext");
        tempMap.put("23", "Auszug");
        tempMap.put("24", "Erstes Kapitel");
        tempMap.put("25", "Beschreibung für Marketing");
        tempMap.put("26", "Pressetext");
        tempMap.put("27", "Beschreibung für die Lizenzabteilung");
        tempMap.put("28", "Beschreibung für Lehrer/Erzieher");
        tempMap.put("30", "Unveröffentlichter Kommentar");
        tempMap.put("31", "Beschreibung für Buchhändler");
        tempMap.put("32", "Beschreibung für Bibliotheken");
        tempMap.put("33", "Einführung/Vorwort");
        tempMap.put("34", "Volltext");
        tempMap.put("90", "Objektabbildung");           // GBV extension
        tempMap.put("91", "Objektabbildung Thumbnail"); // GBV extension
        tempMap.put("92", "Schlüsselseiten");           // GBV extension
        tempMap.put("93", "Cover");                     // GBV extension
        code_to_material_type_map = Collections.unmodifiableMap(tempMap);
    }



    /**
     * Returns either a Set<String> of parent (URL + colon + material type).
     * URLs are taken from 856$u and material types from 856$3, 856$z or 856$x.
     * 856 fields with indicators 4 0 are definitely fulltexts
     * For missing type subfields the text "Unbekanntes Material" will be used.
     * Furthermore 024$2 will be checked for "doi". If we find this we generate
     * a URL with a DOI resolver from the DOI in 024$a and set the
     * "material type" to "DOI Link".
     *
     * @param record
     *            the record
     * @return A, possibly empty, Set<String> containing the URL/material-type
     *         pairs.
     */
    public Set<String> getUrlsAndMaterialTypes(final Record record) {
        final Set<String> nonUnknownMaterialTypeURLs = new HashSet<String>();
        final Map<String, Set<String>> materialTypeToURLsMap = new TreeMap<String, Set<String>>();
        final Set<String> urls_and_material_types = new LinkedHashSet<>();

        for (final VariableField variableField : record.getVariableFields("856")) {
            final DataField field = (DataField) variableField;
            final Subfield subfield_3 = getFirstNonEmptySubfield(field, '3');
            final Subfield subfield_z = getFirstNonEmptySubfield(field, 'z');
            final Subfield subfield_x = getFirstNonEmptySubfield(field, 'x');
            final Subfield subfield_y = getFirstNonEmptySubfield(field, 'y');
            String materialType;
            String materialLicence = "";
            final char indicator1 = field.getIndicator1();
            final char indicator2 = field.getIndicator2();
            // The existence of subfield 3 == Volltext or Indicators 4 0 means full text (c.f. https://github.com/ubtue/tuefind/issues/1782)
            // Indicator 4 1 can also contain fulltext but this then must be
            // stated $y and is thus addressed by the general evaluation case
            if (indicator1 == '4' && indicator2 == '0') {
                materialType = "Volltext";
                if (subfield_z != null)
                    materialLicence = subfield_z.getData();
                else if (subfield_x != null)
                    materialLicence = subfield_x.getData();
            } else if (subfield_3 != null) {
                materialType =  subfield_3.getData();
                if (code_to_material_type_map.containsKey(materialType))
                    materialType = code_to_material_type_map.get(materialType);
                if (subfield_z != null)
                    materialLicence = subfield_z.getData();
                else if (subfield_x != null)
                    materialLicence = subfield_x.getData();
            } else {
                if (subfield_z != null)
                    materialType = subfield_z.getData();
                else if (subfield_y != null)
                    materialType = subfield_y.getData();
                else if (subfield_x != null)
                    materialType = subfield_x.getData();
                else
                    materialType = UNKNOWN_MATERIAL_TYPE;

                if (code_to_material_type_map.containsKey(materialType))
                    materialType = code_to_material_type_map.get(materialType);
            }

            if (!materialLicence.isEmpty())
                materialType = materialType + " (" + materialLicence + ")";

            for (final Subfield subfield_u : field.getSubfields('u')) {
                Set<String> URLs = materialTypeToURLsMap.get(materialType);
                if (URLs == null) {
                    URLs = new HashSet<String>();
                    materialTypeToURLsMap.put(materialType, URLs);
                }

                final String rawLink = subfield_u.getData();
                final String link;
                if (rawLink.startsWith("urn:"))
                    link = "https://nbn-resolving.org/" + rawLink;
                else if (rawLink.startsWith("http://nbn-resolving.de"))
                    // Replace HTTP w/ HTTPS.
                    link = "https://nbn-resolving.org/" + rawLink.substring(23);
                else if (rawLink.startsWith("http://nbn-resolving.org"))
                    // Replace HTTP w/ HTTPS.
                    link = "https://nbn-resolving.org/" + rawLink.substring(24);
                else
                    link = rawLink;
                URLs.add(link);
                if (!materialType.equals(UNKNOWN_MATERIAL_TYPE))
                    nonUnknownMaterialTypeURLs.add(link);
            }
        }

        // Remove duplicates while favouring SWB and, if not present, DNB links:
        for (final String material_type : materialTypeToURLsMap.keySet()) {
            if (material_type.equals(UNKNOWN_MATERIAL_TYPE)) {
                for (final String url : materialTypeToURLsMap.get(material_type)) {
                    if (!nonUnknownMaterialTypeURLs.contains(url)) {
                        urls_and_material_types.add(url + ":" + UNKNOWN_MATERIAL_TYPE);
                    }
                }
            } else {
                // Locate SWB and DNB URLs, if present:
                String preferredURL = null;
                for (final String url : materialTypeToURLsMap.get(material_type)) {
                    if (url.startsWith("http://swbplus.bsz-bw.de")) {
                        preferredURL = url;
                        break;
                    } else if (url.startsWith("http://d-nb.info"))
                        preferredURL = url;
                }


                if (preferredURL != null)
                    urls_and_material_types.add(preferredURL + ":" + material_type);
                else { // Add the kitchen sink.
                    for (final String url : materialTypeToURLsMap.get(material_type))
                        urls_and_material_types.add(url + ":" + material_type);
                }
            }
        }

        return urls_and_material_types;
    }

    protected final static Pattern PPN_EXTRACTION_PATTERN = Pattern.compile("^\\(DE-627\\)(.+)$");

    /** @return A PPN or null if we did not find one. */
    String getPPNFromWSubfield(final DataField field) {
        for (final Subfield wSubfield : field.getSubfields('w')) {
            final Matcher matcher = PPN_EXTRACTION_PATTERN.matcher(wSubfield.getData());
            if (matcher.matches())
                return matcher.group(1);
        }

        return null;
    }

    /**
     * Returns a Set<String> of (parent ID + colon + parent title + optional volume). Only
     * ID's w/o titles will not be returned.
     *
     * @param record
     *            the record
     * @return A, possibly empty, Set<String> containing the ID/title(/volume) pairs and triples.
     */
    public Set<String> getContainerIdsWithTitles(final Record record) {
        final Set<String> containerIdsTitlesAndOptionalVolumes = new TreeSet<>();

        for (final String tag : new String[] { "773", "800", "810", "830" }) {
            for (final VariableField variableField : record.getVariableFields(tag)) {
                final DataField field = (DataField) variableField;
                final Subfield titleSubfield = getFirstNonEmptySubfield(field, 't', 'a');
                final Subfield volumeSubfield = field.getSubfield('v');
                final Subfield idSubfield = field.getSubfield('w');

                if (titleSubfield == null || idSubfield == null)
                    continue;

                final String parentId = getPPNFromWSubfield(field);
                if (parentId == null)
                    continue;

                containerIdsTitlesAndOptionalVolumes
                        .add(parentId + (char) 0x1F + titleSubfield.getData()
                             + (char) 0x1F + (volumeSubfield == null ? "" : volumeSubfield.getData()));
                // We want precisely one superior work at each level
                // So, abort if we found one
                return containerIdsTitlesAndOptionalVolumes;
            }
        }
        return containerIdsTitlesAndOptionalVolumes;
    }

    protected final static char SUBFIELD_SEPARATOR = (char)0x1F;

    public String getSortableAuthorUnicode(final Record record, final String tagList, final String acceptWithoutRelator,
                                           final String relatorConfig)
    {
        String author = creatorTools.getFirstAuthorFilteredByRelator(record, tagList,
                                                              acceptWithoutRelator,
                                                              relatorConfig);

        return normalizeSortableString(author);
    }


    /**
     * @param record
     *            the record
     * @param fieldnums
     * @return
     */
    public Set<String> getSuperMP(final Record record, final String fieldnums) {
        final Set<String> retval = new LinkedHashSet<>();
        final HashMap<String, String> resvalues = new HashMap<>();
        final HashMap<String, Integer> resscores = new HashMap<>();

        String value;
        String id;
        Integer score;
        Integer cscore;
        String fnum;
        String fsfc;

        final String[] fields = fieldnums.split(":");
        for (final String field : fields) {

            fnum = field.replaceAll("[a-z]+$", "");
            fsfc = field.replaceAll("^[0-9]+", "");

            final List<VariableField> fs = record.getVariableFields(fnum);
            if (fs == null) {
                continue;
            }
            for (final VariableField variableField : fs) {
                final DataField dataField = (DataField) variableField;
                final Subfield subfieldW = dataField.getSubfield('w');
                if (subfieldW == null) {
                    continue;
                }
                final Subfield fsubany = dataField.getSubfield(fsfc.charAt(0));
                if (fsubany == null) {
                    continue;
                }
                value = fsubany.getData().trim();
                id = subfieldW.getData().replaceAll("^\\([^\\)]+\\)", "");

                // Count number of commas in "value":
                score = value.length() - value.replace(",", "").length();

                if (resvalues.containsKey(id)) {
                    cscore = resscores.get(id);
                    if (cscore > score) {
                        continue;
                    }
                }
                resvalues.put(id, value);
                resscores.put(id, score);
            }
        }

        for (final String key : resvalues.keySet()) {
            value = "(" + key + ")" + resvalues.get(key);
            retval.add(value);
        }

        return retval;
    }

    /**
     * get the ISILs from LOK-tagged fields
     * <p/>
     * Typical LOK-Section below a Marc21 - Title-Set of a record: LOK |0 000
     * xxxxxnu a22 zn 4500 LOK |0 001 000001376 LOK |0 003 DE-576 LOK |0 004
     * 000000140 LOK |0 005 20020725000000 LOK |0 008
     * 020725||||||||||||||||ger||||||| LOK |0 014 |a 000001368 |b DE-576 LOK |0
     * 541 |e 76.6176 LOK |0 852 |a DE-Sp3 LOK |0 852 1 |c B IV 529 |9 00
     * <p/>
     * LOK = Field |0 852 = Subfield |a DE-Sp3 = Subfield with ISIL
     *
     * @param record
     *            the record
     * @return Set of isils
     */
    public Set<String> getIsils(final Record record) {
        return isilsCache.computeIfAbsent(record.getControlNumber(), value -> {
            final Set<String> isils = new LinkedHashSet<>();
            final List<VariableField> fields = record.getVariableFields("LOK");
            if (fields != null) {
                for (final VariableField variableField : fields) {
                    final DataField lokfield = (DataField) variableField;
                    final Subfield subfield0 = lokfield.getSubfield('0');
                    if (subfield0 == null || !subfield0.getData().startsWith("852")) {
                        continue;
                    }
                    final Subfield subfieldA = lokfield.getSubfield('a');
                    if (subfieldA != null) {
                        isils.add(subfieldA.getData());
                    }
                }
            }

            if (isils.isEmpty()) { // Nothing worked!
                isils.add("Unknown");
            }
            return isils;
        });
    }

    public Set<String> getJournalIssue(final Record record) {
        final DataField _773Field = (DataField)record.getVariableField("773");
        if (_773Field == null)
            return null;


        Subfield titleSubfield = _773Field.getSubfield('t');
        if (titleSubfield == null)
            titleSubfield = _773Field.getSubfield('a');

        if (titleSubfield == null)
            return null;

        final Set<String> subfields = new LinkedHashSet<String>();
        subfields.add(titleSubfield.getData());

        final Subfield gSubfield = _773Field.getSubfield('g');
        if (gSubfield != null)
            subfields.add(gSubfield.getData());

        final List<Subfield> wSubfields = _773Field.getSubfields('w');
        for (final Subfield wSubfield : wSubfields) {
            final String subfieldContents = wSubfield.getData();
            if (subfieldContents.startsWith(ISIL_PREFIX_K10PLUS))
                subfields.add(subfieldContents);
        }

        return subfields;
    }

    /**
     * @param record
     *            the record
     * @return
     */
    public String isAvailableInTuebingen(final Record record) {
        return Boolean.toString(!record.getVariableFields("SIG").isEmpty());
    }

    /**
     * get the collections from LOK-tagged fields
     * <p/>
     * Typical LOK-Section below a Marc21 - Title-Set of a record: LOK |0 000
     * xxxxxnu a22 zn 4500 LOK |0 001 000001376 LOK |0 003 DE-576 LOK |0 004
     * 000000140 LOK |0 005 20020725000000 LOK |0 008
     * 020725||||||||||||||||ger||||||| LOK |0 014 |a 000001368 |b DE-576 LOK |0
     * 541 |e 76.6176 LOK |0 852 |a DE-Sp3 LOK |0 852 1 |c B IV 529 |9 00
     * <p/>
     * LOK = Field |0 852 = Subfield |a DE-Sp3 = Subfield with ISIL
     *
     * @param record
     *            the record
     * @return Set of collections
     */
    public Set<String> getCollections(final Record record) {
        final Set<String> isils = getIsils(record);
        final Set<String> collections = new HashSet<>();
        for (final String isil : isils) {
            final String collection = isil_to_department_map.get(isil);
            if (collection != null) {
                collections.add(collection);
            } else {
                throw new IllegalArgumentException("Unknown ISIL: " + isil);
            }
        }

        if (collections.isEmpty())
            collections.add("Unknown");

        return collections;
    }

    /**
     * @param record
     *            the record
     */
    public String getInstitution(final Record record) {
        final Set<String> collections = getCollections(record);
        return collections.iterator().next();
    }

    protected static boolean isValidMonthCode(final String month_candidate) {
        try {
            final int month_code = Integer.parseInt(month_candidate);
            return month_code >= 1 && month_code <= 12;
        } catch (NumberFormatException e) {
            return false;
        }
    }

    /**
     * @param record
     *            the record
     */
    public String getTueLocalIndexedDate(final Record record) {
        for (final VariableField variableField : record.getVariableFields("LOK")) {
            final DataField lokfield = (DataField) variableField;
            final List<Subfield> subfields = lokfield.getSubfields();
            final Iterator<Subfield> subfieldsIter = subfields.iterator();
            while (subfieldsIter.hasNext()) {
                Subfield subfield = subfieldsIter.next();
                char formatCode = subfield.getCode();

                String dataString = subfield.getData();
                if (formatCode != '0' || !dataString.startsWith("938") || !subfieldsIter.hasNext()) {
                    continue;
                }

                subfield = subfieldsIter.next();
                formatCode = subfield.getCode();
                if (formatCode != 'a') {
                    continue;
                }

                dataString = subfield.getData();
                if (dataString.length() != 4) {
                    continue;
                }

                final String sub_year_text = dataString.substring(0, 2);
                final int sub_year = Integer.parseInt("20" + sub_year_text);
                final int current_year = Calendar.getInstance().get(Calendar.YEAR);
                final String year;
                if (sub_year > current_year) {
                    // It is from the last century
                    year = "19" + sub_year_text;
                } else {
                    year = "20" + sub_year_text;
                }

                final String month = dataString.substring(2, 4);
                if (!isValidMonthCode(month)) {
                    logger.severe("in getTueLocalIndexedDate: bad month in LOK 938 field: " + month
                                  + "! (PPN: " + record.getControlNumber() + ")");
                    return null;
                }
                // If we use a fixed day we underrun a plausible span of time for the new items
                // but we have to make sure that no invalid date is generated that leads to an import problem
                return year + "-" + month + "-" +
                       String.format("%02d",
                       isCurrentYearAndMonth(year, month) ? getCurrentDayOfMonth() : getLastDayForYearAndMonth(year, month))
                       + "T11:00:00.000Z";
            }
        }
        return null;
    }


    /*
     * Check whether given year and date is equivalent to current year and date
     */
    boolean isCurrentYearAndMonth(final String year, final String month) {
        Calendar calendar = Calendar.getInstance();
        return (Integer.valueOf(year) == calendar.get(Calendar.YEAR)) &&
               (Integer.valueOf(month) == calendar.get(Calendar.MONTH) + 1);
    }


    /*
     * Get day of current month
     */
    int getCurrentDayOfMonth() {
        return Calendar.getInstance().get(Calendar.DAY_OF_MONTH);
    }


    /*
     * Get last day of a given month for a given year
     */
    int getLastDayForYearAndMonth(final String year, final String month) {
        return YearMonth.of(Integer.valueOf(year), Integer.valueOf(month)).atEndOfMonth().getDayOfMonth();
    }


    /**
     * @param record
     *            the record
     */
    public String getPageRange(final Record record) {
        final String field_value = getFirstSubfieldValue(record, "936", 'h');
        if (field_value == null)
            return null;

        final Matcher matcher1 = PAGE_RANGE_PATTERN1.matcher(field_value);
        if (matcher1.matches())
            return matcher1.group(1) + "-" + matcher1.group(2);

        final Matcher matcher2 = PAGE_RANGE_PATTERN2.matcher(field_value);
        if (matcher2.matches())
            return matcher2.group(1) + "-" + matcher2.group(2);

        final Matcher matcher3 = PAGE_RANGE_PATTERN3.matcher(field_value);
        if (matcher3.matches())
            return matcher3.group(1) + "-";

        return null;
    }

    /**
     * Return all identifiers from 024. (Ind1 must always be 7)
     *
     * @param record            The record
     * @param subfield2Value    The value of subfield 2, e.g. "urn", "doi", "hdl", ...
     * @param resultPrefix      A prefix which will be prepended to each result entry.
     */
    protected static Set<String> getIdentifiersFrom024(final Record record,
                                                     final String subfield2Value,
                                                     final String resultPrefix)
    {
        final Set<String> result = new TreeSet<>();

        for (final VariableField variableField : record.getVariableFields("024")) {
            final DataField field = (DataField) variableField;
            if (field.getIndicator1() != '7')
                continue;

            final Subfield subfield_2 = field.getSubfield('2');
            if (subfield_2 == null || !subfield_2.getData().equals(subfield2Value))
                continue;

            final Subfield subfield_a = field.getSubfield('a');
            if (subfield_a != null)
                result.add(resultPrefix + subfield_a.getData());
        }

        return result;
    }

    protected static Set<String> getURNs(final Record record) {
        return getURNs(record, "");
    }

    protected static Set<String> getURNs(final Record record, final String resultPrefix) {
        // From 2020-01-07 on, URNs will only be exported in 024.
        final Set<String> result = getIdentifiersFrom024(record, "urn", resultPrefix);

        // Also keep 856 as fallback if something goes wrong:
        for (final VariableField variableField : record.getVariableFields("856")) {
            final DataField field = (DataField) variableField;

            for (final Subfield subfield_u : field.getSubfields('u')) {
                final String rawLink = subfield_u.getData();
                if (rawLink.startsWith("http://nbn-resolving.de/urn:nbn:de"))
                    result.add(resultPrefix + rawLink.substring("http://nbn-resolving.de/".length()));
                else if (rawLink.startsWith("urn:nbn:de"))
                    result.add(resultPrefix + rawLink);
                else if (rawLink.startsWith("https://nbn-resolving.de/urn:nbn:de"))
                    result.add(resultPrefix + rawLink.substring("https://nbn-resolving.de/".length()));
            }
        }

        return result;
    }

    protected static Set<String> getDOIs(final Record record) {
        return getDOIs(record, "");
    }

    protected static Set<String> getDOIs(final Record record, final String resultPrefix) {
        return getIdentifiersFrom024(record, "doi", resultPrefix);
    }

    protected static Set<String> getHandles(final Record record) {
        return getHandles(record, "");
    }

    protected static Set<String> getHandles(final Record record, final String resultPrefix) {
        // From 2020-01-07 on, Handles will only be exported in 024.
        final Set<String> result = getIdentifiersFrom024(record, "hdl", resultPrefix);

        // Also keep 856 as fallback if something goes wrong:
        for (final VariableField variableField : record.getVariableFields("856")) {
            final DataField field = (DataField) variableField;

            for (final Subfield subfield_u : field.getSubfields('u')) {
                final String rawLink = subfield_u.getData();
                final int index = rawLink.indexOf("http://hdl.handle.net/", 0);
                if (index >= 0) {
                    final String link = rawLink.substring("http://hdl.handle.net/".length());
                    result.add(resultPrefix + link);
                }
            }
        }

        return result;
    }

    /**
     * Returns a Set<String> of Persistent Identifiers, e.g. DOIs and URNs
     * e.g.
     *  DOI:<doi1>
     *  URN:<urn1>
     *  URN:<urn2>
     *  HDL:<handle1>
     */
    public Set<String> getTypesAndPersistentIdentifiers(final Record record) {
        final Set<String> result = getDOIs(record, "DOI:");
        result.addAll(getURNs(record, "URN:"));
        result.addAll(getHandles(record, "HDL:"));
        return result;
    }

    /**
     * @param record
     *            the record
     */
    public String getContainerYear(final Record record) {
        final String field_value = getFirstSubfieldValue(record, "936", 'u', 'w', 'j');
        if (field_value == null)
            return null;

        final Matcher matcher = VALID_FOUR_DIGIT_YEAR_PATTERN.matcher(field_value);
        return matcher.matches() ? matcher.group(1) : null;
    }

    /**
     * @param record
     *            the record
     */
    public String getContainerVolume(final Record record) {
        final String field_value = getFirstSubfieldValue(record, "936", 'd');
        if (field_value == null)
            return null;

        final Matcher matcher = VOLUME_PATTERN.matcher(field_value);
        return matcher.matches() ? matcher.group(1) : null;
    }

    public Set<String> map935b(final Record record, final Map<String, String> map) {
        final Set<String> results = new TreeSet<>();
        String last_unmappable_physical_code = null;
        for (final DataField data_field : record.getDataFields()) {
            if (!data_field.getTag().equals("935"))
                continue;

            final List<Subfield> physical_code_subfields = data_field.getSubfields('b');
            for (final Subfield physical_code_subfield : physical_code_subfields) {
                final String physical_code = physical_code_subfield.getData();
                if (map.containsKey(physical_code))
                    results.add(map.get(physical_code));
                else
                    last_unmappable_physical_code = physical_code;
            }
        }

        if (results.isEmpty() && last_unmappable_physical_code != null)
            logger.severe("in TueFindBiblio.map935b: can't map \"" + last_unmappable_physical_code + "\"!");

        return results;
    }

    /**
     * @param record
     *            the record
     */
    public Set<String> getPhysicalType(final Record record) {
        return map935b(record, phys_code_to_full_name_map);
    }

    protected boolean isHonoree(final List<Subfield> subfieldFields4) {
        for (final Subfield subfield4 : subfieldFields4) {
            if (subfield4.getData().equals("hnr"))
                return true;
        }

        return false;
    }

    protected Set<String> addHonourees(final Record record, final Set<String> values, String lang) {
        for (final VariableField variableField : record.getVariableFields("700")) {
            final DataField dataField = (DataField) variableField;
            final List<Subfield> subfieldFields4 = dataField.getSubfields('4');
            if (subfieldFields4 != null) {
                for (final Subfield subfield4 : subfieldFields4) {
                    if (subfield4.getData().equals("hnr")) {
                        final List<Subfield> subfields = dataField.getSubfields();
                        StringBuilder honouree = new StringBuilder();
                        for (Subfield subfield : subfields) {
                            if (Character.isDigit(subfield.getCode()))
                                continue;
                            else if (subfield.getCode() == 'a')
                                honouree.append(translateTopic(subfield.getData(), lang));
                            else if (subfield.getCode() == 'b' || subfield.getCode() == 'c') {
                                honouree.append(", ");
                                honouree.append(translateTopic(subfield.getData(), lang));
                            } else if (subfield.getCode() == 'd') {
                                honouree.append(" ");
                                honouree.append(subfield.getData());
                            }
                        }
                        values.add(honouree.toString());
                        break;
                    }
                }
            }
        }

        return values;
    }

    public Set<String> getValuesOrUnassigned(final Record record, final String fieldSpecs) {
        final Set<String> values = SolrIndexer.instance().getFieldList(record, fieldSpecs);
        if (values.isEmpty()) {
            values.add(UNASSIGNED_STRING);
        }
        return values;
    }


    /**
     * Parse the field specifications
     */
    protected Map<String, String> parseTopicSeparators(String separatorSpec) {
        final Map<String, String> separators = new HashMap<String, String>();

        // Split the string at unescaped ":"
        // See
        // http://stackoverflow.com/questions/18677762/handling-delimiter-with-escape-characters-in-java-string-split-method
        // (20160416)

        final String fieldDelim = ":";
        final String subfieldDelim = "$";
        final String esc = "\\";
        final String regexColon = "(?<!" + Pattern.quote(esc) + ")" + Pattern.quote(fieldDelim);
        final String regexSubfield = "(?<!" + Pattern.quote(esc) + ")" + Pattern.quote(subfieldDelim) + "([0-9][a-z]|[a-zA-Z])(.*)";
        final Pattern SUBFIELD_PATTERN = Pattern.compile(regexSubfield);
        String[] subfieldSeparatorList = separatorSpec.split(regexColon);
        for (String s : subfieldSeparatorList) {
            // Create map of subfields and separators
            Matcher subfieldMatcher = SUBFIELD_PATTERN.matcher(s);

            // Extract the subfield
            if (subfieldMatcher.find()) {
                // Get $ and the character
                String subfield = subfieldMatcher.group(1);
                String separatorToUse = subfieldMatcher.group(2);
                separators.put(subfield, separatorToUse.replace(esc, ""));
            }
            // Use an expression that does not specify a subfield as default
            // value
            else {
                separators.put("default", s.replace(esc, ""));
            }
        }
        return separators;
    }

    /*
     * Helper Class for passing symbol pairs in bracket directives
     */
     protected class SymbolPair {
        public char opening;
        public char closing;
     }


    /*
     * Function to parse out special forms of separator specs needed to include a term bracketed in symbol pairs
     * e.g. opening and closing parentheses
     * Changes the character arguments
     */
    protected SymbolPair parseBracketDirective(final String separator) {
        final Matcher matcher = BRACKET_DIRECTIVE_PATTERN.matcher(separator);
        if (!matcher.matches())
            throw new IllegalArgumentException("Invalid Bracket Specification");

        final SymbolPair symbolPair = new SymbolPair();
        symbolPair.opening = matcher.group(1).charAt(0);
        symbolPair.closing = matcher.group(2).charAt(0);
        return symbolPair;
    }


    protected Boolean isBracketDirective(final String separator) {
        final Matcher matcher = BRACKET_DIRECTIVE_PATTERN.matcher(separator);
        return matcher.matches();
    }


    protected final static Pattern NUMBER_END_PATTERN = Pattern.compile("([^\\d\\s<>]+)(\\s*<?\\d+(-\\d+)>?$)", Pattern.UNICODE_CHARACTER_CLASS);

    /**
     * Translate a single term to given language if a translation is found
     */
    public String translateTopic(String topic, String langAbbrev) {
        if (langAbbrev.equals("de"))
            return topic;

        Map<String, String> translation_map = getTranslationMap(langAbbrev);
        Matcher numberEndMatcher = NUMBER_END_PATTERN.matcher(topic);

        // Some terms contain slash separated subterms, see whether we can
        // translate them
        if (topic.contains("\\/")) {
            String[] subtopics = topic.split("\\\\/");
            int i = 0;
            for (String subtopic : subtopics) {
                subtopic = subtopic.trim();
                subtopics[i] = (translation_map.get(subtopic) != null) ? translation_map.get(subtopic) : subtopic;
                ++i;
            }
            topic = Utils.join(new HashSet<String>(Arrays.asList(subtopics)), " / ");
        }
        // If we have a topic and a following number, try to separate the word and join it afterwards
        // This is especially important for time informations where we provide special treatment
        else if (numberEndMatcher.find()) {
            String topicText = numberEndMatcher.group(1);
            String numberExtension = numberEndMatcher.group(2);
            if (topicText.equals("Geschichte")) {
                switch (langAbbrev) {
                case "en":
                    topic = "History" + numberExtension;
                    break;
                case "fr":
                    topic = "Histoire" + numberExtension;
                    break;
                }
            } else
                topic = translation_map.get(topicText) != null ? translation_map.get(topicText) + numberExtension
                                                               : topic;
        } else
            topic = (translation_map.get(topic) != null) ? translation_map.get(topic) : topic;

        return topic;
    }

    /**
     * Generate Separator according to specification
     * For some subfields there are standards how the values extracted must be attached to the resulting keyword string
     * Examples are $p must be separated y ".". We must also examine the term itself since there a constructions like
     * $9g:
     */
    public String getSubfieldBasedSeparator(Map<String, String> separators, char subfieldCodeChar, String term) {
        String subfieldCodeString = Character.toString(subfieldCodeChar);
        // In some cases of numeric Subfields we have ':'-delimited subfield of a subfield to remove complexity ;-)
        // i.e. $9 g:xxxx
        subfieldCodeString += Character.isDigit(subfieldCodeChar) ? term.split(":")[0] : "";
        String separator = separators.get(subfieldCodeString) != null ? separators.get(subfieldCodeString)
                                                                      : separators.get("default");

        return separator;
    }

    /**
     * Predicates for choosing only a subset of all standardized subjects
     * In the q-Subfield of 689 standardized subjects we have "z" (time subject), "f" (genre subject), "g" (region subject)
     * Alternatively this is in the d-Subfields
     */

     boolean HasLocalTag(final DataField marcField, final String tag) {
        return marcField.getTag().equals("LOK") && marcField.getSubfield('0') != null &&
              marcField.getSubfield('0').getData().substring(0,3).equals(tag);
    }


    Predicate<DataField> _LOK689IsTimeSubject = (DataField marcField) -> {
        if (!HasLocalTag(marcField, "689"))
            return true;
        Subfield subfield0 = marcField.getSubfield('0');
        if (subfield0 != null && subfield0.getData().substring(0,3).equals("689")) {
            Subfield subfieldA = marcField.getSubfield('a'); //Should be capital
            return (subfieldA != null && subfieldA.getData().equals("z"));
        }
        return false;
    };


    Predicate<DataField> _LOK689IsRegionSubject = (DataField marcField) -> {
        if (!HasLocalTag(marcField, "689"))
            return true;
        Subfield subfield0 = marcField.getSubfield('0');
        if (subfield0 != null && subfield0.getData().substring(0,3).equals("689")) {
            Subfield subfieldA = marcField.getSubfield('a'); //Should be capital
            return (subfieldA != null && subfieldA.getData().equals("g"));
        }
        return false;
    };


    Predicate<DataField> _LOK689IsCorporationSubject = (DataField marcField) -> {
        if (!HasLocalTag(marcField, "689"))
            return true;
        Subfield subfield0 = marcField.getSubfield('0');
        if (subfield0 != null && subfield0.getData().substring(0,3).equals("689")) {
            Subfield subfieldA = marcField.getSubfield('a'); //Should be capital
            return (subfieldA != null && subfieldA.getData().equals("k"));
        }
        return false;
    };



    Predicate<DataField> _LOK689IsOrdinarySubject = (DataField marcField) -> {
        if (!(marcField.getTag().equals("LOK")))
            return false;
        Subfield subfield0 = marcField.getSubfield('0');
        if (subfield0 != null && subfield0.getData().substring(0,3).equals("689")) {
            Subfield subfieldA = marcField.getSubfield('a'); //Should be capital
            return (subfieldA != null && subfieldA.getData().equals("s"));
        }
        return false;
    };


    Predicate<DataField> _689IsGenreSubject = (DataField marcField) -> {
        if (!marcField.getTag().equals("689"))
            return true;
        Subfield subfieldQ = marcField.getSubfield('q');
        return (subfieldQ != null && subfieldQ.getData().equals("f"));
    };


    Predicate<DataField> _689IsRegionSubject = (DataField marcField) -> {
        if (marcField.getTag().equals("LOK"))
            return _LOK689IsRegionSubject.test(marcField);

        // Do not prevent non 689-fields
        if (!marcField.getTag().equals("689")) {
            return true;
}
        Subfield subfieldQ = marcField.getSubfield('q');
        Subfield subfieldD = marcField.getSubfield('d');
        return (subfieldQ != null && subfieldQ.getData().equals("g")) || (subfieldD != null && subfieldD.getData().equals("g"));
    };


    Predicate<DataField> _689IsTimeSubject = (DataField marcField) -> {
        if (marcField.getTag().equals("LOK"))
            return _LOK689IsTimeSubject.test(marcField);

        // Do not prevent non 689-fields
        if (!marcField.getTag().equals("689"))
            return true;
        Subfield subfieldQ = marcField.getSubfield('q');
        Subfield subfieldD = marcField.getSubfield('d');
        return (subfieldQ != null && subfieldQ.getData().equals("z")) || (subfieldD != null && subfieldD.getData().equals("z"));
    };



    Predicate<DataField> _689IsOrdinarySubject = (DataField marcField) -> {
        if (marcField.getTag().equals("LOK"))
            return _LOK689IsOrdinarySubject.test(marcField);

        // Do not prevent non 689-fields
        if (!marcField.getTag().equals("689"))
            return true;

        return (!(_689IsTimeSubject.test(marcField) || _689IsGenreSubject.test(marcField) || _689IsRegionSubject.test(marcField)));
    };


    @Deprecated
    protected void getTopicsCollector(final Record record, String fieldSpec, Map<String, String> separators,
                                    Collection<String> collector, String langAbbrev) {
        getTopicsCollector(record, fieldSpec, separators, collector, langAbbrev, null);
    }


    /**
     * Construct a regular expression from the subfield tags where all character subfields are extracted and for number
     * subfields the subsequent character subSubfield-Code is skipped (e.g. abctnpz9g => a|b|c|t|n|p|z|9 (without the g)
     */
    protected String extractNormalizedSubfieldPatternHelper(final String subfldTags) {
        String[] tokens =  subfldTags.split("(?<=[0-9]?[a-z])");
        Stream<String> tokenStream = Arrays.stream(tokens);
        Stream<String> normalizedTokenStream = tokenStream.map(t -> "" + t.charAt(0)); // extract only first character
        return String.join("|", normalizedTokenStream.toArray(String[]::new));
    }


    /**
     * Strip subSubfield-Codes from the value part of a field
     */
    protected String stripSubSubfieldCode(final String term) {
        return term.replaceAll("^[a-z]:", "");
    }


    /**
     * Abstract out topic extract from LOK and ordinary field handling
     */
    protected void extractTopicsHelper(final List<VariableField> marcFieldList, final Map<String, String> separators,
                                       final Collection<String> collector, final  String langAbbrev, final String fldTag,
                                       final String subfldTags, final Predicate<DataField> includeFieldPredicate)
    {
        final Pattern subfieldPattern = Pattern.compile(subfldTags.length() == 0 ? "[a-z]" : extractNormalizedSubfieldPatternHelper(subfldTags));
        for (final VariableField vf : marcFieldList) {
            final StringBuilder buffer = new StringBuilder("");
            final List<String> complexElements = new ArrayList<String>();
            final DataField marcField = (DataField) vf;
            // Skip fields that do not match our criteria
            if (includeFieldPredicate != null && (!includeFieldPredicate.test(marcField)))
                continue;
            final List<Subfield> subfields = marcField.getSubfields();
            // Case 1: The separator specification is empty thus we
            // add the subfields individually
            if (separators.get("default").equals("")) {
                for (final Subfield subfield : subfields) {
                    if (Character.isDigit(subfield.getCode()))
                        continue;
                    final String term = subfield.getData().trim();
                    if (term.length() > 0)
                        collector.add(translateTopic(DataUtil.cleanData(term.replace("/", "\\/")), langAbbrev));
                }
            }
            // Case 2: Generate a complex string using the
            // separators
            else {
                for (final Subfield subfield : subfields) {
                    char subfieldCode = subfield.getCode();
                    final Matcher matcher = subfieldPattern.matcher("" + subfield.getCode());
                    if (!matcher.matches())
                        continue;
                    String term = subfield.getData().trim();
                    if (buffer.length() > 0) {
                        String separator = getSubfieldBasedSeparator(separators, subfield.getCode(), term);
                        // Make sure we strip the subSubfield code from our term
                        if (Character.isDigit(subfieldCode))
                            term = stripSubSubfieldCode(term);
                        if (separator != null) {
                            if (isBracketDirective(separator)) {
                                final SymbolPair symbolPair = parseBracketDirective(separator);
                                final String translatedTerm = translateTopic(term.replace("/", "\\/"), langAbbrev);
                                buffer.append(" " + symbolPair.opening + translatedTerm + symbolPair.closing);
                                continue;
                            } else if (buffer.length() > 0)
                                buffer.append(separator);
                        }

                    }
                    buffer.append(translateTopic(term.replace("/", "\\/"), langAbbrev));
                    complexElements.add(term);
                }
            }
            if (buffer.length() > 0) {
                // Try a translation once again in case a whole expression matches
                final String complexTranslation = (complexElements.size() > 1) ?
                                                  getTranslationOrNull(String.join(" / ", complexElements), langAbbrev) : null;
                collector.add(complexTranslation != null ? complexTranslation : DataUtil.cleanData(buffer.toString()));
            }
        }

    } // end extractTopicsHelper

    /**
     * Abstraction for iterating over the subfields
     */

    /**
     * Generic function for topics that abstracts from a set or list collector
     * It is based on original SolrIndex.getAllSubfieldsCollector but allows to
     * specify several different separators to concatenate the single subfields
     * Separators can be defined on a subfield basis as a list in
     * the format
     *   separator_spec          :== separator | subfield_separator_list
     *   subfield_separator_list :== subfield_separator_spec |  subfield_separator_spec ":" subfield_separator_list |
     *                               subfield_separator_spec ":" separator
     *   subfield_separator_spec :== subfield_spec separator subfield_spec :== "$" character_subfield
     *   character_subfield      :== A character subfield (e.g. p,n,t,x...)
     *   separator               :== separator_without_control_characters+ | separator "\:" separator |
     *                               separator "\$" separator | separator "\[" separator | separator "\]" separator |
     *                               bracket_directive
     *   separator_without_control_characters :== All characters without ":" and "$" | empty_string
     *   bracket_directive       :== [opening_character no_space closing_character]
     *   no_space                :== ""
     *   opening_character       :== A single character to be prepended on the left side
     *   closing character       :== A single character to be appended on the right side
     */
    @Deprecated
    protected void getTopicsCollector(final Record record, String fieldSpec, Map<String, String> separators,
                                      Collection<String> collector, String langAbbrev, Predicate<DataField> includeFieldPredicate)
    {
        String[] fldTags = fieldSpec.split(":");
        String fldTag;
        String subfldTags;
        List<VariableField> marcFieldList;

        for (final String fldTagItem : fldTags) {
            // Check to ensure tag length is at least 3 characters
            if (fldTagItem.length() < 3) {
                continue;
            }

            // Handle "Lokaldaten" appropriately
            if (fldTagItem.substring(0, 3).equals("LOK")) {
                if (fldTagItem.substring(3, 6).length() < 3) {
                    logger.severe("Invalid tag for \"Lokaldaten\": " + fldTagItem);
                    continue;
                }
                // Save LOK-Subfield
                // Currently we do not support specifying an indicator
                fldTag = fldTagItem.substring(0, 6);
                subfldTags = fldTagItem.substring(6);
            } else {
                fldTag = fldTagItem.substring(0, 3);
                subfldTags = fldTagItem.substring(3);
            }
            // Case 1: We have a LOK-Field
            if (fldTag.startsWith("LOK")) {
                // Get subfield 0 since the "subtag" is saved here
                marcFieldList = record.getVariableFields("LOK");
                if (!marcFieldList.isEmpty())
                    extractTopicsHelper(marcFieldList, separators, collector, langAbbrev, fldTag, subfldTags, includeFieldPredicate);
            }
            // Case 2: We have an ordinary MARC field
            else {
                marcFieldList = record.getVariableFields(fldTag);
                if (!marcFieldList.isEmpty()) {
                    extractTopicsHelper(marcFieldList, separators, collector, langAbbrev, fldTag, subfldTags, includeFieldPredicate);
                }
            }
        }
        return;
    }

    protected class Topic {
        public String topic;
        public SymbolPair symbolPair = new SymbolPair();
        public String separator;

        public Topic() {}

        public Topic(final String topic) {
            this.topic = topic;
        }

        public Topic(final String topic, final SymbolPair symbolPair) {
            this.topic = topic;
            this.symbolPair = symbolPair;
        }
    }

    protected void getCachedTopicsCollector(final Record record, String fieldSpec, Map<String, String> separators,
                                          Collection<String> collector, String langAbbrev)
    {
        getCachedTopicsCollector(record, fieldSpec, separators, collector, langAbbrev, null);
    }

    /**
     * Generic function for topics that abstracts from a set or list collector
     * It is based on original SolrIndex.getAllSubfieldsCollector but allows to
     * specify several different separators to concatenate the single subfields
     * Separators can be defined on a subfield basis as a list in
     * the format
     *   separator_spec          :== separator | subfield_separator_list
     *   subfield_separator_list :== subfield_separator_spec |  subfield_separator_spec ":" subfield_separator_list |
     *                               subfield_separator_spec ":" separator
     *   subfield_separator_spec :== subfield_spec separator subfield_spec :== "$" character_subfield
     *   character_subfield      :== A character subfield (e.g. p,n,t,x...)
     *   separator               :== separator_without_control_characters+ | separator "\:" separator |
     *                               separator "\$" separator | separator "\[" separator | separator "\]" separator |
     *                               bracket_directive
     *   separator_without_control_characters :== All characters without ":" and "$" | empty_string
     *   bracket_directive       :== [opening_character no_space closing_character]
     *   no_space                :== ""
     *   opening_character       :== A single character to be prepended on the left side
     *   closing character       :== A single character to be appended on the right side
     */
    protected void getCachedTopicsCollector(final Record record, String fieldSpec, final Map<String, String> separators,
                                            final Collection<String> collector, final String langAbbrev,
                                            final Predicate<DataField> includeFieldPredicate)
    {
        // Part 1: Get raw topics either from cache or from record
        final String cacheKey = record.getControlNumber() + fieldSpec + separators.entrySet().stream().map(e -> e.getKey()+"="+e.getValue()).collect(joining(":"));
        Collection<Collection<Topic>> subcollector = collectedTopicsCache.computeIfAbsent(cacheKey, s -> {
            Collection<Collection<Topic>> cachedSubcollector = new ArrayList<>();

            String[] fieldTags = fieldSpec.split(":");
            String fieldTag;
            String subfieldTags;
            List<VariableField> marcFieldList;

            for (final String fieldTagsEntry : fieldTags) {
                // Check to ensure tag length is at least 3 characters
                if (fieldTagsEntry.length() < 3) {
                    continue;
                }

                // Handle "Lokaldaten" appropriately
                if (fieldTagsEntry.substring(0, 3).equals("LOK")) {

                    if (fieldTagsEntry.substring(3, 6).length() < 3) {
                        logger.severe("Invalid tag for \"Lokaldaten\": " + fieldTagsEntry);
                        continue;
                    }
                    // Save LOK-Subfield
                    // Currently we do not support specifying an indicator
                    fieldTag = fieldTagsEntry.substring(0, 6);
                    subfieldTags = fieldTagsEntry.substring(6);
                } else {
                    fieldTag = fieldTagsEntry.substring(0, 3);
                    subfieldTags = fieldTagsEntry.substring(3);
                }
                // Case 1: We have a LOK-Field
                if (fieldTag.startsWith("LOK")) {
                    // Get subfield 0 since the "subtag" is saved here
                    marcFieldList = record.getVariableFields("LOK");
                    if (!marcFieldList.isEmpty())
                        extractCachedTopicsHelper(marcFieldList, separators, cachedSubcollector, fieldTag, subfieldTags, includeFieldPredicate);
                }
                // Case 2: We have an ordinary MARC field
                else {
                    marcFieldList = record.getVariableFields(fieldTag);
                    if (!marcFieldList.isEmpty())
                        extractCachedTopicsHelper(marcFieldList, separators, cachedSubcollector, fieldTag, subfieldTags, includeFieldPredicate);
                }
            }

            return cachedSubcollector;
        });

        // Part 2: Translate & deliver previously collected topics
        for (final Collection<Topic> topicParts : subcollector) {
            if (topicParts.size() == 1) {
                // if topic consists of 1 part, directly try to translate + add
                collector.add(translateTopic(DataUtil.cleanData(topicParts.iterator().next().topic.replace("/", "\\/")), langAbbrev));
            } else {
                // if topic consists of multiple parts:
                // try to translate the whole string
                // if that fails, translate each part
                // (replaces old "complexTranslation" logic)
                StringBuilder topicStringBuilder = new StringBuilder();
                for (final Topic topic : topicParts) {
                    if (topicStringBuilder.length() > 0)
                        topicStringBuilder.append(" / ");
                    topicStringBuilder.append(topic.topic.replace("/", "\\/"));
                }
                String translation = getTranslationOrNull(topicStringBuilder.toString(), langAbbrev);
                if (translation != null)
                    collector.add(translation);
                else {
                    StringBuilder translationStringBuilder = new StringBuilder();
                    for (final Topic topic : topicParts) {
                        if (topic.separator != null)
                            translationStringBuilder.append(topic.separator);
                        else if (translationStringBuilder.length() > 0)
                            translationStringBuilder.append(" ");

                        // '\u0000' is java default for non-set characters
                        // (compare to this value instead of null)
                        if (topic.symbolPair.opening != '\u0000')
                            translationStringBuilder.append(topic.symbolPair.opening);
                        translationStringBuilder.append(translateTopic(DataUtil.cleanData(topic.topic.replace("/", "\\/")), langAbbrev));
                        if (topic.symbolPair.closing != '\u0000')
                            translationStringBuilder.append(topic.symbolPair.closing);
                    }
                    collector.add(translationStringBuilder.toString());
                }
            }
        }
    }

    /**
     * Abstract out topic extract from LOK and ordinary field handling
     */
    protected void extractCachedTopicsHelper(final List<VariableField> marcFieldList, final Map<String, String> separators,
					   final Collection<Collection<Topic>> collector, final String fieldTag, final String subfieldTags,
					   final Predicate<DataField> includeFieldPredicate)
    {
        final Pattern subfieldPattern = Pattern.compile(subfieldTags.length() == 0 ? "[a-z]"
							                         : extractNormalizedSubfieldPatternHelper(subfieldTags));
        fieldloop:
        for (final VariableField vf : marcFieldList) {
            final ArrayList<Topic> topicParts = new ArrayList<>();
            final DataField marcField = (DataField) vf;
            // Skip fields that do not match our criteria
            if (includeFieldPredicate != null && (!includeFieldPredicate.test(marcField)))
                continue;
            final List<Subfield> subfields = marcField.getSubfields();

            // Handle LOK Fields
            if (fieldTag.length() == 6 && fieldTag.substring(0,3).equals("LOK")) {
                 final String lokTag = fieldTag.substring(3);
                 for (final Subfield subfield : subfields) {
                     if (subfield.getCode() == '0') {
                         if (subfield.getData().substring(0,3).equals(lokTag))
                             break;
                         else
                             continue fieldloop;
                     }
                 }
            }

            // Case 1: The separator specification is empty thus we add the subfields individually
            if (separators.get("default").equals("")) {
                for (final Subfield subfield : subfields) {
                    if (Character.isDigit(subfield.getCode()))
                        continue;
                    final String term = subfield.getData().trim();
                    if (term.length() > 1 || term.matches("\\d")) //Skip on character terms to address uppercase subfield problems in standardized keywords
                        topicParts.add(new Topic(term));
                }
            }
            // Case 2: Generate a complex string using the separators
            else {
                for (final Subfield subfield : subfields) {
                    char subfieldCode = subfield.getCode();
                    final Matcher matcher = subfieldPattern.matcher("" + subfield.getCode());
                    if (!matcher.matches())
                        continue;

                    Topic topic = new Topic();
                    String term = subfield.getData().trim();
                    if ((term.length() < 2) && !term.matches("\\d"))
                        continue; //Skip on character terms to address uppercase subfield problems in standardized keywords

                    if (topicParts.size() > 0) {
                        final String separator = getSubfieldBasedSeparator(separators, subfield.getCode(), term);
                        // Make sure we strip the subSubfield code from our term
                        if (Character.isDigit(subfieldCode))
                            term = stripSubSubfieldCode(term);
                        if (separator != null) {
                            if (isBracketDirective(separator))
                                topic.symbolPair = parseBracketDirective(separator);
                            else
                                topic.separator = separator;
                        }

                    }
                    topic.topic = term;
                    topicParts.add(topic);
                }
            }

            if (topicParts.size() > 0)
                collector.add(topicParts);
        }
    } // end extractTopicsHelper

    public Set<String> getTopics(final Record record, String fieldSpec, String separatorSpec, String langAbbrev)
        throws FileNotFoundException
    {
        final Set<String> topics = new HashSet<String>();
        // It seems to be a general rule that in the fields that the $p fields
        // are converted to a '.'
        // $n is converted to a space if there is additional information
        Map<String, String> separators = parseTopicSeparators(separatorSpec);
        getCachedTopicsCollector(record, fieldSpec, separators, topics, langAbbrev);
        return addHonourees(record, topics, langAbbrev);
    }


    public Set<String> getTopicFacet(final Record record, final String fieldSpecs, String separatorSpec) {
       return getTopicFacetTranslated(record, fieldSpecs, separatorSpec, "de");
    }

    public Set<String> getValuesOrUnassignedTranslated(final Record record, final String fieldSpecs,
                                                       final String langAbbrev)
    {
        Set<String> valuesTranslated = new TreeSet<String>();
        Set<String> values = getValuesOrUnassigned(record, fieldSpecs);
        for (final String value : values) {
            final String translatedValue = getTranslation(value, langAbbrev);
            valuesTranslated.add(translatedValue);
        }
        return valuesTranslated;
    }


    public Set<String> getTopicFacetTranslated(final Record record, final String fieldSpecs, String separatorSpec, final String lang) {
        final Map<String, String> separators = parseTopicSeparators(separatorSpec);
        final Set<String> valuesTranslated = new HashSet<String>();
        getCachedTopicsCollector(record, fieldSpecs, separators, valuesTranslated, lang, _689IsOrdinarySubject);
        // The topic collector generates a chain of all specified subfields for a field
        // In some cases this is unintended behaviour since different topics are are independent
        // To ensure that those chains are broken up again, make sure to specify a triple pipe (="|||") separator for these
        // subfields
        // Rewrite slashes
        final Set<String> toRemove = new HashSet<String>();
        final Set<String> toAdd = new HashSet<String>();
        valuesTranslated.forEach((entry) -> { final String[] triplePipeSeparatedStringChain = entry.split(Pattern.quote("|||"));
                                              if (triplePipeSeparatedStringChain.length > 1 || entry.contains("\\/")) {
                                                  toRemove.add(entry);
                                                  for (final String topic : triplePipeSeparatedStringChain)
                                                      toAdd.add(topic.replace("\\/", "/"));
                                              }
                                            });
        valuesTranslated.removeAll(toRemove);
        valuesTranslated.addAll(toAdd);
        addHonourees(record, valuesTranslated, lang);
        if (valuesTranslated.size() == 0)
            valuesTranslated.add(UNASSIGNED_STRING);
        return valuesTranslated;
    }

    public String getFirstValueOrUnassigned(final Record record, final String fieldSpecs) {
        final Set<String> values = SolrIndexer.instance().getFieldList(record, fieldSpecs);
        if (values.isEmpty()) {
            values.add(UNASSIGNED_STRING);
        }
        return values.iterator().next();
    }

    protected static boolean isSerialComponentPart(final Record record) {
        final String leader = record.getLeader().toString();
        return leader.charAt(7) == 'b';
    }


    protected String checkValidYear(String fourDigitYear) {
        Matcher validFourDigitYearMatcher = VALID_FOUR_DIGIT_YEAR_PATTERN.matcher(fourDigitYear);
        return validFourDigitYearMatcher.matches() ? fourDigitYear : "";
    }

    protected String yyMMDateToYear(final String controlNumber, final String yyMMDate) {
        int currentYear = Calendar.getInstance().get(Calendar.YEAR);
        int yearTwoDigit = currentYear - 2000;  // If extraction fails later we fall back to current year
        try {
            yearTwoDigit = Integer.parseInt(yyMMDate.substring(0, 1));
        }
        catch (NumberFormatException e) {
            logger.severe("in yyMMDateToYear: expected date in YYMM format, found \"" + yyMMDate
                          + "\" instead! (Control number was " + controlNumber + ")");
        }
        return Integer.toString(yearTwoDigit < (currentYear - 2000) ? (2000 + yearTwoDigit) : (1900 + yearTwoDigit));
    }

    /**
     * Get all available years from the record.
     *
     * @param record MARC record
     *
     * @return set of dates
     */

    public Set<String> getYearsBasedOnRecordType(final Record record) {
        final Set<String> years = new LinkedHashSet<>();
        final Set<String> format = getFormats(record);

        // Case 1 [Website]
        if (format.contains("Website")) {
            final ControlField _008_field = (ControlField) record.getVariableField("008");
            if (_008_field == null) {
                logger.severe("getYearsBasedOnRecordType [No 008 Field for Website " + record.getControlNumber() + "]");
                return years;
            }
            years.add(yyMMDateToYear(record.getControlNumber(), _008_field.getData()));
            return years;
        }

        // Case 2 [Reproduction] (Reproductions have the publication date of the original work in 534$c.)
        final VariableField _534Field = record.getVariableField("534");
        if (_534Field != null) {
            final DataField dataField = (DataField) _534Field;
            final Subfield cSubfield = dataField.getSubfield('c');
            if (cSubfield != null) {
                // strip non-digits at beginning and end (e.g. "©")
                String year = cSubfield.getData();
                year = year.replaceAll("^[^0-9]+", "");
                year = year.replaceAll("[^0-9]+$", "");
                years.add(year);
                return years;
            }
        }

        // Case 3 [Article or Review]
        // Match also the case of publication date transgressing one year
        // (Format YYYY/YY for older and Format YYYY/YYYY) for
        // newer entries
        if (format.contains("Article") || (format.contains("Review") && !format.contains("Book"))) {
            final List<VariableField> _936Fields = record.getVariableFields("936");
            for (final VariableField _936VField : _936Fields) {
                final DataField _936Field = (DataField) _936VField;
                if (_936Field.getIndicator1() != 'u' || _936Field.getIndicator2() != 'w')
                    continue;

                final Subfield jSubfield = _936Field.getSubfield('j');
                if (jSubfield != null) {
                    String yearOrYearRange = jSubfield.getData();
                    // Partly, we have additional text like "Post annum domini" in the front, so do away with that
                    yearOrYearRange = yearOrYearRange.replaceAll("^[\\D\\[\\]]+", "");
                    // Make sure we do away with brackets
                    yearOrYearRange = yearOrYearRange.replaceAll("[\\[|\\]]", "");
                    years.add(yearOrYearRange.length() > 4 ? yearOrYearRange.substring(0, 4) : yearOrYearRange);
                }
            }
            if (!years.isEmpty())
                return years;
        }

        // Case 4:
        // Test whether we have a 190j field
        // This was generated in the pipeline for superior works that do not contain a reasonable 008(7,10) entry
        final List<VariableField> _190Fields = record.getVariableFields("190");
        for (VariableField _190VField : _190Fields) {
            final DataField _190Field = (DataField) _190VField;
            final Subfield jSubfield = _190Field.getSubfield('j');
            if (jSubfield != null)
                years.add(jSubfield.getData());
            else
                logger.severe("getYearsBasedOnRecordType [No 190j subfield for PPN " + record.getControlNumber() + "]");

            return years;
        }

        // Case 5:
        // Use the sort date given in the 008-Field
        final ControlField _008_field = (ControlField) record.getVariableField("008");
        if (_008_field == null) {
            logger.severe("getYearsBasedOnRecordType [Could not find 008 field for PPN:" + record.getControlNumber() + "]");
            return years;
        }
        final String _008FieldContents = _008_field.getData();
        final String yearExtracted = _008FieldContents.substring(7, 11);
        // Test whether we have a reasonable value
        final String year = checkValidYear(yearExtracted);
        // log error if year is empty or not a year like "19uu"
        if (year.isEmpty() && !VALID_YEAR_RANGE_PATTERN.matcher(yearExtracted).matches())
            logger.severe("getYearsBasedOnRecordType [\"" + yearExtracted + "\" is not a valid year for PPN "
                          + record.getControlNumber() + "]");
        else
            years.add(year);

        return years;
    }

    public String isSuperiorWork(final Record record) {
        final DataField sprField = (DataField) record.getVariableField("SPR");
        if (sprField == null)
            return Boolean.FALSE.toString();
        return Boolean.toString(sprField.getSubfield('a') != null);
    }

    public String isSubscribable(final Record record) {
        final DataField sprField = (DataField) record.getVariableField("SPR");
        if (sprField == null)
            return Boolean.FALSE.toString();
        return Boolean.toString(sprField.getSubfield('b') != null);
    }

    protected static String currentYear = null;

    /** @return the last two digits of the current year. */
    protected static String getCurrentYear() {
        if (currentYear == null) {
            final DateFormat df = new SimpleDateFormat("yy");
            currentYear = df.format(Calendar.getInstance().getTime());
        }
        return currentYear;
    }

    /**
     * @brief Extracts the date (YYMMDD) that the record was created from a part
     *        of the 008 field.
     */
    public String getRecordingDate(final Record record) {
        final ControlField _008_field = (ControlField) record.getVariableField("008");
        final String fieldContents = _008_field.getData();

        final StringBuilder iso8601_date = new StringBuilder(10);
        iso8601_date.append(fieldContents.substring(0, 2).compareTo(getCurrentYear()) > 0 ? "19" : "20");
        iso8601_date.append(fieldContents.substring(0, 2));
        iso8601_date.append('-');
        iso8601_date.append(fieldContents.substring(2, 4));
        iso8601_date.append('-');
        iso8601_date.append(fieldContents.substring(4, 6));
        iso8601_date.append("T00:00:00Z");

        return iso8601_date.toString();
    }

    /**
     * @brief Extracts the date and time from the 005 field.
     */
    public String getLastModificationTime(final Record record) {
        final ControlField _005_field = (ControlField) record.getVariableField("005");
        final String fieldContents = _005_field.getData();

        final StringBuilder iso8601dateBuilder = new StringBuilder(19);
        iso8601dateBuilder.append(fieldContents.substring(0, 4));
        iso8601dateBuilder.append('-');
        iso8601dateBuilder.append(fieldContents.substring(4, 6));
        iso8601dateBuilder.append('-');
        iso8601dateBuilder.append(fieldContents.substring(6, 8));
        iso8601dateBuilder.append('T');
        iso8601dateBuilder.append(fieldContents.substring(8, 10));
        iso8601dateBuilder.append(':');
        iso8601dateBuilder.append(fieldContents.substring(10, 12));
        iso8601dateBuilder.append(':');
        iso8601dateBuilder.append(fieldContents.substring(12, 14));
        iso8601dateBuilder.append('Z');

        final String iso8601date = iso8601dateBuilder.toString();

        if (iso8601date.equals("0000-00-00T00:00:00Z"))
            return null;

        return iso8601date;
    }


    public Set<String> getGenreTranslated(final Record record, final String fieldSpecs, final String separatorSpec, final String lang) {
        Map<String, String> separators = parseTopicSeparators(separatorSpec);
        Set<String> genres = new HashSet<String>();
        getCachedTopicsCollector(record, fieldSpecs, separators, genres, lang, _689IsGenreSubject);

        return genres;
    }


    public Set<String> getRegionTranslated(final Record record, final String fieldSpecs, final String separatorSpec, final String lang) {
        Map<String, String> separators = parseTopicSeparators(separatorSpec);
        Set<String> region = new HashSet<String>();
        getCachedTopicsCollector(record, fieldSpecs, separators, region, lang, _689IsRegionSubject);

        return region;
    }


    public Set<String> getTimeTranslated(final Record record, final String fieldSpecs, final String separatorSpec, final String lang) {
        Map<String, String> separators = parseTopicSeparators(separatorSpec);
        Set<String> time = new HashSet<String>();
        getCachedTopicsCollector(record, fieldSpecs, separators, time, lang, _689IsTimeSubject);

        return time;
    }


    // Map used by getPhysicalType().
    protected static final Map<String, String> phys_code_to_format_map;

    static {
        Map<String, String> tempMap = new HashMap<>();
        tempMap.put("arbtrans", "Transparency");
        tempMap.put("blindendr", "Braille");
        tempMap.put("bray", "BRDisc");
        tempMap.put("cdda", "SoundDisc");
        tempMap.put("ckop", "Microfiche");
        tempMap.put("cofz", "Online Resource");
        tempMap.put("crom", "CDROM");
        tempMap.put("dias", "Slide");
        tempMap.put("disk", "Diskette");
        tempMap.put("druck", "Printed Material");
        tempMap.put("dvda", "Audio DVD");
        tempMap.put("dvdr", "DVD-ROM");
        tempMap.put("dvdv", "Video DVD");
        tempMap.put("gegenst", "Physical Object");
        tempMap.put("handschr", "Longhand Text");
        tempMap.put("kunstbl", "Artistic Works on Paper");
        tempMap.put("lkop", "Mircofilm");
        tempMap.put("medi", "Multiple Media Types");
        tempMap.put("scha", "Record");
        tempMap.put("skop", "Microform");
        tempMap.put("sobildtt", "Audiovisual Carriers");
        tempMap.put("soerd", "Carriers of Other Electronic Data");
        tempMap.put("sott", "Carriers of Other Audiodata");
        tempMap.put("tonbd", "Audiotape");
        tempMap.put("tonks", "Audiocasette");
        tempMap.put("vika", "VideoCasette");
        phys_code_to_format_map = Collections.unmodifiableMap(tempMap);
    }

    // Map used by getFormats().
    protected static final Map<String, String> _935a_to_format_map;

    static {
        Map<String, String> tempMap = new HashMap<>();
        tempMap.put("BIDL", "Microfiche");
        tempMap.put("BIST", "Microfiche");
        tempMap.put("CICO", "Microfiche");
        tempMap.put("EDCO", "Microfiche");
        tempMap.put("PALA", "Microfiche");
        tempMap.put("WABU", "Microfiche");
        _935a_to_format_map = Collections.unmodifiableMap(tempMap);
    }

    boolean isReview(final Record record) {
        for (final VariableField variableField : record.getVariableFields("856")) {
            final DataField field = (DataField) variableField;
            final Subfield materialTypeSubfield = getFirstNonEmptySubfield(field, '3', 'z', 'y', 'x');
            if (materialTypeSubfield != null) {
                final String materialType = materialTypeSubfield.getData();
                if (materialType.equals("07") || materialType.equals("08"))
                    return true;
            }
        }

        // Evaluate topic fields in some cases
        final List<VariableField> _655Fields = record.getVariableFields("655");
        for (final VariableField _655Field : _655Fields) {
            final DataField dataField = (DataField) _655Field;
            final Subfield aSubfield = dataField.getSubfield('a');
            if (aSubfield != null && dataField.getIndicator1() == ' ' && dataField.getIndicator2() == '7'
                && aSubfield.getData().startsWith("Rezension"))
                    return true;
        }

        final List<VariableField> _787Fields = record.getVariableFields("787");
        if (foundInSubfield(_787Fields, 'i', "Rezension von"))
            return true;

        return false;
    }

    boolean isStatistic(final Record record) {
        final List<VariableField> _655Fields = record.getVariableFields("655");
        for (final VariableField _655Field : _655Fields) {
            final DataField dataField = (DataField) _655Field;
            final Subfield aSubfield = dataField.getSubfield('a');
            if (aSubfield != null && dataField.getIndicator1() == ' ' && dataField.getIndicator2() == '7'
                && aSubfield.getData().equals("Statistik"))
                    return true;
        }

        return false;
    }

    boolean isVideo(final Record record) {
        final List<VariableField> _337Fields = record.getVariableFields("337");
        if (foundInSubfield(_337Fields, 'a', "video"))
            return true;

        final List<VariableField> _935Fields = record.getVariableFields("935");
        if (foundInSubfield(_935Fields, 'c', "vide"))
            return true;
        return false;
    }

    protected final static String electronicRessource = "Electronic";
    protected final static String nonElectronicRessource = "Non-Electronic";

    /**
     * Determine Record Formats
     *
     * Overwrite the original VuFindIndexer getFormats to do away with the
     * single bucket approach, i.e. collect all formats you find, i.e. this is
     * the original code without premature returns which are left in commented
     * out
     *
     * @param record
     *            MARC record
     * @return set of record format
     */
    public Set<String> getFormats(final Record record) {
        final Set<String> formats = map935b(record, phys_code_to_format_map);
        final String leader = record.getLeader().toString();
        final ControlField fixedField = (ControlField) record.getVariableField("008");
        //final DataField title = (DataField) record.getVariableField("245");
        String formatString;
        char formatCode = ' ';
        char formatCode2 = ' ';
        char formatCode4 = ' ';

        // check the 007 - this is a repeating field
        List<VariableField> fields = record.getVariableFields("007");
        if (fields != null) {
            ControlField formatField;
            for (final VariableField varField : fields) {
                formatField = (ControlField) varField;
                formatString = formatField.getData().toUpperCase();
                formatCode = formatString.length() > 0 ? formatString.charAt(0) : ' ';
                formatCode2 = formatString.length() > 1 ? formatString.charAt(1) : ' ';
                formatCode4 = formatString.length() > 4 ? formatString.charAt(4) : ' ';
                switch (formatCode) {
                case 'A':
                    switch (formatCode2) {
                    case 'D':
                        formats.add("Atlas");
                        break;
                    default:
                        formats.add("Map");
                        break;
                    }
                    break;
                case 'C':
                    switch (formatCode2) {
                    case 'A':
                        formats.add("TapeCartridge");
                        break;
                    case 'B':
                        formats.add("ChipCartridge");
                        break;
                    case 'C':
                        formats.add("DiscCartridge");
                        break;
                    case 'F':
                        formats.add("TapeCassette");
                        break;
                    case 'H':
                        formats.add("TapeReel");
                        break;
                    case 'J':
                        formats.add("FloppyDisk");
                        break;
                    case 'M':
                    case 'O':
                        formats.add("CDROM");
                        break;
                    case 'R':
                        // Do not return - this will cause anything with an
                        // 856 field to be labeled as electronicRessource
                        break;
                    }
                    break;
                case 'D':
                    formats.add("Globe");
                    break;
                case 'F':
                    formats.add("Braille");
                    break;
                case 'G':
                    switch (formatCode2) {
                    case 'C':
                    case 'D':
                        formats.add("Filmstrip");
                        break;
                    case 'T':
                        formats.add("Transparency");
                        break;
                    default:
                        formats.add("Slide");
                        break;
                    }
                    break;
                case 'H':
                    formats.add("Microfilm");
                    break;
                case 'K':
                    switch (formatCode2) {
                    case 'C':
                        formats.add("Collage");
                        break;
                    case 'D':
                        formats.add("Drawing");
                        break;
                    case 'E':
                        formats.add("Painting");
                        break;
                    case 'F':
                        formats.add("Print");
                        break;
                    case 'G':
                        formats.add("Photonegative");
                        break;
                    case 'J':
                        formats.add("Print");
                        break;
                    case 'L':
                        formats.add("Drawing");
                        break;
                    case 'O':
                        formats.add("FlashCard");
                        break;
                    case 'N':
                        formats.add("Chart");
                        break;
                    default:
                        formats.add("Photo");
                        break;
                    }
                    break;
                case 'M':
                    switch (formatCode2) {
                    case 'F':
                        formats.add("VideoCassette");
                        break;
                    case 'R':
                        formats.add("Filmstrip");
                        break;
                    default:
                        formats.add("MotionPicture");
                        break;
                    }
                    break;
                case 'O':
                    formats.add("Kit");
                    break;
                case 'Q':
                    formats.add("MusicalScore");
                    break;
                case 'R':
                    formats.add("SensorImage");
                    break;
                case 'S':
                    switch (formatCode2) {
                    case 'D':
                        formats.add("SoundDisc");
                        break;
                    case 'S':
                        formats.add("SoundCassette");
                        break;
                    default:
                        formats.add("SoundRecording");
                        break;
                    }
                    break;
                case 'V':
                    switch (formatCode2) {
                    case 'C':
                        formats.add("VideoCartridge");
                        break;
                    case 'D':
                        switch (formatCode4) {
                        case 'S':
                            formats.add("BRDisc");
                            break;
                        case 'V':
                        default:
                            formats.add("VideoDisc");
                            break;
                        }
                        break;
                    case 'F':
                        formats.add("VideoCassette");
                        break;
                    case 'R':
                        formats.add("VideoReel");
                        break;
                    default:
                        formats.add("Video");
                        break;
                    }
                    break;
                }
            }
        }
        // check the Leader at position 6
        switch (leader.charAt(6)) {
        case 'c':
        case 'd':
            formats.add("MusicalScore");
            break;
        case 'e':
        case 'f':
            formats.add("Map");
            break;
        case 'g':
            formats.add(isVideo(record) ? "Video" : "Slide");
            break;
        case 'i':
            formats.add("SoundRecording");
            break;
        case 'j':
            formats.add("MusicRecording");
            break;
        case 'k':
            formats.add("Photo");
            break;
        case 'o':
        case 'p':
            formats.add("Kit");
            break;
        case 'r':
            formats.add("PhysicalObject");
            break;
        case 't':
            formats.add("Manuscript");
            break;
        }

        // check the Leader at position 7
        switch (leader.charAt(7)) {
        // Monograph
        case 'm':
            formats.add("Book");
            break;
        // Component parts
        case 'a': // BookComponentPart
            formats.add("Article");
            break;
        case 'b': // SerialComponentPart
            formats.add("Article");
            break;
            // Integrating resource
        case 'i':
            // Look in 008 to determine the exact type
            formatCode = fixedField.getData().toUpperCase().charAt(21);
            switch (formatCode) {
            case 'W':
                formats.add("Website");
                break;
            case 'D':
                formats.add("Database");
                break;
            }
            break;
        // Serial
        case 's':
            // Look in 008 to determine what type of Continuing Resource
            formatCode = fixedField.getData().toUpperCase().charAt(21);
            switch (formatCode) {
            case 'N':
                formats.add("Newspaper");
                break;
            case 'P':
                formats.add("Journal");
                break;
            default:
                formats.add("Serial");
                break;
            }
        }

        // Literary remains and archived material
        if (record.getControlNumber().startsWith("LR")) {
            formats.remove("Kit");
            final VariableField _245Field = record.getVariableField("245");
            if (_245Field != null) {
                for (final Subfield aSubfield : ((DataField) _245Field).getSubfields()) {
                    final Matcher matcher = REMAINS_OR_PARTIAL_REMAINS.matcher(aSubfield.getData());
                    if (matcher.matches())
                        formats.add("LiteraryRemains");
                    else
                        formats.add("ArchivedMaterial");
                }
            }
        }

        //Software
        final List<VariableField> _336Fields = record.getVariableFields("336");
        for (final VariableField variableField : _336Fields) {
            final DataField _336Field = (DataField) variableField;
            for (final Subfield aSubfield : _336Field.getSubfields('a')) {
                if (aSubfield.getData().equals("Computerprogramm")) {
                    formats.add("Software");
                }
            }
        }

        // Festschrift
        if (fixedField.getData().length() >= 31) {
            formatCode = fixedField.getData().toUpperCase().charAt(30);
            if (formatCode == '1')
                formats.add("Festschrift");
        }

        // Check 935$a entries:
        final List<VariableField> _935Fields = record.getVariableFields("935");
        for (final VariableField variableField : _935Fields) {
            final DataField _935Field = (DataField) variableField;
            if (_935Field != null) {
                for (final Subfield aSubfield : _935Field.getSubfields('a')) {
                    final String subfieldContents = aSubfield.getData();
                    if (_935a_to_format_map.containsKey(subfieldContents)) {
                        formats.remove("Article");
                        formats.add(_935a_to_format_map.get(subfieldContents));
                    }
                }
            }
        }

        // Records that contain the code "so" in 935$c should be classified as "Article" and not as "Book":
        if (!formats.contains("Article")) {
            for (final VariableField variableField : _935Fields) {
                final DataField _935Field = (DataField) variableField;
                if (_935Field != null) {
                    for (final Subfield cSubfield : _935Field.getSubfields('c')) {
                        if (cSubfield.getData().equals("so")) {
                            formats.remove("Book");
                            formats.add("Article");
                            break;
                        }
                    }
                }
            }
        }

        if (foundInSubfield(_935Fields, 'c', "uwlx")) {
            formats.remove("Article");
            formats.add("DictionaryEntryOrArticle");
        }

        // Determine whether record is a 'Festschrift', i.e. has "fe" in 935$c
        if (foundInSubfield(_935Fields, 'c', "fe"))
            formats.add("Festschrift");

        // Determine whether a record is a subscription package, i.e. has "subskriptionspaket" in 935$c
        if (foundInSubfield(_935Fields, 'c', "subskriptionspaket"))
            formats.add("SubscriptionBundle");

        if (isReview(record)) {
            formats.remove("Article");
            formats.add("Review");
        }

        if (isStatistic(record)) {
            formats.remove("Article");
            formats.add("Statistics");
        }

        // Evaluate topic fields in some cases
        for (final VariableField _655Field : record.getVariableFields("655")) {
            final DataField dataField = (DataField) _655Field;
            final Subfield aSubfield = dataField.getSubfield('a');
            if (aSubfield != null) {
                if (aSubfield.getData().startsWith("Weblog")) {
                    formats.remove("Journal");
                    formats.add("Blog");
                    break;
                }
                if (aSubfield.getData().startsWith("Forschungsdaten") & dataField.getIndicator1() == ' '
                    && dataField.getIndicator2() == '7')
                {
                    formats.remove("Book");
                    formats.add("ResearchData");
                    break;
                }
            }
        }

        // Nothing worked!
        if (formats.isEmpty())
            formats.add("Unknown");

        return formats;
    }

    protected boolean foundInSubfield(final List<VariableField> fields, final char subfieldCode, final String subfieldContents) {
        for (final VariableField field : fields) {
            final DataField dataField = (DataField) field;
            for (final Subfield subfield : dataField.getSubfields()) {
                if (subfield.getCode() == subfieldCode && subfield.getData().contains(subfieldContents))
                    return true;
            }
        }

        return false;
    }

    /**
     * Determine Mediatype For facets we need to differentiate between
     * electronic and non-electronic resources
     *
     * @param record
     *            the record
     * @return mediatype of the record
     */

    public Set<String> getMediatype(final Record record) {
        final Set<String> mediatypes = new HashSet<>();
        if (record.getVariableField("ZWI") != null) {
            mediatypes.add(electronicRessource);
            mediatypes.add(nonElectronicRessource);
            return mediatypes;
        }

        final VariableField elcField = record.getVariableField("ELC");
        if (elcField == null)
            mediatypes.add(nonElectronicRessource);
        else {
            final DataField dataField = (DataField) elcField;
            if (dataField.getSubfield('a') != null)
                mediatypes.add(electronicRessource);
            if (dataField.getSubfield('b') != null)
                mediatypes.add(nonElectronicRessource);
        }

        return mediatypes;
    }

    /**
     * Get IDs of all other records merged into this record
     *
     * @param record
     * @return all merged ids (not including the own record id)
     */
    public Set<String> getMergedIds(final Record record) {
        Set<String> merged_ids = new HashSet<String>();

        for (final VariableField _ZWIField : record.getVariableFields("ZWI")) {
            final DataField field = (DataField)_ZWIField;
            final Subfield subfield_a = field.getSubfield('a');
            if (subfield_a != null && subfield_a.getData().equals("1")) {
                for (final Subfield subfield_b : field.getSubfields('b')) {
                    if (!subfield_b.getData().isEmpty())
                        merged_ids.add(subfield_b.getData());
                }
            }
        }

        return merged_ids;
    }

    /**
     * Helper to calculate the first publication year
     *
     * @param years
     *            String of possible publication years
     * @return the first publication year
     */

    public String calculateFirstPublicationYear(Set<String> years) {
        String firstPublicationYear = null;
        for (final String current : years) {
            if (firstPublicationYear == null || current != null
                && Integer.parseInt(current) < Integer.parseInt(firstPublicationYear))
                firstPublicationYear = current;
        }
        return firstPublicationYear;
    }

    /**
     * Helper to calculate the most recent publication year
     *
     * @param year
     *            String of possible publication years
     * @return the last publication year
     */

    public String calculateLastPublicationYear(Set<String> years) {
        String lastPublicationYear = null;
        for (final String current : years) {
            if (lastPublicationYear == null || current != null && Integer.parseInt(current) > Integer.parseInt(lastPublicationYear))
                lastPublicationYear = current;
        }
        return lastPublicationYear;
    }

    /**
     * Helper to cope with differing dates and possible special characters
     *
     * @param dateString
     *            String of possible publication dates
     * @return the first publication date
     */
    public String getCleanAndNormalizedDate(final String dateString) {
        // We have to normalize dates that follow a different calculation of
        // time, e.g. works with hindu time
        Matcher differentCalcOfTimeMatcher = DIFFERENT_CALCULATION_OF_TIME_PATTERN.matcher(dateString);
        return differentCalcOfTimeMatcher.find() ? differentCalcOfTimeMatcher.group(2) : DataUtil.cleanDate(dateString);

    }

    /**
     * Determine the publication year for "date ascending/descending" sorting in
     * accordance with the rules stated in issue 227
     *
     * @param record
     *            MARC record
     * @return the publication year to be used for
     */
    public String getPublicationSortYear(final Record record) {
        final Set<String> years = getYearsBasedOnRecordType(record);
        if (years.isEmpty())
            return "";

        return calculateLastPublicationYear(years);
    }

    public Set<String> getRecordSelectors(final Record record) {
        final Set<String> result = new TreeSet<String>();

        // 935a
        for (final VariableField _935Field : record.getVariableFields("935")) {
            final DataField field = (DataField)_935Field;
            for (final Subfield subfield_a : field.getSubfields('a')) {
                if (!subfield_a.getData().isEmpty()) {
                    result.add(subfield_a.getData());
                }
            }
        }

        // LOK 935a
        for (final VariableField variableField : record.getVariableFields("LOK")) {
            final DataField lokfield = (DataField) variableField;
            final Subfield subfield_0 = lokfield.getSubfield('0');
            if (subfield_0 == null || !subfield_0.getData().equals("935  ")) {
                continue;
            }

            for (final Subfield subfield_a : lokfield.getSubfields('a')) {
                if (!subfield_a.getData().isEmpty()) {
                    result.add(subfield_a.getData());
                }
            }
        }

        return result;
    }

    protected String getPages(final Record record) {
        final DataField _936Field = (DataField)record.getVariableField("936");
        if (_936Field == null)
            return null;
        final Subfield subfieldH = _936Field.getSubfield('h');
        if (subfieldH == null)
            return null;
        return subfieldH.getData();
    }

    public String getStartPage(final Record record) {
        final String pages = getPages(record);
        if (pages == null)
            return null;
        final Matcher matcher = PAGE_MATCH_PATTERN.matcher(pages);
        if (matcher.matches())
            return matcher.group(1);
        return null;
    }

    public String getEndPage(final Record record) {
        final String pages = getPages(record);
        if (pages == null)
            return null;
        final Matcher matcher = PAGE_MATCH_PATTERN.matcher(pages);
        if (matcher.matches()) {
            if (matcher.group(3) != null && !matcher.group(3).isEmpty())
                return matcher.group(3);
            return matcher.group(1);
        }
        return null;
    }

    /** @return "open-access" if we have an open access publication, else "non-open-access". */
    public String getOpenAccessStatus(final Record record) {
        final DataField _OASField = (DataField)record.getVariableField("OAS");
        if (_OASField == null)
            return "non-open-access";
        final Subfield subfieldA = _OASField.getSubfield('a');
        if (subfieldA == null)
            return "non-open-access";

        return subfieldA.getData().equals("1") ? "open-access" : "non-open-access";
    }

    // Try to get a numerically sortable representation of an issue
    public String getIssueSort(final Record record) {
        for (final VariableField variableField : record.getVariableFields("936")) {
            final DataField dataField = (DataField) variableField;
            final Subfield subfieldE = dataField.getSubfield('e');
            if (subfieldE == null)
                return "0";
            final String issueString = subfieldE.getData();
            if (issueString.matches("^\\d+$"))
                return issueString;
            // Handle Some known special cases
            if (issueString.matches("[\\[]\\d+[\\]]"))
                return issueString.replaceAll("[\\[\\]]","");
            if (issueString.matches("\\d+/\\d+"))
                return issueString.split("/")[0];
        }
        return "0";
    }

    // Returns a canonized number for volume sorting
    public String getVolumeSort(final Record record) {
        String volumeString = "";
        for (final VariableField variableField : record.getVariableFields("936")) {
            if (!volumeString.isEmpty())
                break;
            final DataField dataField = (DataField) variableField;
            final Subfield subfieldD = dataField.getSubfield('d');
            if (subfieldD != null)
                volumeString = subfieldD.getData();
        }
        for (final VariableField variableField : record.getVariableFields("830")) {
            if (!volumeString.isEmpty())
                break;
            final DataField dataField = (DataField) variableField;
            final Subfield subfield9 = dataField.getSubfield('9');
            if (subfield9 != null)
                volumeString = subfield9.getData();
        }

        if (volumeString.matches("^\\d+$"))
            return volumeString;
        // Handle Some known special cases
        if (volumeString.matches("[\\[]\\d+[\\]]"))
            return volumeString.replaceAll("[\\[\\]]","");
        if (volumeString.matches("\\d+/\\d+"))
            return volumeString.split("/")[0];

        return "0";
    }


    public String getFullText(final Record record) {
        final DataField fullTextField = (DataField) record.getVariableField("FUL");
        if (fullTextField == null)
            return "";

        Connection dbConnection = DatabaseManager.instance().getConnection();

        try {
            final Statement statement = dbConnection.createStatement();
            final ResultSet resultSet = statement.executeQuery("SELECT full_text FROM full_text_cache WHERE id=\""
                                                               + record.getControlNumber() + "\"");
            if (!resultSet.isBeforeFirst())
                return "";

            resultSet.next();
            return resultSet.getString("full_text");
        } catch (SQLException e) {
            logger.severe("SQL error: " + e.toString());
            System.exit(1);
            return ""; // Keep the compiler happy!
        }
    }


    public String isHybrid(final Record record) {
        final VariableField field = record.getVariableField("ZWI");
        return Boolean.toString(field != null);
    }

    public String hasUnpaywallEntry(final Record record) {
        for (final VariableField variableField : record.getVariableFields("856")) {
            final DataField dataField = (DataField) variableField;
            final Subfield subfield_x = dataField.getSubfield('x');
            if (subfield_x != null && subfield_x.getData().equals("unpaywall"))
                return Boolean.TRUE.toString();
        }
        return Boolean.FALSE.toString();
    }


    protected String extractFullTextFromJSON(final JSONArray hits, final String text_type_description) {
        if (hits.isEmpty())
            return "";

        StringBuilder fulltextBuilder = new StringBuilder();
        for (final Object obj : hits) {
             JSONObject hit = (JSONObject) obj;
             JSONObject _source = (JSONObject) hit.get("_source");
             final String description = _source.containsKey("text_type") ?
		                        mapTextTypeToDescription((String) _source.get("text_type")) : "";
             if (description.isEmpty() || text_type_description.isEmpty() ||
                 description.equals(text_type_description))
                     fulltextBuilder.append(_source.get("full_text") != null ? _source.get("full_text") : "");
        }
        return (fulltextBuilder.length() > 0)  ? fulltextBuilder.toString() : null;
    }


    protected String mapTextTypeToDescription(final String text_type) {
        String type_candidate = text_type_to_description_map.get(text_type);
        return type_candidate != null ? type_candidate : "Unknown";
    }


    protected Set<String> extractTextTypeFromJSON(final JSONArray hits) {
        final Set<String> text_types = new TreeSet<String>();
        if (hits.isEmpty())
            return text_types;
        for (final Object obj : hits) {
             JSONObject hit = (JSONObject) obj;
             JSONObject _source = (JSONObject) hit.get("_source");
             final String description = _source.containsKey("text_type") ?
                                        mapTextTypeToDescription((String) _source.get("text_type")) : "";
             if (!description.isEmpty())
                 text_types.add(description);
        }
        return text_types;
    }


    protected boolean extractIsPublisherProvidedFromJSON(final JSONArray hits) {
        if (hits.isEmpty())
            return false;
        for (final Object obj : hits) {
             JSONObject hit = (JSONObject) obj;
             JSONObject _source = (JSONObject) hit.get("_source");
             if (_source.containsKey("is_publisher_provided") && ((String) _source.get("is_publisher_provided")).equals("true"))
                 return true;
        }
        return false;
    }


    protected JSONArray getElasticsearchHits(final String responseString) {
        if (responseString.isEmpty())
            return new JSONArray();
        try {
            JSONObject responseObject = (JSONObject) new JSONParser().parse(responseString);
            JSONObject hits = (JSONObject) responseObject.get("hits");
            return (JSONArray) hits.get("hits");
        } catch(ParseException e) {
           e.printStackTrace();
        }
        return new JSONArray(); /* should not be reached */
    }


    protected static Properties getPropertiesFromFile(final String configProps) {
        String homeDir = Boot.getDefaultHomeDir();
        File configFile = new File(configProps);
        if (!configFile.isAbsolute())
        {
            configFile = new File(homeDir, configProps);
        }
        return PropertyUtils.loadProperties(new String[0], configFile.getAbsolutePath(), true);
    }


    protected static Properties esFulltextProperties = null;
    protected static String esFulltextUrl = null;


    public static Properties getESFulltextProperties() {
        if (esFulltextProperties != null)
            return esFulltextProperties;
        esFulltextProperties = getPropertiesFromFile(ES_FULLTEXT_PROPERTIES_FILE);
        return esFulltextProperties;
    }


    public static String getMyHostnameShort() throws java.net.UnknownHostException {
       return fullHostName.replaceAll("\\..*", "");
    }


    public static String getElasticsearchHost() throws java.net.UnknownHostException {
        final Properties esFullTextProperties = getESFulltextProperties();
        final String myhostname = getMyHostnameShort();
        return PropertyUtils.getProperty(esFullTextProperties, myhostname + ".host", "localhost");
    }


    public static String getElasticsearchPort() throws java.net.UnknownHostException {
        final Properties esFullTextProperties = getESFulltextProperties();
        final String myhostname = getMyHostnameShort();
        return PropertyUtils.getProperty(esFullTextProperties, myhostname + ".port", "9200");
    }


    public static String getElasticsearchUrl() throws java.net.UnknownHostException {
        if (esFulltextUrl == null) {
            final String esHost = getElasticsearchHost();
            final String esPort = getElasticsearchPort();
            esFulltextUrl = "http://" + esHost + ":" + esPort + "/full_text_cache/_search";
        }
        return esFulltextUrl;
    }


    public static boolean isFullTextDisabled() throws java.net.UnknownHostException {
        final Properties esFullTextProperties = getESFulltextProperties();
        final String myhostname = getMyHostnameShort();
        final String isDisabled = PropertyUtils.getProperty(esFullTextProperties, myhostname + ".disabled", "false");
        return Boolean.parseBoolean(isDisabled);
    }


    protected static Set<String> fulltextPPNList;
    static {
        fulltextPPNList = new HashSet<>();
        try {
            if (!isFullTextDisabled()) {
                final String fulltextPPNListFile = "/usr/local/ub_tools/bsz_daten/fulltext_ids.txt";
                if (new File(fulltextPPNListFile).length() != 0) {
                    try {
                        BufferedReader in = new BufferedReader(new FileReader(fulltextPPNListFile));
                        String ppnLine;
                        while ((ppnLine = in.readLine()) != null)
                            fulltextPPNList.add(ppnLine);
                     } catch (IOException e) {
                        logger.severe("Could not read file: " + e.toString());
                     }
                }
            }
        } catch (java.net.UnknownHostException e) {
            throw new RuntimeException ("Could not determine Hostname", e);
        }
    }

    protected static CloseableHttpClient elasticsearchClient;

    protected synchronized CloseableHttpClient getSharedElasticsearchClient() {
        // Use shared client for better performance, see:
        // https://stackoverflow.com/questions/43730286/closeablehttpclient-blocks-per-few-minutes-under-high-concurrency
        if (elasticsearchClient == null) {
            // Use concurrency limit, see:
            // https://www.tutorialspoint.com/apache_httpclient/apache_httpclient_multiple_threads.htm
            PoolingHttpClientConnectionManager connManager = new PoolingHttpClientConnectionManager();
            connManager.setMaxTotal(10);
            HttpClientBuilder elasticsearchClientBuilder = HttpClients.custom().setConnectionManager(connManager);
            elasticsearchClient = elasticsearchClientBuilder.build();
        }
        return elasticsearchClient;
    }

    protected String getElasticsearchSearchResponse(final Record record) throws IOException {
        if (!fulltextPPNList.contains(record.getControlNumber()))
            return "";

        HttpPost httpPost = new HttpPost(getElasticsearchUrl());
        final String fulltextById = "{ \"query\" : { \"match\" : { \"id\" : \"" + record.getControlNumber() + "\" } } }";
        final StringEntity stringEntity = new StringEntity(fulltextById);
        httpPost.setEntity(stringEntity);
        httpPost.setHeader("Accept", "application/json");
        httpPost.setHeader("Content-type", "application/json");
        CloseableHttpResponse response = getSharedElasticsearchClient().execute(httpPost);
        HttpEntity entity = response.getEntity();
        final String result = EntityUtils.toString(entity, StandardCharsets.UTF_8);
        EntityUtils.consume(entity);
        return result;
    }

    protected JSONArray getFullTextServerHits(final Record record) throws Exception {
        return fulltextServerHitsCache.computeIfAbsent(record.getControlNumber(), hits -> {
            try {
                final String es_search_response = getElasticsearchSearchResponse(record);
                return getElasticsearchHits(es_search_response);
            } catch (Exception e) {
                // The lambda interface here does nut support regular exceptions,
                // So we need to wrap any exception in a runtime exception
                throw new RuntimeException(e);
            }
        });
    }

    public String getFullTextElasticsearch(final Record record) throws Exception {
        return extractFullTextFromJSON(getFullTextServerHits(record), "Fulltext");
    }


    public String getFullTextElasticsearchTOC(final Record record) throws Exception {
        return extractFullTextFromJSON(getFullTextServerHits(record), "Table of Contents");
    }


    public String getFullTextElasticsearchAbstract(final Record record) throws Exception {
        return extractFullTextFromJSON(getFullTextServerHits(record), "Abstract");
    }


    public String getFullTextElasticsearchSummary(final Record record) throws Exception {
        return extractFullTextFromJSON(getFullTextServerHits(record), "Summary");
    }


    public Set<String> getFullTextTypes(final Record record) throws Exception {
        return extractTextTypeFromJSON(getFullTextServerHits(record));
    }


    public String getHasPublisherFullText(final Record record) throws Exception {
        return Boolean.toString(extractIsPublisherProvidedFromJSON(getFullTextServerHits(record)));
    }


    public String extractFirstK10PlusPPNAndTitle(final Record record, final String fieldAndSubfieldCode) throws IllegalArgumentException {
        if (fieldAndSubfieldCode.length() != 3 + 1)
            throw new IllegalArgumentException("expected a field tag plus a subfield code, got \"" + fieldAndSubfieldCode + "\"!");

        final DataField field = (DataField) record.getVariableField(fieldAndSubfieldCode.substring(0, 3));
        if (field == null)
            return null;

        for (final Subfield subfield : field.getSubfields(fieldAndSubfieldCode.charAt(3))) {
            final Matcher matcher = PPN_WITH_K10PLUS_ISIL_PREFIX_PATTERN.matcher(subfield.getData());
            if (matcher.matches()) {
                Subfield titleSubfield = field.getSubfield('t');
                if (titleSubfield == null)
                    titleSubfield = field.getSubfield('a');
                final String title = (titleSubfield != null) ? titleSubfield.getData() : "";
                return matcher.group(1) + ":" + title;
            }
        }

        return null;
    }


    public Set<String> getAuthorsAndIds(final Record record, String tagList) {
        final String separator = ":";
        Set<String> result = new HashSet<>();

        Map<String, String> authorToId = new HashMap<>();

        if (tagList.contains(":") == false && tagList.trim().length() > 2) {
            tagList = tagList + ":";
        }

        for (String tag : tagList.split(":")) {
            if (tag == null || tag.isEmpty()) {
                continue;
            }

            for (final VariableField variableField : record.getVariableFields(tag)) {
                final DataField dataField = (DataField) variableField;

                final Subfield subfield_a = dataField.getSubfield('a');
                if (subfield_a == null || subfield_a.getData().isEmpty()) {
                    continue;
                }

                final Subfield subfield_b = dataField.getSubfield('b');
                final Subfield subfield_c = dataField.getSubfield('c');
                final Subfield subfield_d = dataField.getSubfield('d');
                final List<Subfield> subfields_0 = dataField.getSubfields('0');

                String authorName = subfield_a.getData();
                if (subfield_b != null && subfield_a.getData().isEmpty() == false)
                    authorName += ", " + subfield_b.getData();
                if (subfield_c != null && subfield_c.getData().isEmpty() == false)
                    authorName += ", " + subfield_c.getData();
                if (subfield_d != null && subfield_d.getData().isEmpty() == false)
                    authorName += " " + subfield_d.getData();

                if (subfields_0 == null || subfields_0.size() < 1) {
                    if (authorToId.containsKey(authorName) == false)
                        authorToId.put(authorName, "");
                } else {
                    for (Subfield subfield_0 : subfields_0) {
                        String author_id = subfield_0.getData();
                        if (author_id.contains(ISIL_PREFIX_K10PLUS)) {
                            authorToId.put(authorName, author_id.replaceAll(ISIL_PREFIX_K10PLUS_ESCAPED, "").trim());
                        } else if (authorToId.containsKey(authorName) == false) {
                            authorToId.put(authorName, "");
                        }
                    }
                }
            }
        }

        for (Entry<String,String> pair : authorToId.entrySet()){
            result.add(pair.getKey() + separator + pair.getValue());
        }

        return result;
    }


    public static List<String> getDateBBoxes(final Record record, final String rangeFieldTag) {
        final DataField rangeField = (DataField) record.getVariableField(rangeFieldTag);
        if (rangeField == null)
            return null;

        final Subfield subfieldA = rangeField.getSubfield('a');
        if (subfieldA == null)
            return null;

        final String[] parts = subfieldA.getData().split(",");

        final List<String> ranges = new ArrayList<String>(parts.length);
        for (final String part : parts) {
            final String[] range = part.split("_");
            if (range.length != 2) {
                System.err.println(part + " is not a valid range! (1)");
                System.exit(-1);
            }

            try {
                long x = Long.parseLong(range[0]);
                long y = Long.parseLong(range[1]);

                if (rangeFieldTag.equalsIgnoreCase("TIM")) {
                    final long lower = x < y ? x : y;
                    final long upper = x < y ? y : x;
                    ranges.add(getBBoxRangeValue(String.valueOf(lower), String.valueOf(upper)));
                }
            } catch (NumberFormatException e) {
                System.err.println(range + " is not a valid range! (2)");
                System.exit(-1);
            }
        }

        return ranges;
    }


    public static List<String> getDateRanges(final Record record, final String rangeFieldTag) {
        final DataField rangeField = (DataField) record.getVariableField(rangeFieldTag);
        if (rangeField == null)
            return null;

        final Subfield subfieldA = rangeField.getSubfield('a');
        if (subfieldA == null)
            return null;

        final String[] parts = subfieldA.getData().split(",");

        final List<String> ranges = new ArrayList<String>(parts.length);
        for (final String part : parts) {
            final String[] range = part.split("_");
            if (range.length != 2) {
                System.err.println(part + " is not a valid range! (1)");
                System.exit(-1);
            }

            try {
                long x = Long.parseLong(range[0]);
                long y = Long.parseLong(range[1]);

                if (rangeFieldTag.equalsIgnoreCase("TIM")) {

                    final long yearOffset = 10000000L;
                    final long lower = x < y ? x : y;
                    final long upper = x < y ? y : x;

                    final long yearLower = (lower / 10000) - yearOffset;
                    final long yearUpper = (upper / 10000) - yearOffset;

                    String monthDayLower = String.format("%04d", lower % 10000);
                    String monthDayUpper = String.format("%04d", upper % 10000);
                    String sLower = Math.abs(yearLower) > 5000 ? "*" : yearLower + "-" + monthDayLower.substring(0,2) + "-" + monthDayLower.substring(2);
                    String sUpper = Math.abs(yearUpper) > 5000 ? "*" : yearUpper + "-" + monthDayUpper.substring(0,2) + "-" + monthDayUpper.substring(2);

                    ranges.add("[" + sLower + " TO " + sUpper + "]");

                }
                else {
                    final Instant lower = Instant.ofEpochSecond(x < y ? x : y);
                    final Instant upper = Instant.ofEpochSecond(x < y ? y : x);
                    ranges.add("[" + lower.toString() + " TO " + upper.toString() + "]");
                }
            } catch (NumberFormatException e) {
                System.err.println(range + " is not a valid range! (2)");
                System.exit(-1);
            }
        }

        return ranges;
    }

    public List<String> createNonUniqueSearchField(final Record record, final String tagList) {
	List<String> results = new ArrayList<String>();
	Set<String> fieldsByTagList = org.vufind.index.FieldSpecTools.getFieldsByTagList(record,tagList);
	//clean, toLower, stripPunct, stripAccent, normalizeSortableString
	for (String elem : fieldsByTagList) {
		results.add(normalizeSortableString(org.solrmarc.tools.DataUtil.stripAccents(org.solrmarc.tools.DataUtil.stripAllPunct(elem.trim().toLowerCase()))));
	}
	return results;
    }

    /*
     * Custom normalisation map function
     */
    public Collection<String> normalizeSortableString(Collection<String> extractedValues) {
        Collection<String> results = new ArrayList<String>();
        for (final String value : extractedValues) {
            final String newValue = normalizeSortableString(value);
            if (newValue != null && !newValue.isEmpty())
                results.add(newValue);
        }
        return results;
    }
}
