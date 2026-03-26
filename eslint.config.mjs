import js from "@eslint/js";
import jsdoc from "eslint-plugin-jsdoc";
import noJquery from "eslint-plugin-no-jquery";
import globals from "globals";

export default [
  {
    ignores: [
      "themes/**/vendor/**",
      "themes/**/node_modules/**"
    ]
  },
  js.configs.recommended,
  {
    plugins: {
      jsdoc,
      "no-jquery": noJquery
    },
    languageOptions: {
      ecmaVersion: 2015,
      sourceType: "script",
      globals: {
        ...globals.browser,
        ...globals.jquery
      }
    },
    rules: {
      // errors
      "block-scoped-var": "error",
      "func-names": ["error", "as-needed"],
      "no-loop-func": "error",
      "no-param-reassign": "error",
      "no-shadow": "error",
      "no-unused-expressions": "error",
      "no-use-before-define": "error",

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

      // no-jquery deprecated rules (expanded from plugin:no-jquery/deprecated)
      "no-jquery/no-and-self": "warn",
      "no-jquery/no-bind": "warn",
      "no-jquery/no-box-model": "warn",
      "no-jquery/no-browser": "warn",
      "no-jquery/no-camel-case": "warn",
      "no-jquery/no-context-prop": "warn",
      "no-jquery/no-delegate": "warn",
      "no-jquery/no-error-shorthand": "warn",
      "no-jquery/no-event-shorthand": ["warn", {}],
      "no-jquery/no-fx-interval": "warn",
      "no-jquery/no-hold-ready": "warn",
      "no-jquery/no-is-array": "warn",
      "no-jquery/no-is-function": "warn",
      "no-jquery/no-is-numeric": "warn",
      "no-jquery/no-is-window": "warn",
      "no-jquery/no-live": "warn",
      "no-jquery/no-load-shorthand": "warn",
      "no-jquery/no-node-name": "warn",
      "no-jquery/no-now": "warn",
      "no-jquery/no-on-ready": "warn",
      "no-jquery/no-parse-json": "warn",
      "no-jquery/no-proxy": "warn",
      "no-jquery/no-ready-shorthand": "warn",
      "no-jquery/no-selector-prop": "warn",
      "no-jquery/no-size": "warn",
      "no-jquery/no-sizzle": ["warn", { "allowPositional": false, "allowOther": true }],
      "no-jquery/no-sub": "warn",
      "no-jquery/no-support": "warn",
      "no-jquery/no-trim": "warn",
      "no-jquery/no-type": "warn",
      "no-jquery/no-unique": "warn",
      "no-jquery/no-unload-shorthand": "warn",

      // jsdoc rules (Recommended)
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
  }
];
