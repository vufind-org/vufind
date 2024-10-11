module.exports = {
  plugins: ["jsdoc"],
  ignorePatterns: [
    "themes/**/vendor/**",
    "themes/**/node_modules/**",
    "themes/bootstrap5/js/account_ajax.js",
    "themes/bootstrap5/js/relais.js",
    "themes/bootstrap5/js/search.js",
    "themes/bootstrap5/js/requests.js",
    "themes/bootstrap5/js/resultcount.js",
    "themes/bootstrap5/js/map_tab_leaflet.js",
    "themes/bootstrap5/js/check_item_statuses.js",
    "themes/bootstrap5/js/combined-search.js",
    "themes/bootstrap5/js/cart.js",
    "themes/bootstrap5/js/pubdate_vis.js",
    "themes/bootstrap5/js/map_selection_leaflet.js",
    "themes/bootstrap5/js/common.js",
    "themes/bootstrap5/js/bs3-compat.js",
    "themes/bootstrap5/js/explain.js",
    "themes/bootstrap5/js/list_item_selection.js",
    "themes/bootstrap5/js/embedded_record.js",
    "themes/bootstrap5/js/openurl.js",
    "themes/bootstrap5/js/ill.js",
    "themes/bootstrap5/js/record.js",
    "themes/bootstrap5/js/doi.js",
    "themes/bootstrap5/js/keep_alive.js",
    "themes/bootstrap5/js/embedGBS.js",
    "themes/bootstrap5/js/lib/ajax_request_queue.js",
    "themes/bootstrap5/js/covers.js",
    "themes/bootstrap5/js/trigger_print.js",
    "themes/bootstrap5/js/visual_facets.js",
    "themes/bootstrap5/js/preview.js",
    "themes/bootstrap5/js/facets.js",
    "themes/bootstrap5/js/searchbox_controls.js",
    "themes/bootstrap5/js/lightbox.js",
    "themes/bootstrap5/js/hierarchy_tree.js",
    "themes/bootstrap5/js/cookie.js",
    "themes/bootstrap5/js/record_versions.js",
    "themes/bootstrap5/js/collection_record.js",
    "themes/bootstrap5/js/sticky_elements.js",
    "themes/bootstrap5/js/checkouts.js",
    "themes/bootstrap5/js/advanced_search.js",
    "themes/bootstrap5/js/check_save_statuses.js",
    "themes/bootstrap5/js/hold.js",
    "themes/bootstrap5/js/config.js",
    "themes/bootstrap5/js/channels.js",
    "themes/bootstrap5/js/truncate.js",
    "themes/local_mixin_example/js/mixin-popup.js",
    "themes/bootstrap3/js/account_ajax.js",
    "themes/bootstrap3/js/relais.js",
    "themes/bootstrap3/js/search.js",
    "themes/bootstrap3/js/requests.js",
    "themes/bootstrap3/js/resultcount.js",
    "themes/bootstrap3/js/map_tab_leaflet.js",
    "themes/bootstrap3/js/check_item_statuses.js",
    "themes/bootstrap3/js/combined-search.js",
    "themes/bootstrap3/js/cart.js",
    "themes/bootstrap3/js/pubdate_vis.js",
    "themes/bootstrap3/js/map_selection_leaflet.js",
    "themes/bootstrap3/js/common.js",
    "themes/bootstrap3/js/explain.js",
    "themes/bootstrap3/js/list_item_selection.js",
    "themes/bootstrap3/js/embedded_record.js",
    "themes/bootstrap3/js/openurl.js",
    "themes/bootstrap3/js/ill.js",
    "themes/bootstrap3/js/record.js",
    "themes/bootstrap3/js/doi.js",
    "themes/bootstrap3/js/keep_alive.js",
    "themes/bootstrap3/js/embedGBS.js",
    "themes/bootstrap3/js/lib/ajax_request_queue.js",
    "themes/bootstrap3/js/covers.js",
    "themes/bootstrap3/js/trigger_print.js",
    "themes/bootstrap3/js/visual_facets.js",
    "themes/bootstrap3/js/preview.js",
    "themes/bootstrap3/js/facets.js",
    "themes/bootstrap3/js/searchbox_controls.js",
    "themes/bootstrap3/js/lightbox.js",
    "themes/bootstrap3/js/hierarchy_tree.js",
    "themes/bootstrap3/js/cookie.js",
    "themes/bootstrap3/js/record_versions.js",
    "themes/bootstrap3/js/collection_record.js",
    "themes/bootstrap3/js/sticky_elements.js",
    "themes/bootstrap3/js/checkouts.js",
    "themes/bootstrap3/js/advanced_search.js",
    "themes/bootstrap3/js/check_save_statuses.js",
    "themes/bootstrap3/js/hold.js",
    "themes/bootstrap3/js/config.js",
    "themes/bootstrap3/js/channels.js",
    "themes/bootstrap3/js/truncate.js",
  ],
  extends: [],
  env: {
    "browser": true,
    "es6": true,
    "jquery": true
  },
  rules: {
    // jsDoc rules
    "jsdoc/check-access": 1, // Recommended
    "jsdoc/check-alignment": 1, // Recommended
    //"jsdoc/check-examples": 1,
    //"jsdoc/check-indentation": 1,
    //"jsdoc/check-line-alignment": 1,
    "jsdoc/check-param-names": 1, // Recommended
    //"jsdoc/check-template-names": 1,
    "jsdoc/check-property-names": 1, // Recommended
    //"jsdoc/check-syntax": 1,
    "jsdoc/check-tag-names": 1, // Recommended
    "jsdoc/check-types": 1, // Recommended
    "jsdoc/check-values": 1, // Recommended
    "jsdoc/empty-tags": 1, // Recommended
    "jsdoc/implements-on-classes": 1, // Recommended
    //"jsdoc/informative-docs": 1,
    //"jsdoc/match-description": 1,
    "jsdoc/multiline-blocks": 1, // Recommended
    //"jsdoc/no-bad-blocks": 1,
    //"jsdoc/no-blank-block-descriptions": 1,
    //"jsdoc/no-defaults": 1,
    //"jsdoc/no-missing-syntax": 1,
    "jsdoc/no-multi-asterisks": 1, // Recommended
    //"jsdoc/no-restricted-syntax": 1,
    //"jsdoc/no-types": 1,
    "jsdoc/no-undefined-types": 1, // Recommended
    //"jsdoc/require-asterisk-prefix": 1,
    //"jsdoc/require-description": 1,
    //"jsdoc/require-description-complete-sentence": 1,
    //"jsdoc/require-example": 1,
    //"jsdoc/require-file-overview": 1,
    //"jsdoc/require-hyphen-before-param-description": 1,
    "jsdoc/require-jsdoc": 1, // Recommended
    "jsdoc/require-param": 1, // Recommended
    "jsdoc/require-param-description": 1, // Recommended
    "jsdoc/require-param-name": 1, // Recommended
    "jsdoc/require-param-type": 1, // Recommended
    "jsdoc/require-property": 1, // Recommended
    "jsdoc/require-property-description": 1, // Recommended
    "jsdoc/require-property-name": 1, // Recommended
    "jsdoc/require-property-type": 1, // Recommended
    "jsdoc/require-returns": 1, // Recommended
    "jsdoc/require-returns-check": 1, // Recommended
    "jsdoc/require-returns-description": 1, // Recommended
    "jsdoc/require-returns-type": 1, // Recommended
    //"jsdoc/require-template": 1,
    //"jsdoc/require-throws": 1,
    "jsdoc/require-yields": 1, // Recommended
    "jsdoc/require-yields-check": 1, // Recommended
    //"jsdoc/sort-tags": 1,
    "jsdoc/tag-lines": 1, // Recommended
    "jsdoc/valid-types": 1 // Recommended
  }
};
