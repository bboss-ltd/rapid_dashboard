<?php

namespace App\Services\FourJaw;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class FourJawClient
{
    private PendingRequest $http;

    public function __construct()
    {
        $baseUrl = config('services.fourjaw.base_url');
        $token = config('services.fourjaw.token');

        $this->http = Http::baseUrl($baseUrl)
            ->timeout(15)
            ->withCookies([
                'access_token' => $token,
                ],
                'rapidaccessltd.fourjaw.app')
            ->retry(3, 250)
            ->acceptJson()
            ->asJson();
    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function get(string $uri, array $query = []): array
    {
        return $this->http->get($uri, $query)->throw()->json();
    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function put(string $uri, array $data = [], array $query = []): array
    {
        // Trello often expects form-encoded, but JSON generally works for these endpoints.
        return $this->http->put($uri, $data + $query)->throw()->json();
    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function post(string $uri, array $data = [], array $query = []): array
    {
        // Trello often expects form-encoded, but JSON generally works for these endpoints.
        return $this->http->withQueryParameters($query)->post($uri, $data)->throw()->json();
    }
}
