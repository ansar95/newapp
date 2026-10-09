<?php

namespace App\Providers;

use App\Enums\DocumentReferenceType;
use App\Models\BoqItem;
use App\Models\BoqRevision;
use App\Models\ClientBilling;
use App\Models\Project;
use App\Models\User;
use App\Policies\ProgressPolicy;
use App\Policies\RevisionPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::policy(BoqRevision::class, RevisionPolicy::class);

        Relation::morphMap([
            DocumentReferenceType::Billing->value => ClientBilling::class,
        ]);

        Gate::define('viewProgress', function (User $user, Project $project): bool {
            return app(ProgressPolicy::class)->view($user, $project);
        });

        Gate::define('updateProgress', function (User $user, BoqItem $item): bool {
            return app(ProgressPolicy::class)->update($user, $item);
        });
    }
}
