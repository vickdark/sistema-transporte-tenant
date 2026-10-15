<?php

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

// Truncation y no RefreshDatabase: el schema de cada empresa se crea por la conexión central y
// se migra por otra conexión, que no vería nada dentro de la transacción de RefreshDatabase.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Feature');

// Authentication, settings and dashboard are tenant routes: run them on a tenant domain.
pest()->beforeEach(function () {
    $this->initializeTenant();
})->in('Feature/Auth', 'Feature/Settings', 'Feature/Tenant', 'Feature/DashboardTest.php');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
