<?php

namespace App\Services\Trello;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class TrelloClient
{
    private PendingRequest $http;

    public function __construct()
    {
        $baseUrl = config('services.trello.base_url');
        $key = config('services.trello.key');
        $token = config('services.trello.token');

        $this->http = Http::baseUrl($baseUrl)
            ->timeout(15)
            ->retry(3, 250)
            ->acceptJson()
            ->asJson()
            ->withQueryParameters([
                'key' => $key,
                'token' => $token,
            ]);
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
}
