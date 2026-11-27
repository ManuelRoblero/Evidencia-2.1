<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // Fase 1: la raíz lleva al panel del personal y su login carga bien.
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/dashboard/login')->assertStatus(200);
    }
}
