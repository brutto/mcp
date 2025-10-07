#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';
use AntonBrutto\McpClient\McpClient;
use AntonBrutto\McpTransportStdio\StdioTransport;
use AntonBrutto\McpCore\Capabilities;
$client = new McpClient(new StdioTransport());
$client->init(Capabilities::all());
$client->callTool('echo', ['hello' => 'world']);
