<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminErrorSpaRouteTest extends TestCase
{
    public function test_admin_error_spa_path_returns_app_shell_without_redirect_to_dashboard(): void
    {
        $response = $this->get('/admin/error');

        $response->assertOk();
        $this->assertFalse($response->isRedirect(), 'Unexpected redirect away from /admin/error');
    }
}
