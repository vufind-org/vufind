# VuFind Road Map

## v4.0

### System Requirements
- PHP 5.6
  - Expecting RedHat to go to PHP 5.6 (Q4 2016)
- Java 8 for Solr 6

### Dependency Updates
- Solr 6.2.0
- [Corresponding SolrMarc](https://github.com/solrmarc/solrmarc/tree/next "SolrMarc: Next Generation")

### Features to be removed
- Jquerymobile
- Statistics

### New Feature: API Interfaces
- [Search API](https://github.com/vufind-org/vufind/pull/819)
- Record API

### Refactoring of Modules
- Separate modules for search backends?
- How much is too much?
- ILS drivers to a module with DAIA/PAIA interface
  - Unify ILS logic
    - Standardize parameter names
    - Standardize return structures
  - Item holdings
    - Assumptions about location amounts, etc.
    - [Item blocking simplification](https://github.com/vufind-org/vufind/pull/815)

### Libraries to be replaced
- Google Maps for OpenLayers and Leaflet

### Solr 6 and New SolrMarc
- [Bundling Solr](https://github.com/vufind-org/vufind/pull/769)
- Spellings features
- Removal of bean shell scripts

### Translation
- Tokenize more, chunk less
- API strings are a problem (EDS)
- Move towards Translate Wiki
- [translation of language field in record-related templates](https://github.com/vufind-org/vufind/pull/413)
- Indexing language terms


## The Horizon
- Consider the possibility of using patron ID instead of library card number + password for user identification so that e.g. if user loses the library card he doesn’t lose access to his VuFind account
- Investigation into PSR4
- Util php files to bash files
- Template granularity
- Revisit binary availability status in ILS interface.
- See PAIA driver
- Cover images: Pattern-based image retrieval from local file system
- Smarter handling logs and errors for fatal
- Usage survey
  - ILS, command-line/util usage, version
- [Linking with ORCIDs, CHORUS, FundRef](https://github.com/vufind-org/vufind/pull/774)
- More metadata, linked-data linking
