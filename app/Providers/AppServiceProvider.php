<?php

namespace App\Providers;

use App\Actions\Auth\EntraTokenValidator;
use App\Actions\Auth\FirebaseEntraTokenValidator;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Azure\Provider as AzureProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EntraTokenValidator::class, FirebaseEntraTokenValidator::class);

        // Telescope is a dev-only dependency and must never load in production, where
        // its classes are absent. Register it (and its app provider) only in local.
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureModels();
        $this->configureDates();
        $this->configureSocialite();
    }

    /**
     * Register the Microsoft Entra (Azure AD) driver with Socialite. The driver is only
     * usable once AZURE_* credentials are set (config/services.php); until then the
     * redirect endpoint simply cannot mint a valid request (.ai/rules/auth.md).
     */
    protected function configureSocialite(): void
    {
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('azure', AzureProvider::class);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Enforce strict Eloquent behaviour everywhere except production, so lazy-loading
     * and silently-dropped attributes surface as failures in development and tests
     * rather than as N+1 queries or lost data on the intranet (see CLAUDE.md, rule 3).
     */
    protected function configureModels(): void
    {
        $strict = ! app()->isProduction();

        Model::preventLazyLoading($strict);
        Model::preventSilentlyDiscardingAttributes($strict);
    }

    /**
     * Render Icelandic relative-time and date strings by default. The locale
     * middleware overrides this per request for English-speaking staff.
     */
    protected function configureDates(): void
    {
        Carbon::setLocale('is');
    }
}
