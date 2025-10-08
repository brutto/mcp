#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use AntonBrutto\McpServer\{Registry, Server};
use AntonBrutto\McpServer\Tools\EchoTool;
use AntonBrutto\McpServer\Prompts\ReleaseNotesPrompt;
use AntonBrutto\McpServer\Resources\FsResource;
use AntonBrutto\McpTransportStdio\StdioTransport;

$registry = new Registry();
$registry->addTool(new EchoTool());
$registry->addPrompt(new ReleaseNotesPrompt());
$registry->addResource(new FsResource(__DIR__ . '/../'));

$server = new Server($registry);

$in = fopen('./.cache/mcp-test/in', 'r+'); // сервер читает
$out = fopen('./.cache/mcp-test/out', 'w+'); // сервер пишет

$transport = new StdioTransport($in, $out);
$transport->open();

$running = true;
$shutdown = static function () use (&$running, $transport): void {
    if (!$running) {
        return;
    }
    $running = false;
    $transport->close();
};

$signalSupport = false;
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, $shutdown);
    pcntl_signal(SIGINT, $shutdown);
    $signalSupport = true;
} elseif (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, $shutdown);
    pcntl_signal(SIGINT, $shutdown);
    $signalSupport = true;
}

try {
    foreach ($transport->incoming() as $payload) {
        if (!$running) {
            break;
        }
        if ($signalSupport && function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }

        if (is_array($payload)) {
            $responses = $server->handleBatch($payload);
            if ($responses !== []) {
                $transport->sendBatch($responses);
            }
            continue;
        }

        $resp = $server->handle($payload);
        if ($resp) {
            $transport->send($resp);
        }
    }
} finally {
    $shutdown();
}
