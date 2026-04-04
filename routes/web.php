<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DriverController as PublicDriverController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\InvoiceOperationsController;
use App\Http\Controllers\Admin\NewsController;
use App\Http\Controllers\Admin\PricingOperationsController;
use App\Http\Controllers\Admin\QuotationOperationsController;
use App\Http\Controllers\Admin\RequestOperationsController;
use App\Http\Controllers\Admin\RouteOperationsController;
use App\Http\Controllers\Admin\ScheduleManagementController;
use App\Http\Controllers\Admin\SmsLogController;
use App\Http\Controllers\Admin\VerificationOperationsController;
use App\Http\Controllers\Auth\RegisteredDriverController;
use App\Http\Controllers\DriverDetailController;
use App\Http\Controllers\Driver\DriverRoutesController;
use App\Http\Controllers\Driver\DriverProfileController;
use App\Http\Controllers\Driver\MessageController;
use App\Http\Controllers\Driver\ScheduleController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParcelRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\SavedDriverController;
use App\Http\Controllers\TwilioWebhookController;
use App\Models\CityRoute;
use App\Models\Driver;
use App\Models\Location;
use App\Models\PackageType;
use App\Models\ParcelRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

Route::get('/', function () {
    if (Auth::check() && auth()->user()?->hasRole('driver')) {
        return redirect()->route('driver.home');
    }

    $locations = Schema::hasTable('locations')
        ? Location::orderBy('name')->get(['id', 'name'])
        : collect();

    $packageTypes = Schema::hasTable('package_types')
        ? PackageType::orderBy('name')->get(['id', 'name'])
        : collect();

    $cityRoutes = Schema::hasTable('city_routes')
        ? CityRoute::query()
            ->with(['originLocation:id,name', 'destinationLocation:id,name'])
            ->where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('distance_km')
            ->get()
            ->map(fn (CityRoute $route) => [
                'id' => $route->id,
                'origin_location_id' => $route->origin_location_id,
                'destination_location_id' => $route->destination_location_id,
                'origin_name' => $route->originLocation?->name,
                'destination_name' => $route->destinationLocation?->name,
                'distance_km' => $route->distance_km,
                'estimated_hours' => $route->estimated_hours,
                'base_fare' => $route->base_fare,
                'is_featured' => $route->is_featured,
            ])
        : collect();

    $liveRequests = Schema::hasTable('parcel_requests')
        ? ParcelRequest::query()
            ->with(['pickupLocation:id,name', 'dropoffLocation:id,name', 'packageType:id,name'])
            ->openForMatching()
            ->latest()
            ->take(4)
            ->get()
            ->map(function (ParcelRequest $parcel) {
                $urgency = $parcel->urgency_level === 'same_day'
                    ? 'Same day'
                    : ($parcel->urgency_level === 'express' ? 'Express' : 'Standard');

                return [
                    'id' => $parcel->id,
                    'route' => trim(($parcel->pickupLocation?->name ?? 'Pickup') . ' -> ' . ($parcel->dropoffLocation?->name ?? 'Destination')),
                    'title' => $parcel->receiver_name ? 'Request ready for matching' : 'New parcel request',
                    'parcel' => $parcel->packageType?->name ?? 'Parcel',
                    'time' => $parcel->created_at?->diffForHumans(),
                    'badge' => $urgency,
                    'badgeStyle' => match ($parcel->urgency_level) {
                        'same_day' => 'background:#1F1F1F;color:#FFFFFF;',
                        'express' => 'background:#F2C900;color:#1F1F1F;',
                        default => 'background:var(--app-surface-soft);color:var(--app-text);border:1px solid var(--app-border);',
                    },
                ];
            })
            ->values()
        : collect();

    $featuredDrivers = Schema::hasTable('drivers')
        ? Driver::query()
            ->with(['user:id,name,profile_photo_path', 'driverRoutes.locations:id,name', 'driverRoutes.packages:id,name'])
            ->where('status', 'active')
            ->whereHas('driverRoutes', fn ($query) => $query->where('available', true))
            ->take(6)
            ->get()
            ->values()
            ->map(function (Driver $driver) {
                $route = $driver->driverRoutes->firstWhere('available', true) ?? $driver->driverRoutes->first();
                $locations = $route?->locations?->pluck('name')->values() ?? collect();
                $packages = $route?->packages?->pluck('name')->take(3)->values() ?? collect();
                $profilePhoto = $driver->user?->profile_photo_url;

                return [
                    'id' => $driver->id,
                    'name' => $driver->user?->name ?? 'Route Driver',
                    'vehicle' => trim(collect([$route?->car_make, $route?->car_model])->filter()->join(' ')) ?: ($route?->vehicle_type ?? 'Delivery Vehicle'),
                    'route' => $locations->take(2)->join(' -> ') ?: 'Namibia intercity lane',
                    'secondaryRoute' => $locations->slice(2)->join(', ') ?: 'Additional route coverage available',
                    'badges' => $packages->take(2)->all() ?: ['Available now'],
                    'parcelTypes' => $packages->join(', ') ?: 'General parcels and intercity loads',
                    'rating' => number_format(4.7 + (($driver->id % 3) * 0.1), 1),
                    'available' => (bool) ($route?->available),
                    'href' => route('driver.detail', $driver->id),
                    'image' => $driver->user?->profile_photo_path ? $profilePhoto : null,
                ];
            })
        : collect();

    $trustStats = [
        [
            'label' => 'Active Drivers',
            'value' => Schema::hasTable('drivers') ? (string) Driver::query()->where('status', 'active')->count() : '0',
            'meta' => 'Drivers live on current lanes',
            'icon' => 'DR',
        ],
        [
            'label' => 'Cities Covered',
            'value' => Schema::hasTable('locations') ? (string) Location::query()->count() : '0',
            'meta' => 'Pickup and dropoff points',
            'icon' => 'CT',
        ],
        [
            'label' => 'Requests Today',
            'value' => Schema::hasTable('parcel_requests') ? (string) ParcelRequest::query()->whereDate('created_at', today())->count() : '0',
            'meta' => 'Fresh demand moving now',
            'icon' => 'RQ',
        ],
        [
            'label' => 'Delivered',
            'value' => Schema::hasTable('parcel_requests') ? (string) ParcelRequest::query()->where('status', 'delivered')->count() : '0',
            'meta' => 'Completed handovers logged',
            'icon' => 'OK',
        ],
    ];

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => app()->version(),
        'phpVersion' => PHP_VERSION,
        'locations' => $locations,
        'packageTypes' => $packageTypes,
        'cityRoutes' => $cityRoutes,
        'trustStats' => $trustStats,
        'liveRequests' => $liveRequests,
        'featuredDrivers' => $featuredDrivers,
        'driverReadyCount' => Schema::hasTable('drivers')
            ? Driver::query()
                ->where('status', 'active')
                ->whereHas('driverRoutes', fn ($query) => $query->where('available', true))
                ->count()
            : 0,
    ]);
})->name('welcome');

Route::get('/find-driver', [PublicDriverController::class, 'findDriver'])->name('find.Driver');
Route::get('/driver-selection/confirm', [ParcelRequestController::class, 'confirmSelection'])->name('driver-selection.confirm');
Route::get('/parcel-preview', [ParcelRequestController::class, 'preview'])->name('parcel-requests.preview');
Route::get('/driver/register', [RegisteredDriverController::class, 'create'])->name('driver.register');
Route::post('/driver/register', [RegisteredDriverController::class, 'store'])->name('driver.register.submit');
Route::get('/driver/{driver}', [DriverDetailController::class, 'show'])->whereNumber('driver')->name('driver.detail');
Route::post('/webhooks/twilio/sms-status', [TwilioWebhookController::class, 'status'])->name('webhooks.twilio.sms-status');

require __DIR__ . '/auth.php';

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/dashboard', function () {
        $user = auth()->user();

        if ($user->hasRole('admin')) {
            return app(DashboardController::class)->index();
        }

        if ($user->hasRole('driver')) {
            return app(DriverRoutesController::class)->dashboard();
        }

        return redirect()->route('user.parcels.index');
    })->name('dashboard');

    Route::get('/parcel-request', [ParcelRequestController::class, 'create'])->name('parcel-requests.create');
    Route::post('/parcel-request', [ParcelRequestController::class, 'store'])->name('parcel-requests.store');
    Route::get('/parcel-request/{parcelRequest}/payment-ready', [ParcelRequestController::class, 'paymentReady'])
        ->whereNumber('parcelRequest')
        ->name('parcel-requests.payment-ready');

    Route::post('/drivers/{id}/save', [SavedDriverController::class, 'save'])->name('drivers.save');
    Route::post('/drivers/{id}/unsave', [SavedDriverController::class, 'unsave'])->name('drivers.unsave');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/quotations/preview', [QuotationController::class, 'storePreview'])->name('quotations.preview');
    Route::post('/quotations/{quotation}/accept', [QuotationController::class, 'accept'])->name('quotations.accept');
    Route::get('/quotations/{quotation}/download', [QuotationController::class, 'download'])->name('quotations.download');
    Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');

    Route::middleware(['verified', 'role:user'])->prefix('user')->name('user.')->group(function () {
        Route::get('/parcels', [ParcelRequestController::class, 'index'])->name('parcels.index');
        Route::get('/profile', function () {
            return Inertia::render('User/Profile', ['user' => auth()->user()]);
        })->name('profile');
        Route::post('/profile', [ProfileController::class, 'update'])->name('profile.legacy-update');
    });

    Route::middleware(['verified', 'role:Driver'])->prefix('driver')->name('driver.')->group(function () {
        Route::get('/', [DriverRoutesController::class, 'home'])->name('home');
        Route::get('/dashboard', [DriverRoutesController::class, 'dashboard'])->name('dashboard');
        Route::get('/routes', [DriverRoutesController::class, 'routes'])->name('routes');
        Route::put('/routes/{driverRoute}', [DriverRoutesController::class, 'update'])->name('routes.update');
        Route::post('/availability', [DriverRoutesController::class, 'updateAvailability'])->name('availability.update');
        Route::get('/profile', [DriverProfileController::class, 'show'])->name('profile');
        Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/verification', [DriverProfileController::class, 'submitVerification'])->name('profile.verification.submit');
        Route::post('/profile/banking', [DriverProfileController::class, 'saveBanking'])->name('profile.banking.save');
        Route::get('/messages', [MessageController::class, 'page'])->name('messages');
        Route::get('/messages/list', [MessageController::class, 'index'])->name('messages.list');
        Route::get('/schedules', [ScheduleController::class, 'page'])->name('schedules.index');
        Route::get('/schedules/list', [ScheduleController::class, 'index'])->name('schedules.list');
        Route::post('/schedules', [ScheduleController::class, 'store'])->name('schedules.store');
        Route::put('/schedules/{id}', [ScheduleController::class, 'update'])->name('schedules.update');
        Route::delete('/schedules/{id}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');
        Route::post('/parcels/{parcelRequest}/accept', [ParcelRequestController::class, 'accept'])->name('parcels.accept');
        Route::post('/parcels/{parcelRequest}/status', [ParcelRequestController::class, 'updateStatus'])->name('parcels.status');
    });

    Route::middleware(['verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/verification', [VerificationOperationsController::class, 'page'])->name('verification.index');
        Route::get('/verification/data', [VerificationOperationsController::class, 'data'])->name('verification.data');
        Route::post('/verification/licences/{driverLicence}/review', [VerificationOperationsController::class, 'review'])->name('verification.review');

        Route::get('/routes', [RouteOperationsController::class, 'page'])->name('routes.index');
        Route::get('/routes/data', [RouteOperationsController::class, 'data'])->name('routes.data');
        Route::post('/routes', [RouteOperationsController::class, 'store'])->name('routes.store');
        Route::put('/routes/{cityRoute}', [RouteOperationsController::class, 'update'])->name('routes.update');
        Route::post('/routes/{cityRoute}/reverse', [RouteOperationsController::class, 'createReverse'])->name('routes.reverse');

        Route::get('/pricing', [PricingOperationsController::class, 'page'])->name('pricing.index');
        Route::get('/pricing/data', [PricingOperationsController::class, 'data'])->name('pricing.data');
        Route::post('/pricing/rules', [PricingOperationsController::class, 'saveRule'])->name('pricing.rules.store');
        Route::put('/pricing/rules/{pricingRule}', [PricingOperationsController::class, 'saveRule'])->name('pricing.rules.update');
        Route::delete('/pricing/rules/{pricingRule}', [PricingOperationsController::class, 'deleteRule'])->name('pricing.rules.destroy');
        Route::post('/pricing/simulate', [PricingOperationsController::class, 'simulate'])->name('pricing.simulate');

        Route::get('/quotations', [QuotationOperationsController::class, 'page'])->name('quotations.index');
        Route::get('/quotations/data', [QuotationOperationsController::class, 'data'])->name('quotations.data');

        Route::get('/invoices', [InvoiceOperationsController::class, 'page'])->name('invoices.index');
        Route::get('/invoices/data', [InvoiceOperationsController::class, 'data'])->name('invoices.data');
        Route::put('/invoices/{invoice}', [InvoiceOperationsController::class, 'update'])->name('invoices.update');

        Route::get('/sms-logs', [SmsLogController::class, 'page'])->name('sms-logs.index');
        Route::get('/sms-logs/data', [SmsLogController::class, 'data'])->name('sms-logs.data');

        Route::get('/requests', [RequestOperationsController::class, 'page'])->name('requests.index');
        Route::get('/requests/data', [RequestOperationsController::class, 'data'])->name('requests.data');

        Route::get('/drivers', [PublicDriverController::class, 'page'])->name('drivers.index');
        Route::get('/drivers/list', [PublicDriverController::class, 'index'])->name('drivers.list');
        Route::get('/drivers/routes/matrix', [PublicDriverController::class, 'routeMatrix'])->name('drivers.routes.matrix');
        Route::get('/drivers/verification-queue', [DriverProfileController::class, 'adminQueue'])->name('drivers.verification-queue');
        Route::post('/drivers/licences/{driverLicence}/review', [DriverProfileController::class, 'reviewVerification'])->name('drivers.verification-review');
        Route::post('/drivers', [PublicDriverController::class, 'store'])->name('drivers.store');
        Route::put('/drivers/{driver}', [PublicDriverController::class, 'update'])->name('drivers.update');
        Route::delete('/drivers/{driver}', [PublicDriverController::class, 'destroy'])->name('drivers.destroy');

        Route::get('/users', [AdminUserController::class, 'page'])->name('users.index');
        Route::get('/users/list', [AdminUserController::class, 'index'])->name('users.list');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        Route::get('/schedules', [ScheduleManagementController::class, 'page'])->name('schedules.index');
        Route::get('/schedules/list', [ScheduleManagementController::class, 'index'])->name('schedules.list');
        Route::post('/schedules/mention', [ScheduleManagementController::class, 'mention'])->name('schedules.mention');

        Route::get('/news', [NewsController::class, 'index'])->name('news.index');
        Route::get('/news/create', [NewsController::class, 'create'])->name('news.create');
        Route::post('/news', [NewsController::class, 'store'])->name('news.store');
        Route::get('/news/{id}/edit', [NewsController::class, 'edit'])->name('news.edit');
        Route::put('/news/{id}', [NewsController::class, 'update'])->name('news.update');
        Route::delete('/news/{id}', [NewsController::class, 'destroy'])->name('news.destroy');
        Route::post('/news/upload-image', [NewsController::class, 'uploadImage'])->name('news.upload-image');
    });
});
