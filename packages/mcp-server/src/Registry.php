<?php
declare(strict_types=1);

namespace AntonBrutto\McpServer;

use AntonBrutto\McpServer\Provider\{ToolProviderInterface, PromptProviderInterface, ResourceProviderInterface};

final class Registry
{
    /** @var ToolProviderInterface[] */
    public array $tools = [];
    /** @var PromptProviderInterface[] */
    public array $prompts = [];
    /** @var ResourceProviderInterface[] */
    public array $resources = [];

    public function addTool(ToolProviderInterface $p): void
    {
        $this->tools[$p->name()] = $p;
    }

    public function addPrompt(PromptProviderInterface $p): void
    {
        $this->prompts[] = $p;
    }

    public function addResource(ResourceProviderInterface $p): void
    {
        $this->resources[] = $p;
    }
}
