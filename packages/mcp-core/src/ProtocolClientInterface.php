<?php
declare(strict_types=1);

namespace AntonBrutto\McpCore;

interface ProtocolClientInterface
{
    /**
     * Negotiate capabilities with the server and return the accepted subset.
     * Optional cancellation token/timeout allow aborting handshakes.
     */
    public function init(Capabilities $want, ?CancellationToken $cancel = null, ?float $timeoutSeconds = null): Capabilities;
    /**
     * Fetch metadata for all available tools (tools/list).
     * @return array<int,array{name:string,description?:string,parameters?:array}>
     */
    public function listTools(?CancellationToken $cancel = null, ?float $timeoutSeconds = null): array;

    /** Invoke a remote tool with the provided arguments (tools/call). */
    public function callTool(string $name, array $args, ?CancellationToken $cancel = null, ?float $timeoutSeconds = null): array;
    /**
     * Retrieve the catalog of prompt templates (prompts/list).
     * @return array<int,array{name:string,description?:string,arguments?:array}>
     */
    public function listPrompts(?CancellationToken $cancel = null, ?float $timeoutSeconds = null): array;

    /** Resolve a specific prompt with optional arguments (prompts/get). */
    public function getPrompt(string $name, array $args = [], ?CancellationToken $cancel = null, ?float $timeoutSeconds = null): array;
    /**
     * List discoverable resources exposed by the server (resources/list).
     * @return array<int,array{uri:string,name?:string,mime?:string}>
     */
    public function listResources(?CancellationToken $cancel = null, ?float $timeoutSeconds = null): array;

    /** Fetch the contents of a resource by URI (resources/read). */
    public function readResource(string $uri, ?CancellationToken $cancel = null, ?float $timeoutSeconds = null): array;
}
