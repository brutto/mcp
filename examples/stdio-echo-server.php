#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';
use AntonBrutto\McpServer\SimpleServer;
use AntonBrutto\McpTransportStdio\StdioTransport;
$server = new SimpleServer();
$transport = new StdioTransport();
$transport->open();
foreach ($transport->incoming() as $msg) {
    $resp = $server->onRequest($msg);
    if ($resp) { $transport->send($resp); }
}
