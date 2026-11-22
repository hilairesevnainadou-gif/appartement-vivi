<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /** L'accueil s'affiche, même sans aucun logement en base. */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Des logements prêts à vous accueillir');
    }
}
