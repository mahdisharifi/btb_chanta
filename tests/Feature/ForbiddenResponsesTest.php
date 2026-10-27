<?php

namespace Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ForbiddenResponsesTest extends TestCase
{
    public function test_forbidden_responses_are_concealed(): void
    {
        foreach ([false, true] as $debug) {
            config(['app.debug' => $debug]);

            foreach (['abort', 'authorization', 'response'] as $kind) {
                foreach (['_test', 'admin/_test', 'api/_test'] as $prefix) {
                    $url = '/'.$prefix.'/forbidden/'.$kind;
                    Route::get($url, function () use ($kind) {
                        if ($kind === 'abort') {
                            abort(403, 'SECRET-ACCESS-DETAIL');
                        }

                        if ($kind === 'authorization') {
                            throw new AuthorizationException('SECRET-ACCESS-DETAIL');
                        }

                        return response()->json(['message' => 'SECRET-ACCESS-DETAIL'], 403);
                    });

                    foreach ([[], ['Accept' => 'application/json'], ['X-Livewire' => 'true'], ['X-Livewire' => 'true', 'Accept' => 'application/json']] as $headers) {
                        $response = $this->get($url, $headers);
                        $response->assertNotFound()->assertDontSee('SECRET-ACCESS-DETAIL');
                        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

                        if (isset($headers['Accept']) || $prefix === 'api/_test') {
                            $response->assertJsonPath('message', 'Not Found');
                        }

                        if ($prefix === 'api/_test') {
                            $response->assertJsonPath('success', false);
                        }
                    }
                }
            }
        }
    }

    public function test_other_status_codes_are_preserved(): void
    {
        foreach ([200, 401, 404, 419, 422, 429, 500] as $status) {
            Route::get('/_test/status/'.$status, fn () => response('unchanged', $status));

            $this->get('/_test/status/'.$status)->assertStatus($status)->assertSee('unchanged');
        }
    }
}
