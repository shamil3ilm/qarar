<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Contracts\ExternalApiClient::class, \App\Services\Compliance\ZatcaClientV1::class);

        $this->app->singleton(\App\Services\Tax\VatRuleResolver::class);

        $this->app->singleton(\App\Services\Compliance\CircuitBreaker::class, function (): \App\Services\Compliance\CircuitBreaker {
            return new \App\Services\Compliance\CircuitBreaker(
                failureThreshold: (int) config('erp.circuit_breaker.threshold', 5),
                openTtlSeconds:   (int) config('erp.circuit_breaker.open_ttl', 60),
                counterTtl:       (int) config('erp.circuit_breaker.counter_ttl', 120),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configurePasswordDefaults();
        $this->configureModels();
        $this->configureDebugSettings();
        $this->registerSensitiveModelObservers();
    }

    /**
     * Configure API rate limiting.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth.login', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->input('email') . '|' . $request->ip());
        });

        RateLimiter::for('auth.register', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->ip());
        });

        // Standard reads — 120 requests per minute per user
        RateLimiter::for('api-read', function (Request $request) {
            return Limit::perMinute(120)
                ->by($request->user()?->id ?: $request->ip());
        });

        // Writes — 60 per minute per user
        RateLimiter::for('api-write', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip());
        });

        // Financial operations — 20 per minute per user
        // Applied to: invoice posting, payroll, payments, journal entries
        RateLimiter::for('api-financial', function (Request $request) {
            return Limit::perMinute(20)
                ->by($request->user()?->id ?: $request->ip());
        });

        // Bulk operations — 5 per minute per user
        RateLimiter::for('api-bulk', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Configure default password validation rules.
     */
    protected function configurePasswordDefaults(): void
    {
        Password::defaults(function () {
            $rule = Password::min(config('erp.security.password.min_length', 8));

            // Stricter rules for production
            if ($this->app->isProduction()) {
                $rule = $rule
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised();
            }

            return $rule;
        });
    }

    /**
     * Configure Eloquent model behavior.
     */
    protected function configureModels(): void
    {
        // Prevent lazy loading in non-production (catches N+1 issues)
        Model::preventLazyLoading(!$this->app->isProduction());

        // Prevent silently discarding attributes
        Model::preventSilentlyDiscardingAttributes(!$this->app->isProduction());

        // Prevent accessing missing attributes
        Model::preventAccessingMissingAttributes(!$this->app->isProduction());
    }

    /**
     * Register the sensitive-model observer for read-access audit logging.
     */
    protected function registerSensitiveModelObservers(): void
    {
        $sensitiveModels = [
            \App\Models\HR\Employee::class,
            \App\Models\HR\Payslip::class,
            \App\Models\Sales\Invoice::class,
            \App\Models\Purchase\Bill::class,
            \App\Models\Accounting\JournalEntry::class,
        ];

        foreach ($sensitiveModels as $model) {
            $model::observe(\App\Observers\SensitiveModelObserver::class);
        }

        // Snapshot observers — keep hot-table aggregates up to date asynchronously
        \App\Models\Accounting\JournalEntryLine::observe(\App\Observers\JournalEntryLineObserver::class);
        \App\Models\Inventory\StockMovement::observe(\App\Observers\StockMovementObserver::class);
    }

    /**
     * Configure debug and logging settings.
     */
    protected function configureDebugSettings(): void
    {
        // Slow query detection — log any query exceeding the threshold
        $slowQueryThresholdMs = (int) config('erp.performance.slow_query_threshold_ms', 500);

        if ($slowQueryThresholdMs > 0) {
            DB::listen(function (\Illuminate\Database\Events\QueryExecuted $query) use ($slowQueryThresholdMs) {
                if ($query->time >= $slowQueryThresholdMs) {
                    Log::channel('slow_queries')->warning('Slow query detected', [
                        'sql'          => $query->sql,
                        'bindings'     => $query->bindings,
                        'time_ms'      => $query->time,
                        'connection'   => $query->connectionName,
                        'threshold_ms' => $slowQueryThresholdMs,
                    ]);
                }
            });
        }
    }
}
