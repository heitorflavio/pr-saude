<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $usuario = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                /*
                 * Subconjunto explicito, nao o model inteiro.
                 *
                 * `users.login` e o CPF quando o usuario e paciente (RN-04). Serializar
                 * o model completo para o frontend colocaria CPF em toda pagina, em
                 * texto claro no HTML inicial e em cada resposta do Inertia -- exatamente
                 * o tipo de vazamento silencioso que a doc 14.2 pede para evitar.
                 */
                'user' => $usuario === null ? null : [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'email' => $usuario->email,
                    'tipo' => $usuario->tipo,
                    'senha_provisoria' => $usuario->senha_provisoria,
                ],

                // Navegacao por perfil: cada usuario ve so o que pode acessar.
                'roles' => $usuario?->getRoleNames() ?? [],
                'permissoes' => $usuario?->getAllPermissions()->pluck('name') ?? [],
            ],

            /*
             * Mensagens de uma requisicao para a proxima.
             *
             * `alerta` e distinto de `status` de proposito: A1 do UC-01 -- "ja existe
             * cadastro para este CPF" nao e sucesso nem erro de validacao, e um desvio
             * de fluxo que precisa de destaque proprio na tela.
             */
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'alerta' => fn () => $request->session()->get('alerta'),
            ],
        ];
    }
}
