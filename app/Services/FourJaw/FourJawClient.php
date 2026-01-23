<?php

namespace App\Services\FourJaw;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Cookie\CookieJar;

class FourJawClient
{
    private PendingRequest $http;
    private string $baseDomain;

    public function __construct()
    {
        $baseUrl = config('services.fourjaw.base_url');

        $this->baseDomain = (string) parse_url($baseUrl, PHP_URL_HOST);

        $this->http = Http::baseUrl($baseUrl)
            ->timeout(15)
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
        $request = $this->withAuth();

        $response = $request->get($uri, $query);
        if ($response->status() === 401 && $this->refreshAuth()) {
            $response = $this->withAuth()->get($uri, $query);
        }

        return $response->throw()->json();
    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function put(string $uri, array $data = [], array $query = []): array
    {
        $request = $this->withAuth();
        $response = $request->put($uri, $data + $query);
        if ($response->status() === 401 && $this->refreshAuth()) {
            $response = $this->withAuth()->put($uri, $data + $query);
        }

        return $response->throw()->json();
    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function post(string $uri, array $data = [], array $query = []): array
    {
        $request = $this->withAuth()->withQueryParameters($query);
        $response = $request->post($uri, $data);
        if ($response->status() === 401 && $this->refreshAuth()) {
            $response = $this->withAuth()->withQueryParameters($query)->post($uri, $data);
        }

        return $response->throw()->json();
    }

    public function login(): array
    {
        $payload = [
            'email' => config('services.fourjaw.email'),
            'password' => config('services.fourjaw.password'),
            'remember_me' => (bool) config('services.fourjaw.remember_me'),
        ];

        return $this->http->post($this->endpoint('login'), $payload)
            ->throw()
            ->json();
    }

    public function checkAuthToken(string $jwt): array
    {
        return $this->http
            ->withOptions(['cookies' => $this->cookieJar($jwt)])
            ->get($this->endpoint('check_auth'))
            ->throw()
            ->json();
    }

    private function withAuth(): PendingRequest
    {
        $token = $this->getCachedToken();

        if ($token === null) {
            $token = $this->refreshAuth()
                ? $this->getCachedToken()
                : null;
        }

        if ($token === null) {
            return $this->http;
        }

        return $this->http->withOptions(['cookies' => $this->cookieJar($token)]);
    }

    private function refreshAuth(): bool
    {
        $login = $this->login();
        $jwt = Arr::get($login, 'jwt');

        if (!is_string($jwt) || $jwt === '') {
            return false;
        }

        Cache::put($this->cacheKey(), $jwt, now()->addMinutes($this->authTtl()));
        return true;
    }

    private function getCachedToken(): ?string
    {
        $token = Cache::get($this->cacheKey());

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function cacheKey(): string
    {
        return 'fourjaw.jwt';
    }

    private function authTtl(): int
    {
        $ttl = (int) config('fourjaw.auth_cache_ttl_minutes');
        return $ttl > 0 ? $ttl : 480;
    }

    private function cookieJar(string $token): CookieJar
    {
        return CookieJar::fromArray(['access_token' => $token], $this->baseDomain);
    }

    private function endpoint(string $key): string
    {
        return (string) config("fourjaw.endpoints.{$key}");
    }
}
