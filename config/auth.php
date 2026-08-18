<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'profissionais'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    /*
    | doc 13.4: dois guards, nao um guard com verificacao de papel.
    |
    | Guards separados dao ISOLAMENTO DE SESSAO: o cookie de sessao do paciente nao e o
    | mesmo do profissional. Um medico logado no mesmo navegador em que um paciente
    | acessou o portal nao corre risco de confusao de contexto -- e nenhuma rota de
    | escrita da equipe e alcancavel a partir de uma sessao de paciente, mesmo que uma
    | Policy falhe.
    |
    | RN-27: o guard `paciente` fica sem nenhuma role e sem nenhuma permission, de
    | proposito. Qualquer can() avaliado nele nega por construcao.
    */
    'guards' => [
        // Equipe assistencial e administrativa. Sessao de 30 min (RNF-10).
        'web' => [
            'driver' => 'session',
            'provider' => 'profissionais',
        ],

        // Portal do paciente, somente leitura. Sessao de 15 min (RNF-09).
        'paciente' => [
            'driver' => 'session',
            'provider' => 'pacientes',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    /*
    | Os dois providers apontam para o MESMO model: D-02, heranca por tabela de classe.
    | Uma pessoa tem um unico usuario mesmo quando e profissional e paciente ao mesmo
    | tempo -- e a auditoria mostra corretamente "Ana acessou o prontuario de Ana".
    |
    | A separacao por `tipo` e feita nas credenciais do attempt(), nao aqui: cada
    | controller de login inclui `tipo` no array de credenciais, e o EloquentUserProvider
    | transforma isso em WHERE. Ver AutenticacaoPacienteTest.
    */
    'providers' => [
        'profissionais' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        'pacientes' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'profissionais' => [
            'provider' => 'profissionais',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],

        // Janela mais curta para o paciente: doc 13.4.
        'pacientes' => [
            'provider' => 'pacientes',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 30,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the amount of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
