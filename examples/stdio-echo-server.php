#!/usr/bin/env php
<?php
// examples/server.php
require __DIR__.'/../vendor/autoload.php';

use AntonBrutto\McpServer\{Registry, Server};
use AntonBrutto\McpServer\Tools\EchoTool;
use AntonBrutto\McpServer\Prompts\ReleaseNotesPrompt;
use AntonBrutto\McpServer\Resources\FsResource;
use AntonBrutto\McpTransportStdio\StdioTransport;

$reg = new Registry();
$reg->addTool(new EchoTool());
$reg->addPrompt(new ReleaseNotesPrompt());
$reg->addResource(new FsResource(__DIR__.'/../'));

$server = new Server($reg);
$transport = new StdioTransport();
$transport->open();
foreach ($transport->incoming() as $msg) {
    if ($resp = $server->handle($msg)) { $transport->send($resp); }
}
