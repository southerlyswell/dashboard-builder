<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use App\Services\DashboardAIService;

class DashboardAIServiceTest extends \Tests\TestCase
{
    #[Test]
    public function it_returns_array_with_content_key(): void
    {
        $service = new DashboardAIService();
        $result = $service->chat('Hello', [], null);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertIsString($result['content']);
        $this->assertGreaterThan(0, strlen($result['content']));
    }

    #[Test]
    public function it_returns_non_empty_response_for_basic_chat(): void
    {
        $service = new DashboardAIService();
        $result = $service->chat('Say "OK" in one word', [], null);
        
        $this->assertNotEmpty($result['content']);
    }

    #[Test]
    public function it_handles_empty_context(): void
    {
        $service = new DashboardAIService();
        $result = $service->chat('Hello', [], null);
        
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('role', $result);
    }
}
