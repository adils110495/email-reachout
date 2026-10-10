<?php

namespace App\Providers;

use App\Sequencer\Events\BounceDetected;
use App\Sequencer\Events\ContactUnsubscribed;
use App\Sequencer\Events\ReplyDetected;
use App\Sequencer\Listeners\ActivitySubscriber;
use App\Sequencer\Listeners\HandleHardBounce;
use App\Sequencer\Listeners\StopEnrollmentOnReply;
use App\Sequencer\Listeners\StopEnrollmentsOnUnsubscribe;
use App\Sequencer\Mail\EmailProviderManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class SequencerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One registry of delivery providers for the whole process.
        $this->app->singleton(EmailProviderManager::class);
    }

    public function boot(): void
    {
        Event::subscribe(ActivitySubscriber::class);
        Event::listen(ReplyDetected::class, StopEnrollmentOnReply::class);
        Event::listen(BounceDetected::class, HandleHardBounce::class);
        Event::listen(ContactUnsubscribed::class, StopEnrollmentsOnUnsubscribe::class);

        // @localtime($carbon) / @localtime($carbon, 'd M') : render in the signed-in user's timezone.
        Blade::directive('localtime', fn (string $expression) => "<?php echo e(\\App\\Sequencer\\Support\\Tz::format({$expression})); ?>");

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // REST API: per user (or IP when anonymous).
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        // API login: slow down credential stuffing.
        RateLimiter::for('api-login', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perMinute(5)->by(strtolower((string) $request->input('login')).'|'.$request->ip()),
        ]);

        // Public tracking endpoints: generous (mail clients load many), but bounded per IP.
        RateLimiter::for('tracking', fn (Request $request) => Limit::perMinute(600)->by($request->ip()));

        // Unsubscribe confirmation.
        RateLimiter::for('unsubscribe', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // Password reset requests.
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
    }
}
