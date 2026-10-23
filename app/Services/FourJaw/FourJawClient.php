<?php

namespace App\Services\FourJaw;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class FourJawClient
{
    private PendingRequest $http;

    public function __construct()
    {
        $this->http = Http::baseUrl(config('services.fourjaw.base_url'))
            ->withToken((string) config('services.fourjaw.token'))
            ->timeout(15)
            ->retry(3, 250, fn (Throwable $e) => $this->isTransient($e), throw: false)
            ->acceptJson()
            ->asJson();
    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function post(string $uri, array $data = []): array
    {
        return $this->http->post($uri, $data)->throw()->json();
    }

    private function isTransient(Throwable $e): bool
    {
        return $e instanceof ConnectionException
            || ($e instanceof RequestException && $e->response->serverError());
    }
}
