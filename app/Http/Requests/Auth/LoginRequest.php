<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to log the user in.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        /*
         * `is_active` is part of the credentials, not a check afterwards.
         *
         * Checking it post-login would mean a suspended account briefly holds a
         * valid session; including it here means the attempt simply fails.
         */
        $credentials = [
            ...$this->only('email', 'password'),
            'is_active' => true,
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            // One generic message for wrong password, unknown email and
            // deactivated account alike — anything more specific tells an
            // attacker which emails are registered.
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /** @throws ValidationException */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /** Keyed on email AND IP, so one attacker cannot lock out a real user. */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
