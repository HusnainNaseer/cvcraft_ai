<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mime\Email;

/**
 * Google OAuth + Gmail API client used to send applications from the user's own mailbox.
 *
 * A "token" is the array kept in the session: access_token, refresh_token, expires_at, email, name.
 */
class GmailClient
{
    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    private const SEND_URL = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

    private const SCOPES = 'openid email profile https://www.googleapis.com/auth/gmail.send';

    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function authorizationUrl(string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange an OAuth code for a session token that includes the account's email address.
     */
    public function connect(string $code): array
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('services.google.redirect'),
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google sign-in failed. Check GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET and the redirect URI.');
        }

        if (! str_contains((string) $response->json('scope'), 'gmail.send')) {
            throw new RuntimeException('Gmail sending permission was not granted. Please allow "Send email on your behalf".');
        }

        $token = $this->tokenFromResponse($response->json());
        $profile = Http::withToken($token['access_token'])->acceptJson()->get(self::USERINFO_URL);

        if (! $profile->successful() || blank($profile->json('email'))) {
            throw new RuntimeException('Could not read the Google account email address.');
        }

        return [
            ...$token,
            'email' => (string) $profile->json('email'),
            'name' => (string) $profile->json('name', ''),
        ];
    }

    /**
     * Return the token unchanged, or refreshed when it is about to expire.
     */
    public function freshToken(array $token): array
    {
        if (($token['expires_at'] ?? 0) > time() + 60) {
            return $token;
        }

        if (blank($token['refresh_token'] ?? null)) {
            throw new RuntimeException('Your Gmail connection expired. Please connect Gmail again.');
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $token['refresh_token'],
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Your Gmail connection expired. Please connect Gmail again.');
        }

        return [...$token, ...$this->tokenFromResponse($response->json(), $token['refresh_token'])];
    }

    /**
     * Send a message from the connected account. Returns the Gmail message id.
     */
    public function send(array $token, Email $email): string
    {
        $raw = rtrim(strtr(base64_encode($email->toString()), '+/', '-_'), '=');

        $response = Http::withToken($token['access_token'])
            ->acceptJson()
            ->timeout(60)
            ->post(self::SEND_URL, ['raw' => $raw]);

        if (! $response->successful()) {
            $reason = (string) $response->json('error.message', 'HTTP '.$response->status());
            throw new RuntimeException('Gmail could not send the email: '.$reason);
        }

        return (string) $response->json('id');
    }

    public function revoke(array $token): void
    {
        $value = $token['refresh_token'] ?? $token['access_token'] ?? null;
        if ($value) {
            Http::asForm()->post(self::REVOKE_URL, ['token' => $value]);
        }
    }

    private function tokenFromResponse(array $payload, ?string $refreshToken = null): array
    {
        return [
            'access_token' => (string) ($payload['access_token'] ?? ''),
            'refresh_token' => $payload['refresh_token'] ?? $refreshToken,
            'expires_at' => time() + (int) ($payload['expires_in'] ?? 3600),
        ];
    }
}
