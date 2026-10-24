<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_login_page_is_the_front_door(): void
    {
        $this->get('/login')->assertOk();
    }
}
