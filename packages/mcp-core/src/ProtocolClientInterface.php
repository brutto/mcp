<?php
declare(strict_types=1);
namespace AntonBrutto\McpCore;
interface ProtocolClientInterface {
    public function init(Capabilities $want): Capabilities;
    /** @return array<int,array{name:string,description?:string,parameters?:array}> */
    public function listTools(): array;
    public function callTool(string $name, array $args): array;
    /** @return array<int,array{name:string,description?:string,arguments?:array}> */
    public function listPrompts(): array;
    public function getPrompt(string $name, array $args = []): array;
    /** @return array<int,array{uri:string,name?:string,mime?:string}> */
    public function listResources(): array;
    public function readResource(string $uri): array;
}
