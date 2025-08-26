module.exports = {
  plugins: ["no-jquery", "jsdoc"],
  ignorePatterns: [
    "themes/**/vendor/**",
    "themes/**/node_modules/**"
  ],
  extends: ["eslint:recommended", "plugin:no-jquery/deprecated"],
  env: {
    "browser": true,
    "es6": true,
    "jquery": true
  },
  rules: {
    // errors
    "block-scoped-var": "error",
    "func-names": ["error", "as-needed"],
    "no-loop-func": "error",
    "no-param-reassign": "error",
    "no-shadow": "error",
    "no-unused-expressions": "error",

    // warnings
    "dot-notation": "warn",
    "eqeqeq": ["warn", "smart"],
    "guard-for-in": "warn",
    "key-spacing": ["warn", { "beforeColon": false, "afterColon": true }],
    "no-lonely-if": "warn",
    "no-console": ["warn", { "allow": ["warn", "error"] }],
    "no-unneeded-ternary": "warn",

    // fixed automatically
    "block-spacing": ["warn", "always"],
    "comma-spacing": ["warn", { "before": false, "after": true }],
    "indent": ["error", 2],
    "keyword-spacing": ["warn", { "before": true, "after": true }],
    "linebreak-style": ["error", "unix"],
    "no-multi-spaces": "warn",
    "semi-spacing": ["warn", { "before": false, "after": true }],
    "space-infix-ops": "warn",

    // the following is required for Bootstrap 3 collapse:
    "no-jquery/no-support": "off",

    // jsdoc rules

    // Recommended
    "jsdoc/check-access": 1,
    "jsdoc/check-alignment": 1,
    "jsdoc/check-param-names": 1,
    "jsdoc/check-property-names": 1,
    "jsdoc/check-tag-names": 1,
    "jsdoc/check-types": 1,
    "jsdoc/check-values": 1,
    "jsdoc/empty-tags": 1,
    "jsdoc/implements-on-classes": 1,
    "jsdoc/multiline-blocks": 1,
    "jsdoc/no-multi-asterisks": 1,
    "jsdoc/no-undefined-types": 1,
    "jsdoc/require-jsdoc": 1,
    "jsdoc/require-param": 1,
    "jsdoc/require-param-description": 1,
    "jsdoc/require-param-name": 1,
    "jsdoc/require-param-type": 1,
    "jsdoc/require-property": 1,
    "jsdoc/require-property-description": 1,
    "jsdoc/require-property-name": 1,
    "jsdoc/require-property-type": 1,
    "jsdoc/require-returns": 1,
    "jsdoc/require-returns-check": 1,
    "jsdoc/require-returns-description": 1,
    "jsdoc/require-returns-type": 1,
    "jsdoc/require-yields": 1,
    "jsdoc/require-yields-check": 1,
    "jsdoc/tag-lines": 1,
    "jsdoc/valid-types": 1
    // Disabled
    //"jsdoc/check-examples": 1,
    //"jsdoc/check-indentation": 1,
    //"jsdoc/check-line-alignment": 1,
    //"jsdoc/check-template-names": 1,
    //"jsdoc/check-syntax": 1,
    //"jsdoc/informative-docs": 1,
    //"jsdoc/match-description": 1,
    //"jsdoc/no-bad-blocks": 1,
    //"jsdoc/no-blank-block-descriptions": 1,
    //"jsdoc/no-defaults": 1,
    //"jsdoc/no-missing-syntax": 1,
    //"jsdoc/no-restricted-syntax": 1,
    //"jsdoc/no-types": 1,
    //"jsdoc/require-asterisk-prefix": 1,
    //"jsdoc/require-description": 1,
    //"jsdoc/require-description-complete-sentence": 1,
    //"jsdoc/require-example": 1,
    //"jsdoc/require-file-overview": 1,
    //"jsdoc/require-hyphen-before-param-description": 1,
    //"jsdoc/require-template": 1,
    //"jsdoc/require-throws": 1,
    //"jsdoc/sort-tags": 1,
  }
};
