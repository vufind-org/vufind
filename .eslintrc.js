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

    // jsdoc rules

    // Recommended
    "jsdoc/check-access": "error",
    "jsdoc/check-alignment": "error",
    "jsdoc/check-param-names": "error",
    "jsdoc/check-property-names": "error",
    "jsdoc/check-tag-names": "error",
    "jsdoc/check-types": "error",
    "jsdoc/check-values": "error",
    "jsdoc/empty-tags": "error",
    "jsdoc/implements-on-classes": "error",
    "jsdoc/multiline-blocks": "error",
    "jsdoc/no-multi-asterisks": "error",
    "jsdoc/no-undefined-types": "error",
    "jsdoc/require-jsdoc": "error",
    "jsdoc/require-param": "error",
    "jsdoc/require-param-description": "error",
    "jsdoc/require-param-name": "error",
    "jsdoc/require-param-type": "error",
    "jsdoc/require-property": "error",
    "jsdoc/require-property-description": "error",
    "jsdoc/require-property-name": "error",
    "jsdoc/require-property-type": "error",
    "jsdoc/require-returns": "error",
    "jsdoc/require-returns-check": "error",
    "jsdoc/require-returns-description": "error",
    "jsdoc/require-returns-type": "error",
    "jsdoc/require-yields": "error",
    "jsdoc/require-yields-check": "error",
    "jsdoc/tag-lines": "error",
    "jsdoc/valid-types": "error"
    // Disabled
    //"jsdoc/check-examples": "error",
    //"jsdoc/check-indentation": "error",
    //"jsdoc/check-line-alignment": "error",
    //"jsdoc/check-template-names": "error",
    //"jsdoc/check-syntax": "error",
    //"jsdoc/informative-docs": "error",
    //"jsdoc/match-description": "error",
    //"jsdoc/no-bad-blocks": "error",
    //"jsdoc/no-blank-block-descriptions": "error",
    //"jsdoc/no-defaults": "error",
    //"jsdoc/no-missing-syntax": "error",
    //"jsdoc/no-restricted-syntax": "error",
    //"jsdoc/no-types": "error",
    //"jsdoc/require-asterisk-prefix": "error",
    //"jsdoc/require-description": "error",
    //"jsdoc/require-description-complete-sentence": "error",
    //"jsdoc/require-example": "error",
    //"jsdoc/require-file-overview": "error",
    //"jsdoc/require-hyphen-before-param-description": "error",
    //"jsdoc/require-template": "error",
    //"jsdoc/require-throws": "error",
    //"jsdoc/sort-tags": "error",
  }
};
