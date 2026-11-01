<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

class OpenApiController
{
    public function spec(Request $request): void
    {
        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title'       => 'NChanger API',
                'description' => 'Official REST API for NChanger Windows Client, Android Services, and Management Portal.',
                'version'     => '1.1.0'
            ],
            'servers' => [
                ['url' => '/api/v1', 'description' => 'Default API v1 Server']
            ],
            'paths' => [
                '/system/status' => [
                    'get' => [
                        'tags' => ['System'],
                        'summary' => 'System status ping',
                        'responses' => ['200' => ['description' => 'System online status']]
                    ]
                ],
                '/auth/login' => [
                    'post' => [
                        'tags' => ['Authentication'],
                        'summary' => 'User & client authentication',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'username' => ['type' => 'string'],
                                            'password' => ['type' => 'string']
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'responses' => ['200' => ['description' => 'Access and refresh tokens']]
                    ]
                ],
                '/models' => [
                    'get' => [
                        'tags' => ['Hardware Models'],
                        'summary' => 'List supported hardware models',
                        'parameters' => [
                            ['name' => 'brand', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'codename', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string']]
                        ],
                        'responses' => ['200' => ['description' => 'Array of hardware models']]
                    ],
                    'post' => [
                        'tags' => ['Hardware Models'],
                        'summary' => 'Create a new hardware model',
                        'responses' => ['201' => ['description' => 'Model created']]
                    ]
                ],
                '/models/{id}' => [
                    'get' => [
                        'tags' => ['Hardware Models'],
                        'summary' => 'Get model details',
                        'responses' => ['200' => ['description' => 'Model details']]
                    ],
                    'patch' => [
                        'tags' => ['Hardware Models'],
                        'summary' => 'Update model parameters',
                        'responses' => ['200' => ['description' => 'Model updated']]
                    ],
                    'delete' => [
                        'tags' => ['Hardware Models'],
                        'summary' => 'Disable a hardware model',
                        'responses' => ['200' => ['description' => 'Model disabled']]
                    ]
                ],
                '/models/{id}/change-profile' => [
                    'get' => [
                        'tags' => ['Hardware Models'],
                        'summary' => 'Get active change profile & policy for a model',
                        'responses' => ['200' => ['description' => 'Model, ROM and Change Profile with Policy JSON']]
                    ]
                ],
                '/change-profiles' => [
                    'get' => [
                        'tags' => ['Change Profiles'],
                        'summary' => 'List operational change profiles and workflow policies',
                        'responses' => ['200' => ['description' => 'Array of change profiles']]
                    ],
                    'post' => [
                        'tags' => ['Change Profiles'],
                        'summary' => 'Create change profile with workflow code and policy JSON',
                        'responses' => ['201' => ['description' => 'Change profile created']]
                    ]
                ],
                '/change-profiles/{id}' => [
                    'get' => [
                        'tags' => ['Change Profiles'],
                        'summary' => 'Get change profile details',
                        'responses' => ['200' => ['description' => 'Change profile details']]
                    ],
                    'patch' => [
                        'tags' => ['Change Profiles'],
                        'summary' => 'Update change profile policy or workflow code',
                        'responses' => ['200' => ['description' => 'Change profile updated']]
                    ],
                    'delete' => [
                        'tags' => ['Change Profiles'],
                        'summary' => 'Disable change profile',
                        'responses' => ['200' => ['description' => 'Change profile disabled']]
                    ]
                ],
                '/rom/compatibility' => [
                    'post' => [
                        'tags' => ['ROM Compatibility'],
                        'summary' => 'Pre-change validation check called by NChanger.exe',
                        'description' => 'Validates Model and ROM compatibility immediately before workflow execution. Returns workflow code or forces Safe Mode.',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'model_id' => ['type' => 'integer'],
                                            'rom_id'   => ['type' => 'integer'],
                                            'device'   => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'codename'        => ['type' => 'string'],
                                                    'android_version' => ['type' => 'string'],
                                                    'api_level'       => ['type' => 'integer']
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => ['description' => 'Compatibility result (Supported with workflow or Safe Mode)']
                        ]
                    ]
                ],
                '/license/check' => [
                    'post' => [
                        'tags' => ['License'],
                        'summary' => 'Check license validity and device slots',
                        'responses' => ['200' => ['description' => 'License validity details']]
                    ]
                ],
                '/device/register' => [
                    'post' => [
                        'tags' => ['Device'],
                        'summary' => 'Register device and issue UUID',
                        'responses' => ['200' => ['description' => 'Registered device information']]
                    ]
                ],
                '/device/heartbeat' => [
                    'post' => [
                        'tags' => ['Device'],
                        'summary' => 'Device keepalive & online status reporting',
                        'responses' => ['200' => ['description' => 'Heartbeat acknowledged']]
                    ]
                ]
            ]
        ];

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function docs(Request $request): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NChanger API Documentation (v1.1.0)</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        body { margin: 0; background: #0f172a; }
        .swagger-ui .topbar { display: none; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        window.onload = () => {
            window.ui = SwaggerUIBundle({
                url: '/api/v1/openapi.json',
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIBundle.SwaggerUIStandalonePreset
                ],
                layout: "BaseLayout"
            });
        };
    </script>
</body>
</html>
HTML;
        exit;
    }
}
