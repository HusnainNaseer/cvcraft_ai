<?php

namespace App\Http\Controllers;

use App\Services\GmailClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Connects the user's Gmail account (opened in a popup so the main page keeps its state).
 */
class GoogleAuthController extends Controller
{
    public const SESSION_KEY = 'gmail_token';

    public function __construct(private readonly GmailClient $gmail)
    {
    }

    public function redirect(Request $request): RedirectResponse|View
    {
        if (! $this->gmail->isConfigured()) {
            return $this->finish(false, 'Gmail is not set up yet. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to .env.');
        }

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        return redirect()->away($this->gmail->authorizationUrl($state));
    }

    public function callback(Request $request): View
    {
        if ($request->filled('error')) {
            return $this->finish(false, 'Gmail connection was cancelled.');
        }

        $expectedState = (string) $request->session()->pull('google_oauth_state');
        if ($expectedState === '' || ! hash_equals($expectedState, (string) $request->query('state'))) {
            return $this->finish(false, 'Gmail connection could not be verified. Please try again.');
        }

        try {
            $token = $this->gmail->connect((string) $request->query('code'));
        } catch (\RuntimeException $e) {
            return $this->finish(false, $e->getMessage());
        }

        $request->session()->put(self::SESSION_KEY, $token);

        return $this->finish(true, 'Gmail connected as '.$token['email'].'.', $token['email']);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $token = $request->session()->pull(self::SESSION_KEY);
        if (is_array($token)) {
            rescue(fn () => $this->gmail->revoke($token), report: false);
        }

        return response()->json(['success' => true]);
    }

    private function finish(bool $connected, string $message, ?string $email = null): View
    {
        return view('oauth-complete', [
            'payload' => [
                'type' => 'gmail-oauth',
                'connected' => $connected,
                'message' => $message,
                'email' => $email,
            ],
        ]);
    }
}
