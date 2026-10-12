import { Head } from '@inertiajs/react';
import type { SeoMeta } from '@/types/seo';
export function SeoHead({ seo }: { seo: SeoMeta }) {
    return (
        <Head title={seo.title}>
            <meta
                head-key="description"
                name="description"
                content={seo.description}
            />
            <meta head-key="robots" name="robots" content={seo.robots} />
            <link head-key="canonical" rel="canonical" href={seo.canonical} />
            <meta head-key="og:type" property="og:type" content="website" />
            <meta head-key="og:locale" property="og:locale" content="fr_FR" />
            <meta
                head-key="og:site_name"
                property="og:site_name"
                content={seo.site_name}
            />
            <meta
                head-key="og:title"
                property="og:title"
                content={seo.social_title}
            />
            <meta
                head-key="og:description"
                property="og:description"
                content={seo.social_description}
            />
            <meta head-key="og:url" property="og:url" content={seo.canonical} />
            <meta
                head-key="twitter:card"
                name="twitter:card"
                content={seo.social_image ? 'summary_large_image' : 'summary'}
            />
            <meta
                head-key="twitter:title"
                name="twitter:title"
                content={seo.social_title}
            />
            <meta
                head-key="twitter:description"
                name="twitter:description"
                content={seo.social_description}
            />
            {seo.social_image && (
                <meta
                    head-key="og:image"
                    property="og:image"
                    content={seo.social_image}
                />
            )}
            {seo.social_image && (
                <meta
                    head-key="twitter:image"
                    name="twitter:image"
                    content={seo.social_image}
                />
            )}
            {seo.google_verification && (
                <meta
                    head-key="google-site-verification"
                    name="google-site-verification"
                    content={seo.google_verification}
                />
            )}
            {seo.bing_verification && (
                <meta
                    head-key="msvalidate.01"
                    name="msvalidate.01"
                    content={seo.bing_verification}
                />
            )}
            <script head-key="structured-data" type="application/ld+json">
                {JSON.stringify(seo.schema).replace(/</g, '\\u003c')}
            </script>
        </Head>
    );
}
