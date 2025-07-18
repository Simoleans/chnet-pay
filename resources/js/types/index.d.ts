import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
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
}

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    // Propiedades adicionales del modelo
    phone?: string;
    address?: string;
    zone_id?: number;
    code?: string;
    id_number?: string;
    plan_id?: number;
    status?: boolean;
    role?: number;
    credit_balance?: number;
    due?: number;
    // Relaciones
    zone?: Zone;
    plan?: Plan;
}

export interface Zone {
    id: number;
    name: string;
    description?: string;
}

export interface Plan {
    id: number;
    name: string;
    price: string;
    mbps?: number;
    type?: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
