<?php

namespace SCTeam\Auth\Tests\Traits;

use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use SCTeam\Auth\Tests\CreateUsers;

trait LoggableTest
{
    use CreateUsers;

    public function loginAsAdmin($browser) {
        $password = Str::random(10);
        $user = $this->makeAdmin(['password' => \Hash::make($password),]);
        $this->login($browser, $user, $password);
    }

    public function login($browser, $user, $password) {
        $browser->visit('/login'); // przechodzę na http://localhost/login

        $browser->type('email', $user->email); //wprowadzam w pole formularza logowania adres e-mail wartość adres e-mail użytkownika
        $browser->type('password', $password); //wprowadzam w pole formularza logowania hasło wartość Str::random(10);

        $browser->press("button[type=\"submit\"]"); // naciskam przycisk zaloguj

        if(config('scteam.auth.2fa_enabled')) {
            $this->enterCode($browser, $user->fresh()->two_factor_code);//*[@id="login-form"]/div[3]/div/button
            $browser->clickAtXPath('//*[@id="2fa-form"]/div[2]/div[1]/button'); // naciskam przycisk zaloguj//*[@id="login-form"]/div[3]/div/button
        }
    }

    public function resetPassword($browser, $user, $password) {
        $browser->visit('/password/reset/'); // przechodzę na http://localhost/password/reset/
        $browser->type('email', $user->email); //wprowadzam w pole formularza logowania adres e-mail wartość adres e-mail użytkownika
        $browser->press('.btn.btn-block.btn-flat.btn-primary.col-12'); // naciskam przycisk resetuj hasło

        $broker = $this->app[PasswordBrokerFactory::class];
        $token = $broker->broker()->createToken($user);
        $browser->visit(route('password.reset', ['token' => $token, 'email' => $user->email], false)); // przechodzę na http://localhost/password/reset/token

        $browser->type('email', $user->email); //wprowadzam w pole formularza logowania adres e-mail wartość adres e-mail użytkownika
        $browser->type('password', $password); //wprowadzam w pole formularza logowania hasło wartość nowehaslo
        $browser->type('password_confirmation', $password); //wprowadzam w pole formularza logowania potwierdź hasło wartość nowehaslo

        $browser->press('.btn.btn-block.btn-flat.btn-primary.col-12'); // naciskam przycisk resetuj hasło
    }

    public function enterCode(Browser $browser, ?string $code = ''): void {
        //wypełniam formularz weryfikacji dwuetapowej kodem pobranym z bazy danych
        for ($i = 1; $i <= 6; $i++) {
            $browser->type("#digit-$i", substr($code, $i - 1, 1));
        }
    }
}