<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChatApiTest extends TestCase
{
    public function test_chat_endpoint_requires_message(): void
    {
        $response = $this->postJson('/api/chat', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_chat_endpoint_accepts_valid_message(): void
    {
        $this->markTestSkipped('Requires OpenAI API key to be configured');

        $response = $this->postJson('/api/chat', [
            'message' => 'Tere!'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['reply']);
    }

    public function test_chat_endpoint_validates_message_length(): void
    {
        $response = $this->postJson('/api/chat', [
            'message' => str_repeat('a', 2001)
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }
}
