#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';
use AntonBrutto\McpClient\McpClient;
use AntonBrutto\McpTransportStdio\StdioTransport;
use AntonBrutto\McpCore\Capabilities;
$client = new McpClient(new StdioTransport());
$caps = $client->init(Capabilities::all());
$tools = $client->listTools();
$result = $client->callTool('echo', ['hello' => 'world']);
echo json_encode([
    'type' => 'summary',
    'data' => [
        'capabilities' => [
            'tools' => $caps->tools,
            'resources' => $caps->resources,
            'prompts' => $caps->prompts,
            'notifications' => $caps->notifications,
        ],
        'tools' => $tools,
        'callResult' => $result,
    ],
], JSON_UNESCAPED_SLASHES) . "\n";
