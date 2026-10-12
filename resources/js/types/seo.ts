export type ContentSection = { heading: string; text: string };
export type FaqItem = { question: string; answer: string };
export type SeoContent = {
    id: number;
    path: string;
    kind: string;
    title: string;
    description: string;
    heading: string;
    intro: string;
    sections: ContentSection[];
    faq: FaqItem[];
    status: 'draft' | 'published';
    noindex: boolean;
    keyword: string | null;
    social_title: string | null;
    social_description: string | null;
    updated_at: string;
};
export type SeoMeta = {
    title: string;
    description: string;
    canonical: string;
    robots: string;
    site_name: string;
    social_title: string;
    social_description: string;
    social_image: string | null;
    google_verification: string | null;
    bing_verification: string | null;
    schema: Record<string, unknown>;
};
export type PublicLink = { path: string; heading: string; intro: string };
export type MarketingProps = {
    content: SeoContent;
    seo: SeoMeta;
    publicLinks: PublicLink[];
};
export type SeoManagement = {
    settings: {
        site_name: string | null;
        public_url: string | null;
        indexing_enabled: boolean;
        allow_ai_search: boolean;
        allow_ai_training: boolean;
        google_verification: string | null;
        bing_verification: string | null;
        social_image: string | null;
        contact_email: string | null;
    };
    effective_name: string;
    effective_url: string;
    environment: string;
    indexing_active: boolean;
    public_domain: boolean;
    pages: (SeoContent & {
        resolved_title: string;
        resolved_description: string;
        canonical: string;
        warnings: string[];
    })[];
};
