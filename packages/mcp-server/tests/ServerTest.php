<?php
declare(strict_types=1);

namespace AntonBrutto\McpServerTests;

use AntonBrutto\McpCore\JsonRpcMessage;
use AntonBrutto\McpServer\Cache\InMemoryCachePool;
use AntonBrutto\McpServer\Provider\ToolProviderInterface;
use AntonBrutto\McpServer\Registry;
use AntonBrutto\McpServer\Server;
use AntonBrutto\McpServer\Validation\SchemaValidationException;
use AntonBrutto\McpServer\Validation\SchemaValidatorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/** @covers \AntonBrutto\McpServer\Server */
final class ServerTest extends TestCase
{
    public function testToolsCallCachesByIdempotencyKey(): void
    {
        $tool = new SpyTool();
        $registry = new Registry();
        $registry->addTool($tool);
        $logger = new SpyLogger();
        $validator = new RecordingValidator();
        $server = new Server($registry, new InMemoryCachePool(), $validator, $logger);

        $request = new JsonRpcMessage('1', 'tools/call', [
            'name'      => 'spy',
            'arguments' => ['idempotencyKey' => 'abc', 'value' => 42],
        ]);

        $responseA = $server->handle($request);
        $responseB = $server->handle(new JsonRpcMessage('2', 'tools/call', [
            'name'      => 'spy',
            'arguments' => ['idempotencyKey' => 'abc', 'value' => 13],
        ]));

        self::assertSame(1, $tool->invocations);
        self::assertSame(['value' => 42], $tool->lastArgs);
        self::assertSame(['result' => ['value' => 42]], $responseA?->result);
        self::assertSame(['result' => ['value' => 42]], $responseB?->result);

        self::assertSame('Invoking tool', $logger->records[0]['message'] ?? null);
        self::assertSame('Returning cached tool result', $logger->records[1]['message'] ?? null);
        self::assertSame(['spy.arguments', 'spy.result'], $validator->contexts);
    }

    public function testToolsCallWithoutKeyInvokesEachTime(): void
    {
        $tool = new SpyTool();
        $registry = new Registry();
        $registry->addTool($tool);
        $server = new Server($registry, new InMemoryCachePool(), new RecordingValidator(), new SpyLogger());

        $request = new JsonRpcMessage('1', 'tools/call', [
            'name'      => 'spy',
            'arguments' => ['value' => 1],
        ]);

        $server->handle($request);
        $server->handle(new JsonRpcMessage('2', 'tools/call', [
            'name'      => 'spy',
            'arguments' => ['value' => 2],
        ]));

        self::assertSame(2, $tool->invocations);
    }

    public function testValidationFailureReturnsJsonRpcError(): void
    {
        $tool = new SpyTool();
        $registry = new Registry();
        $registry->addTool($tool);
        $validator = new RecordingValidator(new SchemaValidationException('spy.arguments', 'failed schema'));
        $server = new Server($registry, new InMemoryCachePool(), $validator, new SpyLogger());

        $response = $server->handle(new JsonRpcMessage('10', 'tools/call', [
            'name'      => 'spy',
            'arguments' => ['value' => 99],
        ]));

        self::assertNotNull($response);
        self::assertSame([
            'code' => 422,
            'message' => 'Payload validation failed: failed schema',
            'data' => ['context' => 'spy.arguments', 'subjectType' => 'tool', 'subjectName' => 'spy'],
        ], $response->error);
        self::assertSame(0, $tool->invocations);
    }

    public function testPromptArgumentsValidationFailureReturnsError(): void
    {
        $prompt = new StubPromptProvider();
        $registry = new Registry();
        $registry->addPrompt($prompt);
        $validator = new RecordingValidator(new SchemaValidationException('stub.arguments', 'prompt args invalid'));
        $server = new Server($registry, new InMemoryCachePool(), $validator, new SpyLogger());

        $response = $server->handle(new JsonRpcMessage('p1', 'prompts/get', [
            'name' => 'stub',
            'arguments' => [],
        ]));

        self::assertNotNull($response);
        self::assertSame([
            'code' => 422,
            'message' => 'Payload validation failed: prompt args invalid',
            'data' => ['context' => 'stub.arguments', 'subjectType' => 'prompt', 'subjectName' => 'stub'],
        ], $response->error);
    }
}

final class SpyTool implements ToolProviderInterface
{
    public int $invocations = 0;

    public array $lastArgs = [];

    public function name(): string
    {
        return 'spy';
    }

    public function description(): ?string
    {
        return 'spy';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'value' => ['type' => ['integer', 'number']],
            ],
            'required' => ['value'],
            'additionalProperties' => false,
        ];
    }

    public function invoke(array $args): array
    {
        $this->invocations++;
        $this->lastArgs = $args;

        return ['value' => $args['value'] ?? null];
    }

    public function resultSchema(): ?array
    {
        return [
            'type' => 'object',
            'properties' => ['value' => ['type' => ['integer', 'number', 'null']]],
            'required' => ['value'],
            'additionalProperties' => false,
        ];
    }
}

final class SpyLogger extends AbstractLogger
{
    /** @var list<array{level:string,message:string,context:array}> */
    public array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level'   => (string)$level,
            'message' => (string)$message,
            'context' => $context,
        ];
    }
}

final class RecordingValidator implements SchemaValidatorInterface
{
    /** @var list<string> */
    public array $contexts = [];

    public function __construct(private ?SchemaValidationException $exception = null)
    {
    }

    public function validate(array $schema, mixed $payload, string $context): void
    {
        $this->contexts[] = $context;

        if ($this->exception !== null) {
            throw $this->exception;
        }
    }
}

final class StubPromptProvider implements \AntonBrutto\McpServer\Provider\PromptProviderInterface
{
    public function listPrompts(): array
    {
        return [[
            'name' => 'stub',
            'description' => 'stub prompt',
            'arguments' => ['type' => 'object'],
        ]];
    }

    public function getPrompt(string $name, array $args = []): array
    {
        if ($name !== 'stub') {
            throw new \InvalidArgumentException();
        }

        return ['content' => 'stub', 'meta' => []];
    }

    public function argumentsSchema(string $name): ?array
    {
        if ($name !== 'stub') {
            return null;
        }

        return [
            'type' => 'object',
            'properties' => ['foo' => ['type' => 'string']],
            'required' => ['foo'],
        ];
    }
}
