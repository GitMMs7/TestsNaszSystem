<?php

namespace SCTeam\Auth\Tests\Browser;

use Faker\Factory;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use SCTeam\Auth\Enums\UserStatus;
use SCTeam\Auth\Tests\CreateUsers;
use SCTeam\Auth\Tests\Traits\LoggableTest;
use SCTeam\Base\Tests\DuskTestCase;

/**
 * @group Auth
 */
class AuthTest extends DuskTestCase {
    use LoggableTest;
    use CreateUsers;

    /**
     * @testNotCorrectLoginCompleteDoesNotExistAndPassword
     */
    public function testNotCorrectLoginCompleteDoesNotExistAndPassword(): void { // ok 1

        $email = fake()->safeEmail();
        $password = Str::random(10);

        $this->browse(function (Browser $browser) use ($email, $password) {
            $browser->visit('/login'); // przechodzę na http://localhost/login  // niepoprawny nieistniejące konto 1
            $browser->type('email', $email); //wprowadzam w pole formularza logowania adres e-mail wartość nie@istnieje adres e-mail nie istnieje w bazie
            $browser->type('password', $password); //wprowadzam w pole formularza logowania hasło wartość 'bzdury Tptal 123'
            $browser->clickAtXPath('//*[@id="login-form"]/div[3]/div/button');
            $browser->assertSee(__t('auth.failed')); //sprawdzam, czy na stronie jest napis 'Błędny login lub hasło.'

            $browser->screenshot('testNotCorrectLoginCompleteDoesNotExistAndPassword' . now()->format('Y-m-d H:i:s'));
        });
    }

    public function testLoginWithInvalidPassword() { // ok 2
        $password = Str::random(10);
        $user = $this->makeAdmin(['password' => \Hash::make($password),]);
        $this->browse(function (Browser $browser) use ($user) {
            $invalidPassword = Str::random(10);

            $this->login($browser, $user, $invalidPassword);
            $browser->assertSee(__t('auth.failed')); //sprawdzam, czy na stronie jest napis 'Błędny login lub hasło.'

            $browser->screenshot('testLoginWithInvalidPassword' . now()->format('Y-m-d H:i:s'));
        });
        \DB::table('users')->where('email', $user->email)->delete();
    }

    public function testLoginWithInvalid2FA() { // ok 3

        $this->browse(function (Browser $browser) {
            $browser->visit('/login');

            $password = Str::random(10);
            $user = $this->makeUser(['password' => \Hash::make($password)]);

            $browser->type('email', $user->email);
            $browser->type('password', $password);

            $browser->press("button[type=\"submit\"]"); // naciskam przycisk zaloguj

            if(config('scteam.auth.2fa_enabled')) {
                $this->enterCode($browser, rand(100000, 999999));
                $browser->press("button[type=\"submit\"]"); // naciskam przycisk zaloguj
                $browser->assertSee(__t('scteam.auth::common.the_two_factor_code_you_have_entered_does_not_match'));
            }
        });
    }
    
    /**
     * @testLoginCorrectUser
     */
    public function testLoginCorrectUser(): void { // ok 4
        $password = Str::random(10);
        $user = $this->makeAdmin(['password' => \Hash::make($password),]);
        $this->browse(function (Browser $browser) use ($user, $password) {
            // Traits to Auth\Tests\Traits\LoggableTest.php
            $this->login($browser, $user, $password);
            $browser->assertSee($user->first_name . " " . $user->last_name);

            $browser->screenshot('testLoginCorrectUser' . now()->format('Y-m-d H:i:s'));
        });
        \DB::table('users')->where('email', $user->email)->delete();
    }

    
    
    /**
     * @testChangePasswordCorrectly
     */
    public function testChangePasswordCorrectly(): void { //ok 5
        $password = Str::random(10);
        $user = $this->makeAdmin(['password' => \Hash::make($password),]);
        $this->browse(function (Browser $browser) use ($user, $password) {

            // Traits to Auth\Tests\Traits\LoggableTest.php
            $this->resetPassword($browser, $user, $password);
            $browser->assertSee($user->first_name . " " . $user->last_name); //sprawdzam, czy na stronie jest napis mateusz kowalski

            $browser->screenshot('testChangePasswordCorrectly' . now()->format('Y-m-d H:i:s'));
        });
        \DB::table('users')->where('email', $user->email)->delete();
    }

    /**
     * @testLoginAsDisabledUser
     */
    public function testLoginAsDisabledUser(): void { //ok 6
        $password = Str::random(10);
        $user = $this->makeAdmin([   'password' => \Hash::make($password),
                                     'status' => UserStatus::Inactive,]);

        $this->browse(function (Browser $browser) use ($user, $password) {
            $browser->visit('/login');
            $browser->type('email', $user->email);
            $browser->type('password', $password);

            $browser->press("button[type=\"submit\"]");
            //sprawdzam, czy na stronie jest napis 'Konto zostało zablokowane.'
            $browser->assertSee(__t('scteam.auth::common.the_account_has_been_blocked'));

            \DB::table('users')->where('email', $user->email)->delete();

            $browser->screenshot('testLoginAsDisabledUser' . now()->format('Y-m-d H:i:s'));
        });
    }

    public function testLoginAsDeletedUser() { //ok 7

        $this->browse(function (Browser $browser) {
            $password = Str::random(10);
            $user = $this->makeAdmin(['password' => \Hash::make($password),]);
            $id = $user->id;

            $this->loginAsAdmin($browser);

            $browser->visit('/admin/users');
            $browser->waitFor("a[href$=\"/admin/users/$id\"] + .dropdown-toggle[data-bs-toggle=\"dropdown\"]", 40);
            $browser->click("a[href$=\"/admin/users/$id\"] + .dropdown-toggle[data-bs-toggle=\"dropdown\"]");
            $browser->waitFor("a[href$=\"/admin/users/$id\"]~.dropdown-menu button[data-key=\"delete\"]",10);
            $browser->click("a[href$=\"/admin/users/$id\"]~.dropdown-menu button[data-key=\"delete\"]");

            $browser->waitForDialog(20)->acceptDialog();
            $browser->waitUntilMissingText("$user->first_name $user->last_name", 90);

            $browser->logout();
            $this->login($browser, $user, $password);
            
            $browser->assertSee(__t('auth.failed'));
        });
    }
}
