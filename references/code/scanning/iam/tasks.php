<?php

/**
 * Provides rules definition for a scan
 */

$tasks = [
    'listUsers' => [
        'task' => 'listUsers',
        'id' => 'user',
        'key' => 'Name',
        'description' => 'List all users',
        'items' => [],
        'actions' => [
            'getUser',
            'listMFADevices',
            'listAccessKeys',
            'listUserPolicies',
            'listGroupsForUser',
            'listAttachedUserPolicies'
        ]
    ],
    'getUser' => [],
    'listMFADevices' => [],
    'listAccessKeys' => [],
    'listUserPolicies' => [],
    'listGroupsForUser' => [],
    'listAttachedUserPolicies' => [],
];

$config = [
    'service' => 'iam',
    'start' => &$tasks['listUsers'],
    'defaults' => [
        'actions' => [
            'params' => function($item) {
                return [
                    'UserName' => $item['UserName']
                ];
            }
        ]
    ]
];

$tasks['listUsers']['items'] = [
    'Users' => [
        [
            'Name' => 'UserName',
            'Type' => 'String',
        ],
        [
            'Name' => 'UserId',
            'Type' => 'String',
        ],
        [
            'Name' => 'Arn',
            'Type' => 'String',
        ],
        [
            'Name' => 'CreateDate',
            'Type' => 'Date',
        ],
        [
            'Name' => 'PasswordLastUsed',
            'Type' => 'Date',
        ],
        [
            'Name' => 'Path',
            'Type' => 'String',
        ],
    ]
];

// $tasks['listUserPolicies'] = [
//     'params' => function($item) {
//         return [
//             'Filters' => [
//                 [
//                     'UserName' => $item['UserName']
//                 ]
//             ]
//         ];
//     }
// ];

// List user's AWS Console login details

return [
    'config' => $config,
    'tasks' => $tasks
];
