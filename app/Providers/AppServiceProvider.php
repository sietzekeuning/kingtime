<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Every write goes through a validated DTO (see BaseData), so mass
        // assignment protection only gets in the way.
        Model::unguard();

        // Models live in app/Domain/*/Models, factories stay flat in database/factories.
        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            $factory = 'Database\\Factories\\'.class_basename($modelName).'Factory';

            if (! class_exists($factory) || ! is_subclass_of($factory, Factory::class)) {
                throw new RuntimeException("No factory found for [{$modelName}], expected [{$factory}].");
            }

            return $factory;
        });
        Model::shouldBeStrict(! app()->isProduction());

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
}
