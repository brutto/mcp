#!/usr/bin/env php
<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use AntonBrutto\McpClient\McpClient;
use AntonBrutto\McpTransportStdio\StdioTransport;
use AntonBrutto\McpCore\Capabilities;

$in  = fopen('./.cache/mcp-test/out', 'r+'); // клиент читает то, что сервер ПИШЕТ
$out = fopen('./.cache/mcp-test/in',  'r+'); // клиент пишет туда, где сервер ЧИТАЕТ

$client = new McpClient(new StdioTransport($in, $out));
$caps = $client->init(Capabilities::all());
var_dump($caps);

$batch = $client->requestBatch([
    'tools' => ['method' => 'tools/list'],
    'prompts' => ['method' => 'prompts/list'],
]);

var_dump($batch);

$tools = $batch['tools']->result['tools'] ?? [];
var_dump($tools);

$prompts = $batch['prompts']->result['prompts'] ?? [];
var_dump($prompts);

$result = $client->callTool('echo', ['text' => 'Hello world']);
var_dump($result);
