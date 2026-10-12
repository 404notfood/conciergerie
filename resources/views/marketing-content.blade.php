<div class="brand-surface landing marketing-page">
    <a class="skip-link" href="#main">Aller au contenu</a>
    <header class="landing-header"><div class="landing-container flex items-center justify-between gap-5">
        <a href="{{ route('home') }}" class="brand text-xl font-bold">{{ $seo['site_name'] }}<span class="brand-dot">.</span></a>
        <nav class="marketing-nav" aria-label="Navigation principale"><a href="{{ route('resources') }}">Ressources</a><a href="{{ route('pricing') }}">Tarifs</a><a href="{{ route('login') }}">Connexion</a></nav>
    </div></header>
    <main id="main">
        <section class="marketing-hero landing-container">
            @if($content['path'] !== '/')<nav aria-label="Fil d’Ariane"><a href="{{ route('home') }}">Accueil</a> / @if($content['kind'] === 'content')<a href="{{ route('resources') }}">Ressources</a> / @endif {{ $content['kind'] === 'hub' ? 'Ressources' : $content['heading'] }}</nav>@endif
            <span class="section-kicker">La gestion de votre conciergerie</span>
            <h1>{{ $content['heading'] }}</h1>
            <p>{{ $content['intro'] }}</p>
            <a href="{{ route('register') }}" class="brand-button">Créer mon espace →</a>
        </section>
        @if($content['kind'] === 'pricing')
            <section class="landing-container directory-grid">
                @forelse($page['props']['plans'] as $plan)
                    <article class="workspace-card admin-plan-card">
                        <h2>{{ $plan['name'] }}</h2><p>{{ $plan['description'] }}</p>
                        <div class="admin-plan-price">{{ number_format($plan['monthly_price_cents'] / 100, 2, ',', ' ') }} € <small>/ mois</small></div>
                        <div class="admin-plan-limits">@foreach($plan['limits'] as $key => $value)<div><span>{{ $page['props']['limitLabels'][$key] }}</span><b>{{ $value === null ? 'Illimité' : $value }}</b></div>@endforeach</div>
                        <div class="admin-plan-options">@foreach($plan['features'] as $key => $enabled)@if($enabled)<span>{{ $page['props']['featureLabels'][$key] }}</span>@endif @endforeach</div>
                        <a href="{{ route('register') }}" class="brand-button small mt-5">Créer mon espace</a>
                    </article>
                @empty
                    <article class="workspace-card"><h2>Les offres seront présentées ici.</h2><p>Vous pouvez déjà créer votre espace pour découvrir la plateforme.</p></article>
                @endforelse
            </section>
        @endif
        <section class="marketing-body landing-container">
            <div class="marketing-prose">
                @foreach($content['sections'] as $section)
                    <section><h2>{{ $section['heading'] }}</h2>@foreach(preg_split('/\n\s*\n/', $section['text']) as $paragraph)<p>{{ $paragraph }}</p>@endforeach</section>
                @endforeach
                @if(count($content['faq']))
                    <section class="marketing-faq"><span class="section-kicker">Vos questions</span><h2>Les réponses utiles avant de commencer.</h2>
                        @foreach($content['faq'] as $item)<details><summary>{{ $item['question'] }}</summary><p>{{ $item['answer'] }}</p></details>@endforeach
                    </section>
                @endif
            </div>
            <section class="marketing-resources"><div class="section-heading"><span class="section-kicker">Pour aller plus loin</span><h2>Des outils et des méthodes pour votre activité.</h2></div>
                <div class="marketing-link-grid">@foreach($publicLinks as $item)@if($item['path'] !== $content['path'])<a href="{{ $item['path'] }}" class="marketing-link-card"><span class="section-kicker">{{ str_starts_with($item['path'], '/guides') ? 'Guide pratique' : 'Découvrir' }}</span><h3>{{ $item['heading'] }}</h3><p>{{ $item['intro'] }}</p><span class="terrain-link">Découvrir →</span></a>@endif @endforeach</div>
            </section>
        </section>
    </main>
    <footer class="landing-footer"><div class="landing-container"><a href="{{ route('home') }}" class="brand">{{ $seo['site_name'] }}</a><p>La plateforme de gestion des conciergeries</p><a href="{{ route('pricing') }}">Découvrir les offres</a></div></footer>
</div>
