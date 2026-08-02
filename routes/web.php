<?php

use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\PortfolioController as AdminPortfolioController;
use App\Http\Controllers\Admin\PortfolioImageController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\VideoController;
use App\Http\Controllers\Admin\CommissionPayoutController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ProjectChargeController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ProjectFileController;
use App\Http\Controllers\Admin\ProjectStageController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\StageController;
use App\Http\Controllers\Admin\StageSetController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\Public\BlogController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\PortfolioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website
|--------------------------------------------------------------------------
|
| Unauthenticated marketing site. The `cacheable` middleware sets CDN cache
| headers so Cloudflare can serve these pages while the free-tier origin is
| asleep — a 30-60s cold start on a public page loses visitors and hurts
| search ranking, which does not matter for the internal app but very much
| does here.
|
*/
Route::middleware('cacheable')->withoutMiddleware([
    /*
     * No session on cacheable pages — this is the whole ballgame.
     *
     * StartSession stamps `Cache-Control: no-cache, private` and a Set-Cookie
     * on every response, and Cloudflare (or any shared cache) will refuse to
     * store a response carrying either. Leaving these on would mean the CDN
     * silently caches nothing, every visitor hits a sleeping origin, and the
     * problem only shows up in production as slowness nobody can explain.
     *
     * These pages are identical for every visitor and need no session.
     * SubstituteBindings is deliberately kept — route-model binding still has
     * to resolve slugs.
     */
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    // Laravel 13 renamed VerifyCsrfToken to PreventRequestForgery. Naming the
    // old class silently excludes nothing — the middleware keeps running and
    // keeps demanding a session.
    \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
])->group(function () {
    Route::get('/', [PageController::class, 'home'])->name('public.home');
    Route::get('/about', [PageController::class, 'about'])->name('public.about');
    Route::get('/videos', [PageController::class, 'videos'])->name('public.videos');

    Route::get('/work', [PortfolioController::class, 'index'])->name('public.portfolio.index');
    Route::get('/work/{portfolioItem}', [PortfolioController::class, 'show'])->name('public.portfolio.show');

    Route::get('/blog', [BlogController::class, 'index'])->name('public.blog.index');
    Route::get('/blog/tag/{tag}', [BlogController::class, 'tag'])->name('public.blog.tag');
    Route::get('/blog/{post}', [BlogController::class, 'show'])->name('public.blog.show');

    Route::get('/sitemap.xml', [PageController::class, 'sitemap'])->name('public.sitemap');
});

// Offline fallback for the service worker. No session, nothing dynamic — it has
// to render when the network is gone.
Route::view('/offline', 'offline')->name('offline');

// Not cacheable: the form carries a CSRF token, and a cached token is a broken
// form for every visitor after the first.
Route::get('/contact', [ContactController::class, 'show'])->name('public.contact');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('public.contact.store');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| Plain controllers and form POSTs rather than Livewire: a sign-in page should
| not depend on JavaScript to function.
|
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});

/*
 * Accepting an invitation.
 *
 * `signed` verifies the signature and expiry; the controller then checks the
 * invitation has not already been used. Deliberately outside the `guest` group —
 * someone already signed in as a different account still needs to follow their
 * own invite link.
 */
Route::middleware('signed')->group(function () {
    Route::get('/invitation/{user}', [InvitationController::class, 'show'])->name('invitation.accept');
    Route::post('/invitation/{user}', [InvitationController::class, 'store'])->name('invitation.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated areas
|--------------------------------------------------------------------------
|
| Three separate applications behind one login. `account` confines each type to
| its own area; policies and model scopes then handle record-level isolation.
|
*/
Route::middleware('auth')->group(function () {
    Route::patch('/preferences/theme', [PreferenceController::class, 'theme'])->name('preferences.theme');

    /*
     * One download endpoint for everyone, authorised per request by
     * ProjectFilePolicy. Staff get everything; clients and partners get only
     * the shared files on projects that are theirs, never internal ones.
     * Deliberately not a signed URL — those stay valid for anyone they are
     * forwarded to.
     */
    Route::get('/files/{file}', [ProjectFileController::class, 'download'])->name('files.download');

    /*
     * Invoice PDF, outside the staff-only group on purpose: a client (or a
     * partner who is being billed directly) needs to read their own invoice.
     * InvoicePolicy decides, and only ever for a SENT invoice — a draft is a
     * working document whose figures may still be wrong.
     */
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('admin.invoices.pdf');

    Route::middleware('account:staff')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'staff'])->name('dashboard');

        Route::name('admin.')->prefix('admin')->group(function () {
            // Account management. No model of its own, so it is gated by the
            // named `manageUsers` ability rather than a policy.
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::post('users/{user}/resend', [UserController::class, 'resend'])->name('users.resend');
            Route::delete('users/{user}/revoke', [UserController::class, 'revoke'])->name('users.revoke');
            Route::put('users/{user}/active', [UserController::class, 'toggleActive'])->name('users.active');
            Route::put('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');

            Route::resource('clients', ClientController::class);
            Route::resource('partners', PartnerController::class);

            // Portal invitations live on the client's or partner's own page, so
            // the account is always attached to the right record.
            Route::post('clients/{client}/invite', [ClientController::class, 'invite'])->name('clients.invite');
            Route::post('partners/{partner}/invite', [PartnerController::class, 'invite'])->name('partners.invite');
            Route::resource('projects', ProjectController::class);

            // Extra work and stage movement hang off a project rather than
            // standing alone — they are meaningless without one.
            Route::prefix('projects/{project}')->name('projects.')->group(function () {
                Route::post('charges', [ProjectChargeController::class, 'store'])->name('charges.store');
                Route::post('charges/{charge}/approve', [ProjectChargeController::class, 'approve'])->name('charges.approve');
                Route::put('charges/{charge}', [ProjectChargeController::class, 'update'])->name('charges.update');
                Route::delete('charges/{charge}', [ProjectChargeController::class, 'destroy'])->name('charges.destroy');

                Route::post('stage', [ProjectStageController::class, 'move'])->name('stage.move');
                Route::post('stage/advance', [ProjectStageController::class, 'advance'])->name('stage.advance');

                // Money in, and money out to the partner.
                Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
                Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
                Route::post('payouts', [CommissionPayoutController::class, 'store'])->name('payouts.store');
                Route::delete('payouts/{payout}', [CommissionPayoutController::class, 'destroy'])->name('payouts.destroy');

                Route::post('files', [ProjectFileController::class, 'store'])->name('files.store');
                Route::patch('files/{file}/visibility', [ProjectFileController::class, 'toggleVisibility'])->name('files.visibility');
                Route::delete('files/{file}', [ProjectFileController::class, 'destroy'])->name('files.destroy');
            });

            // Invoices. Drafting hangs off a project; everything else is
            // invoice-scoped.
            Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
            Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
            Route::put('invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.status');
            Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
            Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
            Route::post('projects/{project}/invoices', [InvoiceController::class, 'store'])->name('projects.invoices.store');

            // "Who do I owe" across every partner.
            Route::get('payouts', [CommissionPayoutController::class, 'index'])->name('payouts.index');

            /*
             * Public-site content.
             *
             * The Markdown preview endpoint is declared BEFORE the resource
             * route: posts/{post} would otherwise capture "posts/preview" and
             * try to resolve "preview" as a slug.
             */
            Route::post('posts/preview', [PostController::class, 'preview'])->name('posts.preview');
            Route::resource('posts', PostController::class)->except(['show']);

            Route::resource('portfolio', AdminPortfolioController::class)
                ->parameters(['portfolio' => 'portfolioItem'])
                ->except(['show']);

            Route::prefix('portfolio/{portfolioItem}')->name('portfolio.')->group(function () {
                Route::post('images', [PortfolioImageController::class, 'store'])->name('images.store');
                Route::put('images/{image}', [PortfolioImageController::class, 'update'])->name('images.update');
                Route::post('images/{image}/move', [PortfolioImageController::class, 'move'])->name('images.move');
                Route::delete('images/{image}', [PortfolioImageController::class, 'destroy'])->name('images.destroy');
            });

            Route::get('videos', [VideoController::class, 'index'])->name('videos.index');
            Route::post('videos', [VideoController::class, 'store'])->name('videos.store');
            Route::post('videos/sync', [VideoController::class, 'sync'])->name('videos.sync');
            Route::put('videos/{video}', [VideoController::class, 'update'])->name('videos.update');
            Route::delete('videos/{video}', [VideoController::class, 'destroy'])->name('videos.destroy');

            Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
            Route::put('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
            Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
            Route::delete('leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');

            // Stage sets — the reusable pipelines projects are assigned to.
            Route::resource('stage-sets', StageSetController::class)->except(['show']);
            Route::post('stage-sets/{stageSet}/duplicate', [StageSetController::class, 'duplicate'])
                ->name('stage-sets.duplicate');
            Route::post('stage-sets/{stageSet}/default', [StageSetController::class, 'makeDefault'])
                ->name('stage-sets.default');

            Route::prefix('stage-sets/{stageSet}')->name('stage-sets.')->group(function () {
                Route::post('stages', [StageController::class, 'store'])->name('stages.store');
                Route::put('stages/{stage}', [StageController::class, 'update'])->name('stages.update');
                Route::post('stages/{stage}/move', [StageController::class, 'move'])->name('stages.move');
                Route::delete('stages/{stage}', [StageController::class, 'destroy'])->name('stages.destroy');
            });
        });
    });

    Route::middleware('account:client')->prefix('portal')->group(function () {
        Route::get('/', [DashboardController::class, 'client'])->name('portal.client.dashboard');
    });

    Route::middleware('account:partner')->prefix('partner')->group(function () {
        Route::get('/', [DashboardController::class, 'partner'])->name('portal.partner.dashboard');
    });
});

/*
|--------------------------------------------------------------------------
| Development
|--------------------------------------------------------------------------
*/
if (app()->environment('local')) {
    Route::view('/design', 'design')->name('design');
}
