<?php

namespace App\Providers;

use App\Policies\CustomUserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Statamic\Facades\Entry;
use Statamic\Facades\Form;
use Statamic\Policies\UserPolicy;
use Studio1902\PeakSeo\Handlers\ErrorPage;
use Statamic\Statamic;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserPolicy::class, CustomUserPolicy::class);

        if (class_exists(\App\Console\Commands\ClearSite::class)) {
            $this->commands([\App\Console\Commands\ClearSite::class]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ErrorPage::handle404AsEntry();

        $this->bootRoute();

        $this->bootFormRedirects();
    }

    /**
     * A Thank-you page in every form's settings, just after Peak Tools' Success message. It has its own
     * section because sections are keyed by name, and Peak Tools' "Fields" section would replace ours.
     * Statamic has no redirect setting of its own, only Form::redirect() callbacks, which it checks
     * first when a submission succeeds. Peak Tools' form handler then follows the redirect in the response.
     */
    public function bootFormRedirects(): void
    {
        Form::appendConfigFields('*', __('After submission'), [
            'redirect' => [
                'type' => 'entries',
                'collections' => ['pages'],
                'max_items' => 1,
                'display' => __('Thank-you page'),
                'instructions' => __('Optional. After a successful submission, send people to this page instead of showing the success message.'),
            ],
        ], afterSection: 'fields');

        Form::all()->each(fn ($form) => Form::redirect(
            $form->handle(),
            fn ($submission) => Entry::find(Arr::first(Arr::wrap($submission->form()->get('redirect'))))?->url()
        ));
    }

    public function bootRoute(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
