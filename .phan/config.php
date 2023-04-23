<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

use Phan\Issue;

define('APP_DIR', dirname(__DIR__));

return [
    'target_php_version' => '8.1',

    'allow_missing_properties' => false,

    'null_casts_as_any_type' => false,
    'null_casts_as_array' => false,
    'array_casts_as_null' => false,

    'scalar_implicit_cast' => false,
    'scalar_array_key_cast' => false,
    'scalar_implicit_partial' => [],

    'strict_method_checking' => true,
    'strict_object_checking' => true,
    'strict_param_checking' => true,
    'strict_property_checking' => true,
    'strict_return_checking' => true,

    'ignore_undeclared_variables_in_global_scope' => false,
    'ignore_undeclared_functions_with_known_signatures' => false,

    'backward_compatibility_checks' => false,

    'check_docblock_signature_return_type_match' => true,
    'phpdoc_type_mapping' => [],

    'dead_code_detection' => false,
    'unused_variable_detection' => true,
    'redundant_condition_detection' => true,

    'assume_real_types_for_internal_functions' => true,

    'quick_mode' => false,

    'globals_type_map' => [],

    'minimum_severity' => Issue::SEVERITY_LOW,
    'suppress_issue_types' => [
        Issue::UnusedPublicMethodParameter,
        Issue::UnusedPublicFinalMethodParameter,
    ],

    'exclude_file_regex' => '@^vendor/.*/(tests?|Tests?)/@',
    'exclude_file_list' => [],
    'exclude_analysis_directory_list' => [
        APP_DIR.'/vendor/',
    ],

    'enable_include_path_checks' => true,
    'processes' => 1,
    'analyzed_file_extensions' => [
        'php',
    ],
    'autoload_internal_extension_signatures' => [],

    'plugins' => [
        'AlwaysReturnPlugin',
        'DollarDollarPlugin',
        'DuplicateArrayKeyPlugin',
        'DuplicateExpressionPlugin',
        'PregRegexCheckerPlugin',
        'PrintfCheckerPlugin',
        'SleepCheckerPlugin',
        'UnreachableCodePlugin',
        'UseReturnValuePlugin',
        'EmptyStatementListPlugin',
        'StrictComparisonPlugin',
        'LoopVariableReusePlugin',
    ],

    'directory_list' => [
        APP_DIR.'/src',
        APP_DIR.'/vendor/doctrine/orm',
        APP_DIR.'/vendor/doctrine/persistence',
        APP_DIR.'/vendor/onelogin/php-saml/src',
        APP_DIR.'/vendor/psr/log',
        APP_DIR.'/vendor/symfony/config',
        APP_DIR.'/vendor/symfony/dependency-injection',
        APP_DIR.'/vendor/symfony/deprecation-contracts',
        APP_DIR.'/vendor/symfony/event-dispatcher',
        APP_DIR.'/vendor/symfony/event-dispatcher-contracts',
        APP_DIR.'/vendor/symfony/http-foundation',
        APP_DIR.'/vendor/symfony/http-kernel',
        APP_DIR.'/vendor/symfony/routing',
        APP_DIR.'/vendor/symfony/security-bundle',
        APP_DIR.'/vendor/symfony/security-core',
        APP_DIR.'/vendor/symfony/security-http',
    ],

    'file_list' => [],
];
