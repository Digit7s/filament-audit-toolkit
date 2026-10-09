<?php

return [
    'navigation_group' => 'Audit',
    'navigation_sort' => 1,

    'diff' => [
        'default_style' => 'unified',
        'available_styles' => [
            'unified',
            'split',
            'fields',
        ],
        'allow_style_switching' => false,
        'max_depth' => 5,
        'max_entries' => 100,
        'max_value_length' => 2_000,
        'max_array_elements' => 100,
    ],

    'json_viewer' => [
        'default_mode' => 'tree',
        'max_depth' => 5,
        'max_entries' => 200,
        'max_value_length' => 2_000,
        'allow_copy' => true,
    ],
];
