<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CityRoute;
use App\Models\Driver;
use App\Models\Location;
use App\Models\PackageType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Illuminate\Support\Facades\Route;

class DriverController extends Controller
{
    public function page()
    {
        return Inertia::render('Admin/Drivers/Index');
    }

    public function index(Request $request)
    {
        $rows = Driver::with(['user:id,name,email,profile_photo_path,location,phone', 'primaryLicence', 'bankAccount'])
            ->orderByDesc('id')
            ->get()
            ->map(fn($d) => [
                'id'          => $d->id,
                'user_id'     => $d->user_id,
                'name'        => $d->user?->name,
                'email'       => $d->user?->email,
                'photo'       => $d->user?->profile_photo_url,
                'designation' => $d->designation,
                'speciality'  => $d->speciality,
                'phone'       => $d->phone,
                'about'       => $d->about,
                'verification_status' => $d->verification_status,
                'verification_rejection_reason' => $d->verification_rejection_reason,
                'licence_type' => $d->primaryLicence?->licence_type_name,
                'licence_expiry_date' => optional($d->primaryLicence?->expiry_date)->toDateString(),
                'licence_document_url' => $d->primaryLicence?->document_url,
                'banking_status' => $d->bankAccount?->status ?? 'incomplete',
                'masked_account_number' => $d->bankAccount?->masked_account_number,
            ]);

        return response()->json($rows, 200);
    }

    public function routeMatrix()
    {
        return response()->json(
            CityRoute::query()
                ->with(['originLocation:id,name', 'destinationLocation:id,name'])
                ->orderBy('origin_location_id')
                ->orderBy('destination_location_id')
                ->get()
                ->map(fn (CityRoute $route) => [
                    'id' => $route->id,
                    'route_code' => $route->route_code,
                    'origin_name' => $route->originLocation?->name,
                    'destination_name' => $route->destinationLocation?->name,
                    'distance_km' => $route->distance_km,
                    'estimated_hours' => $route->estimated_hours,
                    'base_fare' => $route->base_fare,
                    'per_km_rate' => $route->per_km_rate,
                    'minimum_price' => $route->minimum_price,
                    'distance_source' => $route->distance_source,
                ])
        );
    }

    // Create + send email
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => ['required','string','max:255'],
            'email'        => ['required','email','max:255', Rule::unique('users','email')],
            'password'     => ['required', Password::min(8)],
            'designation'  => ['nullable','string','max:255'],
            'speciality'   => ['nullable','string','max:255'],
            'phone'        => ['nullable','string','max:50'],
            'about'        => ['nullable','string','max:2000'],
            'photo'        => ['nullable','image','max:2048'],
        ]);

        $row = DB::transaction(function () use ($data) {
            $photoPath = null;
            if (isset($data['photo'])) {
                $photoPath = $data['photo']->store('drivers', 'public');
            }

            $user = User::create([
                'name'              => $data['name'],
                'email'             => $data['email'],
                'password'          => Hash::make($data['password']),
                'role'              => 'Driver',
                'profile_photo_path' => $photoPath,
                'phone'             => $data['phone'] ?? null,
                'email_verified_at' => now(),
            ]);

            $Driver = Driver::create([
                'user_id'     => $user->id,
                'designation' => $data['designation'] ?? null,
                'speciality'  => $data['speciality'] ?? null,
                'phone'       => $data['phone'] ?? null,
                'about'       => $data['about'] ?? null,
            ]);

            return [$user, $Driver];
        });

        [$user, $Driver] = $row;

        return response()->json([
            'message' => 'Driver created successfully',
            'Driver'  => [
                'id'          => $Driver->id,
                'user_id'     => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'photo'       => $user->profile_photo_url,
                'designation' => $Driver->designation,
                'speciality'  => $Driver->speciality,
                'phone'       => $Driver->phone,
                'about'       => $Driver->about,
            ]
        ], 201);
    }


/*    public function update(Request $request, Driver $Driver)
    {
        $user = $Driver->user;

        $data = $request->validate([
            'name'         => ['required','string','max:255'],
            'email'        => ['required','email','max:255', Rule::unique('users','email')->ignore($user?->id)],
            'password'     => ['nullable', Password::min(8)],
            'designation'  => ['nullable','string','max:255'],
            'speciality'   => ['nullable','string','max:255'],
            'phone'        => ['nullable','string','max:50'],
            'about'        => ['nullable','string','max:2000'],
            'photo'        => ['nullable','image','max:2048'],
        ]);

        DB::transaction(function () use ($data, $user, $Driver) {
            $user->name  = $data['name'];
            $user->email = $data['email'];
            if (!empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }
            if (isset($data['photo'])) {
                $photoPath = $data['photo']->store('drivers', 'public');
                $user->photo = $photoPath;
            }
            $user->save();

            $Driver->update([
                'designation' => $data['designation'] ?? null,
                'speciality'  => $data['speciality'] ?? null,
                'phone'       => $data['phone'] ?? null,
                'about'       => $data['about'] ?? null,
            ]);
        });

        $Driver->load('user:id,name,email,photo');

        return response()->json([
            'message' => 'Driver updated',
            'Driver'  => [
                'id'          => $Driver->id,
                'user_id'     => $Driver->user_id,
                'name'        => $Driver->user?->name,
                'email'       => $Driver->user?->email,
                'photo'       => $Driver->user?->photo ? asset('storage/' . $Driver->user->photo) : null,
                'designation' => $Driver->designation,
                'speciality'  => $Driver->speciality,
                'phone'       => $Driver->phone,
                'about'       => $Driver->about,
            ],
        ], 200);
    }
*/


    public function update(Request $request, Driver $driver)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($driver->user_id)],
            'password' => ['nullable', Password::min(8)],
            'designation' => ['nullable', 'string', 'max:255'],
            'speciality' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'about' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $user = $driver->user;

        if ($request->hasFile('photo')) {
            $user->profile_photo_path = $request->file('photo')->store('drivers', 'public');
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $driver->update([
            'designation' => $validated['designation'] ?? null,
            'speciality' => $validated['speciality'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'about' => $validated['about'] ?? null,
        ]);

        return response()->json([
            'Driver' => [
                'id' => $driver->id,
                'user_id' => $driver->user_id,
                'name' => $user->name,
                'email' => $user->email,
                'photo' => $user->profile_photo_url,
                'designation' => $driver->designation,
                'speciality' => $driver->speciality,
                'phone' => $driver->phone,
                'about' => $driver->about,
            ],
        ]);
    }

    public function show(Driver $driver)
    {
        $driver->load([
            'user:id,name,email,phone,profile_photo_path,location',
            'driverRoutes.locations',
            'driverRoutes.packages',
        ]);

        // Profile views count (safe: 0 if relationship doesn't exist)
        $profileViews = $driver->profileViews()->count() ?? 0;

        // Saved contacts count
        $savedBy = $driver->savedBy()->with('user')->get()->map(fn($s) => [
            'id' => $s->user->id,
            'name' => $s->user->name,
            'email' => $s->user->email,
        ]);

        // Always return a contactStats object
        $contactStats = [
            'views' => $profileViews,
            'saves' => $savedBy->count(),
        ];

        return Inertia::render('DriverDetail', [
            'driver' => [
                'id' => $driver->id,
                'phone' => $driver->phone,
                'location' => $driver->location,
                'designation' => $driver->designation,
                'about' => $driver->about,
                'user' => $driver->user,
                'driver_routes' => $driver->driverRoutes->map(fn($route) => [
                    'id' => $route->id,
                    'car_make' => $route->car_make,
                    'car_model' => $route->car_model,
                    'car_number' => $route->car_number,
                    'available' => $route->available,
                    'packages' => $route->packages->map(fn($pkg) => [
                        'id' => $pkg->id,
                        'name' => $pkg->name,
                    ]),
                    'locations' => $route->locations->map(fn($loc) => [
                        'id' => $loc->id,
                        'name' => $loc->name,
                    ]),
                ]),
            ],
            'contactStats' => $contactStats, // always present
            'savedContacts' => $savedBy,
            'authUser' => auth()->user()?->load('savedDrivers'),
        ]);
    }


    public function findDriver(): \Inertia\Response
    {
        $drivers = Driver::with([
            'user',
            'driverRoutes.locations',
            'driverRoutes.packages',
        ])->get()
            ->map(function ($driver) {
                return [
                    'id' => $driver->id,
                    'phone' => $driver->phone,
                    'status' => $driver->status,
                    'user' => $driver->user,
                    'driverRoutes' => $driver->driverRoutes->map(function ($route) {
                        return [
                            'id' => $route->id,
                            'vehicle_type' => $route->vehicle_type,
                            'max_load_size' => $route->max_load_size,
                            'is_refrigerated' => (bool) $route->is_refrigerated,
                            'available' => (bool) $route->available,
                            'updated_at' => optional($route->updated_at)?->toIso8601String(),
                            'car_make' => $route->car_make,
                            'car_model' => $route->car_model,
                            'car_number' => $route->car_number,
                            'locations' => $route->locations->map(fn($loc) => [
                                'id' => $loc->id,
                                'name' => $loc->name,
                            ]),
                            'packages' => $route->packages->map(fn($pkg) => [
                                'id' => $pkg->id,
                                'name' => $pkg->name,
                            ]),
                        ];
                    }),
                ];
            });

        $locations = Location::select('id', 'name')->get();
        $packageTypes = PackageType::select('id', 'name')->get();
        $cityRoutes = CityRoute::query()
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
            ]);

        return Inertia::render('FindDriver', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'drivers' => $drivers,
            'locations' => $locations,
            'packageTypes' => $packageTypes,
            'cityRoutes' => $cityRoutes,
        ]);
    }






    public function destroy(Driver $Driver)
    {
        DB::transaction(function () use ($Driver) {
            $user = $Driver->user;
            $Driver->delete();
            if ($user) $user->delete();
        });

        return response()->noContent();
    }


    public function saveContact(Request $request, $driverId)
    {
        $user = $request->user(); // currently authenticated user

        // Check if driver exists
        $driver = User::find($driverId);
        if (!$driver) {
            return redirect()->back()->with('error', 'Driver does not exist.');
        }

        // Prevent saving self
        if ($user->id === $driver->id) {
            return redirect()->back()->with('error', 'You cannot save yourself.');
        }




        // Check if already saved
        $alreadySaved = DB::table('saved_drivers')
            ->where('user_id', $user->id)
            ->where('driver_id', $driver->id)
            ->exists();

        if ($alreadySaved) {
            return redirect()->back()->with('info', 'Driver already saved.');
        }

        // Save the driver
        DB::table('saved_drivers')->insert([
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user->savedDrivers()->syncWithoutDetaching([$driver->id]);

        return redirect()->back()->with('success', 'Driver saved successfully.');
    }




}
