# Архитектура (слои)

## Core (протокол)

JSON-RPC 2.0 сообщения + stateful-сессии (id, capabilities, errors, cancel). MCP поверх JSON-RPC — это базис спеки.

Доменные сущности MCP: Tools, Resources, Prompts, Sessions/Capabilities.
Промпты — часть стандартной модели, клиент их «листит» и получает аргументы/контент.

## Transports (адаптеры)

stdio (локальные/CLI-интеграции, высокий перформанс, 1-к-1 соединение). Рекомендовано поддерживать.
modelcontextprotocol.info

Streamable HTTP (HTTP POST + серверные стримы). В актуальной доке это «Streamable HTTP», в старых гайдах встречается SSE; многие ресурсы помечают SSE как устаревающее в пользу Streamable HTTP.
Model Context Protocol
+1

WebSocket (план) — есть SEP-предложение для добавления WS-транспорта (долгоживущие двунаправленные сессии). Заложите интерфейс под будущий адаптер.
GitHub

## Codecs

JSON (UTF-8) — единственный обязательный кодек; строгая сериализация/десериализация JSON-RPC и MCP-payload.
modelcontextprotocol.info

## Абстракции

TransportInterface — send(), receive(), close(), duplex/half-duplex флаги.

ProtocolClientInterface — init(), listTools(), callTool(), listResources(), readResource(), listPrompts(), getPrompt(), notifications/requests.

ServerInterface — onRequest(), onNotification(), capability negotiation; router методов: "tools/call", "resources/read", "prompts/list|get", и т.д. (по спекам MCP).
Model Context Protocol

MiddlewarePipeline — логирование, ретраи, таймауты, метрики.

AuthProviderInterface — токены/подписи на уровне транспорта (заголовки HTTP) либо out-of-band для stdio.

События и конкурентность

Реактивная модель (ReactPHP/amp) для неблокирующего IO и стриминга.

Очередь запросов с map по id → DeferredPromise.

Канал нотификаций сервера (server→client) обязателен для streamable сценариев.
Model Context Protocol

## Пакетирование (Composer моно-репо)

- mcp/core — типы, ошибки, JSON-RPC фрейм, валидация схем (JSON Schema).

- mcp/client — клиентский SDK (в т.ч. high-level фасад).

- mcp/server — минимальный серверный рантайм + роутинг методов.

- mcp/transport-stdio, mcp/transport-streamhttp (и позже …-websocket).

- mcp/codec-json — маппинг структур MCP ↔ PHP DTO (psalm/phpstan с жёсткими типами).

Реальные PHP-клиенты уже есть — можно вдохновиться API-формами и тестовым покрытием: php-mcp/client (GitHub/Packagist; поддержка stdio и http+sse/streaming, discovery Tools/Resources/Prompts).

## Рекомендованные PSR и утилиты

- PSR-7/17 для HTTP сообщений; PSR-18 как HTTP-клиент (Streamable HTTP адаптер).
- PSR-3 логирование; PSR-14 события (уведомления MCP → доменные события).
- PSR-11 контейнер (опционально для DI серверных тулов).
- что-то для неблокирующего IO (stdio/HTTP стриминг)

## Контракты (эскиз)

```php
interface TransportInterface {
public function open(): void;
public function send(JsonRpcMessage $msg): void;
/** @return iterable<JsonRpcMessage> */
public function incoming(): iterable;
public function close(): void;
}

interface ProtocolClientInterface {
public function init(Capabilities $want): Capabilities;  // mcp.init
/** @return ToolInfo[] */  public function listTools(): array;        // tools/list
public function callTool(string $name, array $args, ?CancellationToken $c = null): ToolResult; // tools/call
/** @return PromptInfo[] */public function listPrompts(): array;      // prompts/list
public function getPrompt(string $name, array $args = []): Prompt;    // prompts/get
/** @return ResourceInfo[] */ public function listResources(): array;  // resources/list
public function readResource(string $uri): Resource;                   // resources/read
}
```

## Серверный рантайм

Декларативный реестр возможностей:

ToolProvider (name, schema, callable),

ResourceProvider (URI scheme handler),

PromptProvider (шаблоны + аргументы).

Автоматический capabilities handshake при mcp.init.
Model Context Protocol

Роутер методов (JSON-RPC): "tools/*", "resources/*", "prompts/*", "notifications/*".

## Транспорты: детали реализации

stdio: неблокирующее чтение STDIN, потоковая запись STDOUT, фрейминг по строкам/длине; graceful shutdown по SIGINT/SIGTERM. Спека прямо рекомендует поддерживать stdio.
modelcontextprotocol.info

Streamable HTTP:

client→server: HTTP POST (batch/one-shot JSON-RPC).

server→client: стрим обновлений (event stream / chunked), единый endpoint «подписки». Официальная дока описывает «streamable HTTP» как базовый способ стрима.
Model Context Protocol

WebSocket (позже): выделите TransportInterface так, чтобы добавить WS без ломающего refactor — SEP есть.
GitHub

## Надёжность

- Cancellation & timeouts: токены отмены на клиенте; сервер должен обрабатывать cancel и освобождать ресурсы.
- Backpressure: ограничение параллельных запросов; очереди для входящих server→client нотификаций.
- Idempotency: повтор запросов по таймауту — опциональные requestId и дедупликация на сервере.
- Schema-validation: validate входных/выходных payload (JSON Schema от MCP-спеки).
- Observability: корелляция traceId в JSON-RPC метаданных; метрики (RPS/latency/error rate) по методам MCP.

###  Мини-пример: клиент вызывает тул

```php
$client = new McpClient(
transport: new StreamableHttpTransport('https://mcp.example/api', $httpClient),
logger: $logger
);

$client->init(Capabilities::all());
$tool = $client->listTools()['jira.updateIssue'] ?? null;
$result = $client->callTool('jira.updateIssue', ['id' => 'WEB-512', 'priority' => 'High']);
// → далее маппим в ответ агента
```

## Тестирование

Протокольные тесты: golden-файлы JSON-RPC (вход/выход) для всех методов MCP.

Transport fakes: in-memory двунаправленный pipe для детерминированных тестов.

Compat-matrix: stdio + streamable HTTP; бенчмарк latency/throughput.

Прогон против существующего клиента (php-mcp/client) как reference.
