<?php

use Illuminate\Support\Facades\Broadcast;

it('authorizes broadcasting requests', function () {
    Broadcast::shouldReceive('auth')
        ->once()
        ->andReturn(response(['auth' => 'signed-token']));

    $response = $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-tenant.1',
        'socket_id' => '123.456',
    ]);

    $response->assertStatus(200)
        ->assertJson(['auth' => 'signed-token']);
});
