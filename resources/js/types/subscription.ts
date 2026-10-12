export type Limits = Record<string, number | null>;
export type Features = Record<string, boolean>;
export type CatalogItem = {
    id: number;
    name: string;
    monthly_price_cents: number;
    limits?: Limits;
    limit_bonus?: Record<string, number>;
    features: Features;
    is_active: boolean;
    is_public?: boolean;
    description: string | null;
};
export type Subscription = {
    id: number;
    subscription_plan_id: number;
    status: string;
    terms: {
        plan_name: string;
        base_price_cents: number;
        monthly_total_cents: number;
        limits: Limits;
        features: Features;
        addons: { id: number; name: string; monthly_price_cents: number }[];
    };
    limit_overrides: Limits | null;
    feature_overrides: Features | null;
    price_override_cents: number | null;
    starts_on: string;
    expires_at: string | null;
    notes: string | null;
};
export type ConciergeSummary = {
    id: number;
    name: string;
    created_at: string;
    subscription: Subscription;
    usage: Record<string, number>;
    limits: Limits;
    features: Features;
    monthly_price_cents: number;
    usable: boolean;
    members: {
        user: {
            id: number;
            name: string;
            email: string;
            is_admin: boolean;
            disabled_at: string | null;
        };
        role: string;
    }[];
};
export type AdminUser = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    disabled_at: string | null;
    created_at: string;
};
export type AdminProps = {
    seoManagement: import('@/types/seo').SeoManagement;
    plans: CatalogItem[];
    addons: CatalogItem[];
    concierges: ConciergeSummary[];
    users: AdminUser[];
    settings: {
        default_plan_id: number;
        trial_days: number;
        registration_open: boolean;
    };
    limitLabels: Record<string, string>;
    featureLabels: Record<string, string>;
    events: {
        id: number;
        description: string;
        actor: string;
        created_at: string;
        details: string | null;
    }[];
};
export function euros(cents: number) {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
    }).format(cents / 100);
}
