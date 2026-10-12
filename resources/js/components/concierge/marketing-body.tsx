import { Link } from '@inertiajs/react';
import { ArrowRight, BookOpen } from 'lucide-react';
import type { MarketingProps } from '@/types/seo';
export function MarketingBody({
    content,
    publicLinks,
}: Pick<MarketingProps, 'content' | 'publicLinks'>) {
    const links = publicLinks.filter((p) => p.path !== content.path);
    return (
        <section className="marketing-body landing-container">
            <div className="marketing-prose">
                {content.sections.map((section, i) => (
                    <section key={i}>
                        <h2>{section.heading}</h2>
                        {section.text.split(/\n\s*\n/).map((text, n) => (
                            <p key={n}>{text}</p>
                        ))}
                    </section>
                ))}
                {content.faq.length > 0 && (
                    <section className="marketing-faq">
                        <span className="section-kicker">Vos questions</span>
                        <h2>Les réponses utiles avant de commencer.</h2>
                        {content.faq.map((item, i) => (
                            <details key={i}>
                                <summary>{item.question}</summary>
                                <p>{item.answer}</p>
                            </details>
                        ))}
                    </section>
                )}
            </div>
            {links.length > 0 && (
                <section className="marketing-resources">
                    <div className="section-heading">
                        <span className="section-kicker">
                            <BookOpen size={16} /> Pour aller plus loin
                        </span>
                        <h2>Des outils et des méthodes pour votre activité.</h2>
                    </div>
                    <div className="marketing-link-grid">
                        {links.map((item) => (
                            <Link
                                href={item.path}
                                key={item.path}
                                className="marketing-link-card"
                            >
                                <span className="section-kicker">
                                    {item.path.startsWith('/guides')
                                        ? 'Guide pratique'
                                        : item.path.startsWith('/solutions')
                                          ? 'Votre activité'
                                          : 'Fonctionnalité'}
                                </span>
                                <h3>{item.heading}</h3>
                                <p>{item.intro}</p>
                                <span className="terrain-link">
                                    Découvrir <ArrowRight size={15} />
                                </span>
                            </Link>
                        ))}
                    </div>
                </section>
            )}
        </section>
    );
}
