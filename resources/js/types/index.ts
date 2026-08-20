import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User | null;
    /** Nomes das roles do spatie. Navegacao por perfil, nunca controle de acesso. */
    roles: string[];
    /** Permissoes efetivas, no formato recurso.acao. */
    permissoes: string[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
    /**
     * Permissoes que liberam o item. Se ausente, o item e visivel a qualquer usuario
     * autenticado; se presente, basta UMA delas.
     */
    permissoes?: string[];
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    /** `alerta` e distinto de `status`: A1 do UC-01 nao e sucesso nem erro. */
    flash: { status: string | null; alerta: string | null };
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export type TipoUsuario = 'PACIENTE' | 'PROFISSIONAL' | 'ADMIN';

/**
 * Subconjunto compartilhado pelo HandleInertiaRequests -- nao o model inteiro.
 * `login` fica de fora de proposito: para paciente ele e o CPF (RN-04).
 */
export interface User {
    id: number;
    name: string;
    email: string | null;
    tipo: TipoUsuario;
    senha_provisoria: boolean;
    avatar?: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
