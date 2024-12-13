<?php

namespace Wave;

use DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use Wave\Plan;
use Carbon\Carbon;
use Wave\Changelog;
use Wave\Subscription;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Notifications\Notifiable;
use Devdojo\Auth\Models\User as AuthUser;
use Lab404\Impersonate\Models\Impersonate;

class User extends AuthUser implements JWTSubject, HasAvatar, FilamentUser
{
    use Notifiable, Impersonate, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'surname',
        'email',
        'username',
        'avatar',
        'password',
        'role_id',
        'verification_code',
        'verified',
        'trial_ends_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

    public function onTrial()
    {
        if (is_null($this->trial_ends_at)) {
            return false;
        }
        if ($this->subscriber()) {
            return false;
        }
        return true;
    }

    

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'billable_id')->where('billable_type', 'user');
    }

    public function subscriber()
    {
        return $this->subscriptions()->where('status', 'active')->exists();
    }

    public function subscribedToPlan($planSlug)
    {
        $plan = Plan::where('name', $planSlug)->first();
        if (!$plan) {
            return false;
        }
        return $this->subscriptions()->where('plan_id', $plan->id)->where('status', 'active')->exists();
    }

    public function plan(){
        $latest_subscription = $this->latestSubscription();
        return Plan::find($latest_subscription->plan_id);
    }

    public function planInterval(){
        $latest_subscription = $this->latestSubscription();
        return ($latest_subscription->cycle == 'month') ? 'Monthly' : 'Yearly'; 
    }

    public function latestSubscription()
    {
        return $this->subscriptions()->where('status', 'active')->orderBy('created_at', 'desc')->first();
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class, 'billable_id')->where('status', 'active')->orderBy('created_at', 'desc');
    }

    public function switchPlans(Plan $plan){
        $this->syncRoles([]);
        $this->assignRole( $plan->role->name );
    }

    public function invoices(){
        $user_invoices = [];

        if(is_null($this->subscription)){
            return null;
        }

        if(config('wave.billing_provider') == 'stripe'){
            $stripe = new \Stripe\StripeClient(config('wave.stripe.secret_key'));
            $subscriptions = $this->subscriptions()->get();        
            foreach($subscriptions as $subscription){
                $invoices = $stripe->invoices->all([ 'customer' => $subscription->vendor_customer_id, 'limit' => 100 ]);

                foreach($invoices as $invoice){
                    array_push($user_invoices, (object)[
                        'id' => $invoice->id,
                        'created' => \Carbon\Carbon::parse($invoice->created)->isoFormat('MMMM Do YYYY, h:mm:ss a'),
                        'total' => number_format(($invoice->total /100), 2, '.', ' '),
                        'download' => $invoice->invoice_pdf
                    ]);
                }
            }
        } else { 
            $paddle_url = (config('wave.paddle.env') == 'sandbox') ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com';
            $response = Http::withToken(config('wave.paddle.api_key'))->get($paddle_url . '/transactions', [
                'subscription_id' => $this->subscription->vendor_subscription_id
            ]);
            $responseJson = json_decode($response->body());
            foreach($responseJson->data as $invoice){
                array_push($user_invoices, (object)[
                    'id' => $invoice->id,
                    'created' => \Carbon\Carbon::parse($invoice->created_at)->isoFormat('MMMM Do YYYY, h:mm:ss a'),
                    'total' => number_format(($invoice->details->totals->subtotal /100), 2, '.', ' '),
                    'download' => '/settings/invoices/' . $invoice->id
                ]);
            }
        }

        return $user_invoices;
    }

    /**
     * @return bool
     */
    public function canImpersonate()
    {
        // If user is admin they can impersonate
        return $this->hasRole('admin');
    }

    /**
     * @return bool
     */
    public function isAdmin()
    {
        // return if the user has a role of admin
        return $this->hasRole('admin');
    }

    /**
     * @return bool
     */
    public function canBeImpersonated()
    {
        // Any user that is not an admin can be impersonated
        return !$this->hasRole('admin');
    }

    public function hasChangelogNotifications()
    {
        // Get the latest Changelog
        $latest_changelog = Changelog::orderBy('created_at', 'DESC')->first();

        if (!$latest_changelog) return false;
        return !$this->changelogs->contains($latest_changelog->id);
    }

    public function link(){
        return url('/profile/' . $this->username);
    }

    public function changelogs()
    {
        return $this->belongsToMany('Wave\Changelog');
    }

    public function createApiKey($name)
    {
        return ApiKey::create(['user_id' => $this->id, 'name' => $name, 'key' => Str::random(60)]);
    }

    public function apiKeys()
    {
        return $this->hasMany('Wave\ApiKey')->orderBy('created_at', 'DESC');
    }

    public function avatar()
    {
        return Storage::url($this->avatar);
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar();
    }

    public function profile($key)
    {
        $keyValue = $this->profileKeyValue($key);
        return isset($keyValue->value) ? $keyValue->value : '';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin' && auth()->user()->hasRole('admin')) {
            return true;
        }

        return false;
    }

    /*** PUT ALL THESE below into a trait */

    /**
     * Return default User Role.
     */
    // public function role()
    // {
    //     return $this->belongsTo(Role::class);
    // }

    // /**
    //  * Return alternative User Roles.
    //  */
    // public function roles()
    // {
    //     return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    // }

    // /**
    //  * Return all User Roles, merging the default and alternative roles.
    //  */
    // public function roles_all()
    // {
    //     $this->loadRolesRelations();

    //     return collect([$this->role])->merge($this->roles);
    // }

    // /**
    //  * Check if User has a Role(s) associated.
    //  *
    //  * @param string|array $name The role(s) to check.
    //  *
    //  * @return bool
    //  */
    // public function hasRole($name)
    // {
    //     $roles = $this->roles_all()->pluck('name')->toArray();

    //     foreach ((is_array($name) ? $name : [$name]) as $role) {
    //         if (in_array($role, $roles)) {
    //             return true;
    //         }
    //     }

    //     return false;
    // }

    // /**
    //  * Set default User Role.
    //  *
    //  * @param string $name The role name to associate.
    //  */
    // public function setRole($name)
    // {
    //     $role = Role::where('name', '=', $name)->first();

    //     if ($role) {
    //         $this->role()->associate($role);
    //         $this->save();
    //     }

    //     return $this;
    // }

    // public function hasPermission($name)
    // {
    //     $this->loadPermissionsRelations();

    //     $_permissions = $this->roles_all()
    //                           ->pluck('permissions')->flatten()
    //                           ->pluck('key')->unique()->toArray();

    //     return in_array($name, $_permissions);
    // }

    // public function hasPermissionOrFail($name)
    // {
    //     if (!$this->hasPermission($name)) {
    //         throw new UnauthorizedHttpException(null);
    //     }

    //     return true;
    // }

    // public function hasPermissionOrAbort($name, $statusCode = 403)
    // {
    //     if (!$this->hasPermission($name)) {
    //         return abort($statusCode);
    //     }

    //     return true;
    // }

    // private function loadRolesRelations()
    // {
    //     if (!$this->relationLoaded('role')) {
    //         $this->load('role');
    //     }

    //     if (!$this->relationLoaded('roles')) {
    //         $this->load('roles');
    //     }
    // }

    // private function loadPermissionsRelations()
    // {
    //     $this->loadRolesRelations();

    //     if ($this->role && !$this->role->relationLoaded('permissions')) {
    //         $this->role->load('permissions');
    //         $this->load('roles.permissions');
    //     }
    // }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function scopeUser($query)
    {
        //return $query->where('type', 'user');
        return $query;
    }

    /**
     * The contact the user has access to.
     * Applied only when selected_contacts is true for a user in
     * users table
     */
    public function contactAccess()
    {
        return $this->belongsToMany(Contact::class, 'user_contact_access');
    }

    /**
     * Get all of the users's notes & documents.
     */
    public function documentsAndnote()
    {
        return $this->morphMany(DocumentAndNote::class, 'notable');
    }

    /**
     * Creates a new user based on the input provided.
     *
     * @return object
     */
    public static function create_user($details)
    {
        $user = User::create([
            'surname' => $details['surname'],
            'name' => $details['name'],
            //'last_name'  => $details['last_name'],
            'username' => $details['username'],
            'email' => $details['email'],
            'password' => Hash::make($details['password']),
            'language' => ! empty($details['language']) ? $details['language'] : 'en',
        ]);

        return $user;
    }

    /**
     * Gives locations permitted for the logged in user
     *
     * @param: int $business_id
     *
     * @return string or array
     */
    public function permitted_locations($business_id = null)
    {
        $user = $this;

        if ($user->can('access_all_locations')) {
            return 'all';
        } else {
            $business_id = ! is_null($business_id) ? $business_id : null;
            if (empty($business_id) && auth()->check()) {
                $business_id = auth()->user()->business_id;
            }
            if (empty($business_id) && session()->has('business.id')) {
                $business_id = session('business.id');
            }

            $permitted_locations = [];
            $all_locations = BusinessLocation::where('business_id', $business_id)->get();
            $permissions = $user->permissions->pluck('name')->all();
            foreach ($all_locations as $location) {
                if (in_array('location.'.$location->id, $permissions)) {
                    $permitted_locations[] = $location->id;
                }
            }

            return $permitted_locations;
        }
    }

    /**
     * Returns if a user can access the input location
     *
     * @param: int $location_id
     *
     * @return bool
     */
    public static function can_access_this_location($location_id, $business_id = null)
    {
        $permitted_locations = auth()->user()->permitted_locations($business_id);

        if ($permitted_locations == 'all' || in_array($location_id, $permitted_locations)) {
            return true;
        }

        return false;
    }

    public function scopeOnlyPermittedLocations($query)
    {
        $user = auth()->user();
        $permitted_locations = $user->permitted_locations();
        $is_admin = $user->hasAnyPermission('Admin#'.$user->business_id);
        if ($permitted_locations != 'all' && ! $user->can('superadmin') && ! $is_admin) {
            $permissions = ['access_all_locations'];
            foreach ($permitted_locations as $location_id) {
                $permissions[] = 'location.'.$location_id;
            }

            return $query->whereHas('permissions', function ($q) use ($permissions) {
                $q->whereIn('permissions.name', $permissions);
            });
        } else {
            return $query;
        }
    }

    /**
     * Return list of users dropdown for a business
     *
     * @param $business_id int
     * @param $prepend_none = true (boolean)
     * @param $include_commission_agents = false (boolean)
     * @return array users
     */
    public static function forDropdown($business_id, $prepend_none = true, $include_commission_agents = false, $prepend_all = false, $check_location_permission = false)
    {
        $query = User::where('business_id', $business_id)
                    ->user();

        /*if (! $include_commission_agents) {
            $query->where('is_cmmsn_agnt', 0);
        }*/

        if ($check_location_permission) {
            $query->onlyPermittedLocations();
        }

        $all_users = $query->select('id', DB::raw("CONCAT(COALESCE(surname, ''),' ',COALESCE(name,'')) as full_name"))->get();
        $users = $all_users->pluck('full_name', 'id');

        //Prepend none
        if ($prepend_none) {
            $users = $users->prepend(__('lang_v1.none'), '');
        }

        //Prepend all
        if ($prepend_all) {
            $users = $users->prepend(__('lang_v1.all'), '');
        }

        return $users;
    }

    /**
     * Return list of sales commission agents dropdown for a business
     *
     * @param $business_id int
     * @param $prepend_none = true (boolean)
     * @return array users
     */
    public static function saleCommissionAgentsDropdown($business_id, $prepend_none = true)
    {
        $all_cmmsn_agnts = User::where('business_id', $business_id)
                        //->where('is_cmmsn_agnt', 1)
                        ->select('id', DB::raw("CONCAT(COALESCE(surname, ''),' ',COALESCE(name,'')) as full_name"));

        $users = $all_cmmsn_agnts->pluck('full_name', 'id');

        //Prepend none
        if ($prepend_none) {
            $users = $users->prepend(__('lang_v1.none'), '');
        }

        return $users;
    }

    /**
     * Return list of users dropdown for a business
     *
     * @param $business_id int
     * @param $prepend_none = true (boolean)
     * @param $prepend_all = false (boolean)
     * @return array users
     */
    public static function allUsersDropdown($business_id, $prepend_none = true, $prepend_all = false)
    {
        $all_users = User::where('business_id', $business_id)
                        ->select('id', DB::raw("CONCAT(COALESCE(surname, ''),' ',COALESCE(name,'')) as full_name"));

        $users = $all_users->pluck('full_name', 'id');

        //Prepend none
        if ($prepend_none) {
            $users = $users->prepend(__('lang_v1.none'), '');
        }

        //Prepend all
        if ($prepend_all) {
            $users = $users->prepend(__('lang_v1.all'), '');
        }

        return $users;
    }

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getUserFullNameAttribute()
    {
        return "{$this->surname} {$this->name}";
    }

    /**
     * Return true/false based on selected_contact access
     *
     * @return bool
     */
    public static function isSelectedContacts($user_id)
    {
        $user = User::findOrFail($user_id);

        return (bool) $user->selected_contacts;
    }

    public function getRoleNameAttribute()
    {
        $role_name_array = $this->getRoleNames();
        $role_name = ! empty($role_name_array[0]) ? explode('#', $role_name_array[0])[0] : '';

        return $role_name;
    }

    public function media()
    {
        return $this->morphOne(\App\Media::class, 'model');
    }

    /**
     * Find the user instance for the given username.
     *
     * @param  string  $username
     * @return \App\User
     */
    public function findForPassport($username)
    {
        return $this->where('username', $username)->first();
    }

    /**
     * Get the contact for the user.
     */
    public function contact()
    {
        return $this->belongsTo(\Modules\Crm\Entities\CrmContact::class, 'crm_contact_id');
    }

    /**
     * Get the products image.
     *
     * @return string
     */
    public function getImageUrlAttribute()
    {
        if (isset($this->media->display_url)) {
            $img_src = $this->media->display_url;
        } else {
            $img_src = 'https://ui-avatars.com/api/?name='.$this->name;
        }

        return $img_src;
    }
}
