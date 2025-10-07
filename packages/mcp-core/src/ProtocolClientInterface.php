<?php
declare(strict_types=1);

namespace AntonBrutto\McpCore;

interface ProtocolClientInterface
{
    /** Negotiate capabilities with the server and return the accepted subset. */
    public function init(Capabilities $want): Capabilities;
    /** Fetch metadata for all available tools (tools/list). */
    /** @return array<int,array{name:string,description?:string,parameters?:array}> */
    public function listTools(): array;

    /** Invoke a remote tool with the provided arguments (tools/call). */
    public function callTool(string $name, array $args): array;
    /** Retrieve the catalog of prompt templates (prompts/list). */
    /** @return array<int,array{name:string,description?:string,arguments?:array}> */
    public function listPrompts(): array;

    /** Resolve a specific prompt with optional arguments (prompts/get). */
    public function getPrompt(string $name, array $args = []): array;
    /** List discoverable resources exposed by the server (resources/list). */
    /** @return array<int,array{uri:string,name?:string,mime?:string}> */
    public function listResources(): array;

    /** Fetch the contents of a resource by URI (resources/read). */
    public function readResource(string $uri): array;
}
