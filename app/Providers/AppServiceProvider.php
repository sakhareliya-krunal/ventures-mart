<?php

namespace App\Providers;

use App\Listeners\PersistFailedJobApplicationErrors;
use App\Listeners\PersistLoggedApplicationErrors;
use App\Services\ApplicationErrorRecorder;
use Composer\CaBundle\CaBundle;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ApplicationErrorRecorder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Http::globalOptions([
            'verify' => CaBundle::getSystemCaRootBundlePath(),
        ]);

        Password::defaults(fn () => Password::min(8));

        ResetPassword::createUrlUsing(function (object $user, string $token) {
            return url('/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]));
        });

        Event::listen(MessageLogged::class, PersistLoggedApplicationErrors::class);
        Event::listen(JobFailed::class, PersistFailedJobApplicationErrors::class);
    }
}
