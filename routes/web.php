<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use App\Livewire\Dash\ContactListingComponent;
use App\Livewire\Dash\BusinessListingComponent;
use App\Livewire\Dash\CurrencyListingComponent;
use Illuminate\Support\Facades\Route;
use Wave\Facades\Wave;

// Wave routes
Wave::routes();

Route::group(['middleware' => 'auth'], function () {
    Route::get('contacts', ContactListingComponent::class)->name('contacts.list');
    Route::get('businesses', BusinessListingComponent::class)->name('businesses.list');
    Route::get('currencies', CurrencyListingComponent::class)->name('currencies.list');
});
