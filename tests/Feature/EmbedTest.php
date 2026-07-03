<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;

class EmbedTest extends \Tests\TestCase
{
    #[Test]
    public function embed_endpoint_returns_200(): void
    {
        $response = $this->get('/embed/test123');
        
        $response->assertStatus(200);
    }

    #[Test]  
    public function embed_page_contains_expected_content(): void
    {
        $response = $this->get('/embed/test123');
        
        $response->assertSee('Embed Loaded', false);
    }
}
