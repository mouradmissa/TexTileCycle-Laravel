@extends('layouts.back')

@section('page-title', 'Statistiques & impact')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/module5-admin.css') }}">
@endpush

@php
    $fmt = fn ($n, $d = 0) => number_format($n, $d, ',', ' ');
    $co2 = $kpis['co2_evite_kg'] >= 1000 ? $fmt($kpis['co2_evite_kg'] / 1000, 2).' t' : $fmt($kpis['co2_evite_kg'], 1).' kg';
    $statutsVetement = ['en_attente' => 'En attente', 'en_reparation' => 'En réparation', 'repare' => 'Réparé', 'donne' => 'Donné', 'recycle' => 'Recyclé'];
@endphp

@section('content')
<div class="dashboard">
    @include('back.partials.flash')

    <div class="dashboard-intro">
        <p class="muted">Indicateurs calculés en temps réel à partir de l'activité de tous les modules.</p>
        <div class="row-actions">
            <a href="{{ route('back.statistiques.export') }}" class="btn btn-secondary small"><i data-lucide="download"></i> Export CSV</a>
            <form method="post" action="{{ route('back.statistiques.consolider') }}">
                @csrf
                <input type="hidden" name="mois" value="12">
                <button type="submit" class="btn btn-primary small"><i data-lucide="database"></i> Consolider l'historique</button>
            </form>
        </div>
    </div>

    {{-- Impact écologique --}}
    <div class="stats-grid four">
        @foreach([
            ['shirt', 'Vêtements sauvés', $fmt($kpis['vetements_sauves']), 'Réparés, donnés ou recyclés', 'green'],
            ['wind', 'CO₂ évité', $co2, 'Production neuve évitée', 'orange'],
            ['droplets', 'Eau économisée', $fmt($kpis['eau_economisee_litres']).' L', 'Culture & teinture évitées', 'blue'],
            ['recycle', 'Taux de valorisation', $kpis['taux_valorisation'].' %', 'des vêtements déclarés', 'purple'],
        ] as [$icon, $label, $value, $detail, $tone])
            <div class="stat-card">
                <div @class(['stat-icon', $tone])><i data-lucide="{{ $icon }}"></i></div>
                <div>
                    <p class="eyebrow">{{ $label }}</p>
                    <strong>{{ $value }}</strong>
                    <small>{{ $detail }}</small>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Activité plateforme --}}
    <div class="stats-grid four" style="margin-top:14px">
        @foreach([
            ['users', 'Utilisateurs', $kpis['nb_utilisateurs'], '+'.$kpis['nb_inscrits_mois'].' ce mois-ci', 'green', null],
            ['shirt', 'Vêtements déclarés', $kpis['nb_vetements'], $kpis['nb_reparations'].' réparations · '.$kpis['nb_dons'].' dons validés', 'blue', null],
            ['store', 'Partenaires actifs', $kpis['nb_ateliers'] + $kpis['nb_associations'], $kpis['nb_ateliers'].' ateliers · '.$kpis['nb_associations'].' associations', 'purple',
                $kpis['nb_partenaires_en_attente'] ? [route('back.partenaires.index', ['statut' => 'en_attente']), $kpis['nb_partenaires_en_attente'].' en attente de validation'] : null],
            ['circle-alert', 'Signalements à traiter', $kpis['nb_signalements_en_attente'], 'Modération', 'orange',
                [route('back.signalements.index', ['statut' => 'en_attente']), 'Ouvrir la file de modération']],
        ] as [$icon, $label, $value, $detail, $tone, $link])
            <div class="stat-card">
                <div @class(['stat-icon', $tone])><i data-lucide="{{ $icon }}"></i></div>
                <div>
                    <p class="eyebrow">{{ $label }}</p>
                    <strong>{{ $fmt($value) }}</strong>
                    <small>{{ $detail }}</small>
                    @if($link)
                        <a href="{{ $link[0] }}" class="stat-link">{{ $link[1] }} →</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Activité + IA --}}
    <div class="dashboard-grid">
        <div class="panel chart-panel">
            <div class="panel-head">
                <div>
                    <h2>Activité mensuelle & prévisions</h2>
                    <p>12 derniers mois — pointillés : prévision IA sur 3 mois</p>
                </div>
            </div>
            <div class="chart-box"><canvas id="chartActivite" role="img" aria-label="Graphique de l'activité mensuelle et des prévisions. Les chiffres figurent dans le tableau Historique consolidé."></canvas></div>
        </div>

        <div class="panel ai-panel">
            <div class="ai-panel-icon"><i data-lucide="sparkles"></i></div>
            <p class="kicker">Analyse prédictive IA</p>
            <h2>Tendances & anomalies</h2>
            <div class="insights">
                @foreach(array_slice($analyse['insights'], 0, 5) as $insight)
                    <div class="insight {{ $insight['niveau'] }}">
                        <div class="insight-icon"><i data-lucide="{{ $insight['icone'] }}"></i></div>
                        <div>
                            <b>{{ $insight['titre'] }}</b>
                            <p>{{ $insight['message'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            @php($prevSauves = $analyse['previsions']['sauves'])
            @if($prevSauves['confiance'] !== 'insuffisante')
                <p class="kicker" style="margin-top:16px">Vêtements sauvés prévus · confiance {{ $prevSauves['confiance'] }}</p>
                <div class="forecast">
                    @foreach($analyse['labels_prevision'] as $i => $label)
                        <div><small>{{ $label }}</small><strong>{{ $fmt($prevSauves['valeurs'][$i]) }}</strong></div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Impact + répartition --}}
    <div class="grid-2">
        <div class="panel chart-panel">
            <div class="panel-head">
                <div>
                    <h2>Impact écologique mensuel</h2>
                    <p>CO₂ évité (kg) et eau économisée (L)</p>
                </div>
            </div>
            <div class="chart-box"><canvas id="chartImpact" role="img" aria-label="Graphique de l'impact écologique mensuel : CO₂ évité et eau économisée. Les chiffres figurent dans le tableau Historique consolidé."></canvas></div>
        </div>
        <div class="panel chart-panel">
            <div class="panel-head">
                <div>
                    <h2>Cycle de vie des vêtements</h2>
                    <p>Répartition par statut et types les plus déclarés</p>
                </div>
            </div>
            <div class="grid-2" style="margin:0;gap:0">
                <div class="chart-box small"><canvas id="chartStatuts" role="img" aria-label="Graphique de la répartition des vêtements par statut."></canvas></div>
                <div class="chart-box small"><canvas id="chartTypes" role="img" aria-label="Graphique des types de vêtements les plus déclarés."></canvas></div>
            </div>
        </div>
    </div>

    {{-- Historique consolidé --}}
    <h2 class="section-title">Historique consolidé</h2>
    <div class="panel table-panel">
        <table>
            <caption class="sr-only">Historique consolidé de l'activité</caption>
            <thead>
                <tr>
                    <th scope="col">Période</th>
                    <th scope="col">Vêtements</th>
                    <th scope="col">Réparations</th>
                    <th scope="col">Dons</th>
                    <th scope="col">Nouveaux inscrits</th>
                    <th scope="col">Ateliers actifs</th>
                    <th scope="col">Vêtements sauvés</th>
                    <th scope="col">CO₂ évité</th>
                    <th scope="col">Eau économisée</th>
                </tr>
            </thead>
            <tbody>
                @forelse($historique as $ligne)
                    <tr>
                        <td>
                            <b>{{ ucfirst($ligne['periode']->locale('fr')->isoFormat('MMMM YYYY')) }}</b>
                            @if($ligne['demo'])
                                <x-status-badge tone="blue" title="Généré par HistoriqueStatistiquesSeeder">démo</x-status-badge>
                            @endif
                        </td>
                        <td>{{ $ligne['nb_vetements'] }}</td>
                        <td>{{ $ligne['nb_reparations'] }}</td>
                        <td>{{ $ligne['nb_dons'] }}</td>
                        <td>{{ $ligne['nb_utilisateurs'] }}</td>
                        <td>{{ $ligne['nb_ateliers'] }}</td>
                        <td>{{ $ligne['vetements_sauves'] }}</td>
                        <td>{{ $fmt($ligne['co2_evite_kg'], 1) }} kg</td>
                        <td>{{ $fmt($ligne['eau_economisee_litres']) }} L</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="empty">Aucune période consolidée — cliquez sur « Consolider l'historique » ou lancez <code>php artisan statistiques:consolider --mois=12</code>.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Méthode de calcul --}}
    <details class="panel" style="margin-top:16px;padding:16px 20px">
        <summary style="cursor:pointer;font-weight:700">Méthode de calcul de l'impact écologique</summary>
        <p class="muted" style="margin-top:10px">
            Un vêtement est « sauvé » lorsqu'il est réparé (RDV terminé ou statut <em>réparé</em>), donné (don accepté ou statut <em>donné</em>) ou recyclé.
            Chaque vêtement n'est compté qu'une fois. L'impact correspond à l'empreinte de production d'un vêtement neuf évité (ordres de grandeur indicatifs),
            pondérée selon la valorisation : réparation et don {{ $facteurs['reparation'] * 100 }} %, recyclage {{ $facteurs['recyclage'] * 100 }} %.
        </p>
        <table class="method-table">
            <caption class="sr-only">Empreinte de production évitée par type de vêtement</caption>
            <thead><tr><th scope="col">Type de vêtement</th><th scope="col">CO₂ (kg)</th><th scope="col">Eau (L)</th></tr></thead>
            <tbody>
                @foreach($coefficients as $type => [$c, $e])
                    <tr><td>{{ ucfirst($type) }}</td><td>{{ $fmt($c, 1) }}</td><td>{{ $fmt($e) }}</td></tr>
                @endforeach
                <tr><td><em>Autre / non renseigné</em></td><td>{{ $fmt($coefficientDefaut[0], 1) }}</td><td>{{ $fmt($coefficientDefaut[1]) }}</td></tr>
            </tbody>
        </table>
    </details>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(() => {
    const series = @json($series);
    const analyse = @json($analyse);
    const repartition = @json($repartition);
    const topTypes = @json($topTypes);
    const statutLabels = @json($statutsVetement);

    const css = getComputedStyle(document.documentElement);
    const c = (name, fallback) => (css.getPropertyValue(name) || fallback).trim();
    const colors = {
        green: c('--green', '#2E7D32'), blue: c('--blue', '#3f83c9'),
        purple: c('--purple', '#8265c8'), orange: c('--orange', '#e88932'), line: c('--line', '#e5ebe7'),
    };

    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
    Chart.defaults.font.size = 11;
    Chart.defaults.color = c('--muted', '#6c7d75');
    Chart.defaults.plugins.legend.labels.boxWidth = 10;
    const grid = { color: colors.line };

    // Activité : historique + prévisions (le mois courant sert de point de raccord).
    const labels = [...series.labels, ...analyse.labels_prevision.slice(1)];
    const pad = (n) => Array(n).fill(null);
    const withForecast = (key) => {
        const histo = series.series[key];
        const prev = analyse.previsions[key];
        if (!prev || prev.confiance === 'insuffisante') return null;
        // Prévision : dernier mois complet → mois courant → 2 mois suivants.
        return [...pad(histo.length - 2), histo[histo.length - 2], ...prev.valeurs];
    };

    const datasets = [
        ['reparations', 'Réparations', colors.blue],
        ['dons', 'Dons validés', colors.purple],
        ['sauves', 'Vêtements sauvés', colors.green],
    ].flatMap(([key, label, color]) => {
        const sets = [{ label, data: series.series[key], borderColor: color, backgroundColor: color, tension: .35, pointRadius: 3 }];
        const prev = withForecast(key);
        if (prev) sets.push({ label: label + ' (prévision)', data: prev, borderColor: color, borderDash: [6, 4], pointRadius: 0, tension: .35 });
        return sets;
    });
    datasets.push({ label: 'Vêtements déclarés', data: series.series.vetements, borderColor: colors.orange, backgroundColor: colors.orange, tension: .35, pointRadius: 3 });

    new Chart(document.getElementById('chartActivite'), {
        type: 'line',
        data: { labels, datasets },
        options: {
            maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            scales: { y: { beginAtZero: true, grid, ticks: { precision: 0 } }, x: { grid: { display: false } } },
            plugins: { legend: { position: 'bottom', labels: { filter: (item) => !item.text.includes('(prévision)') } } },
        },
    });

    new Chart(document.getElementById('chartImpact'), {
        data: {
            labels: series.labels,
            datasets: [
                { type: 'bar', label: 'CO₂ évité (kg)', data: series.series.co2, backgroundColor: colors.green, borderRadius: 4, yAxisID: 'y' },
                { type: 'line', label: 'Eau économisée (L)', data: series.series.eau, borderColor: colors.blue, backgroundColor: colors.blue, tension: .35, yAxisID: 'y1' },
            ],
        },
        options: {
            maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            scales: {
                y: { beginAtZero: true, grid, title: { display: true, text: 'kg CO₂' } },
                y1: { beginAtZero: true, position: 'right', grid: { display: false }, title: { display: true, text: 'litres' } },
                x: { grid: { display: false } },
            },
            plugins: { legend: { position: 'bottom' } },
        },
    });

    const palette = [colors.orange, colors.blue, colors.green, colors.purple, '#9aa9a1', '#c9d3cd'];
    new Chart(document.getElementById('chartStatuts'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(repartition).map((k) => statutLabels[k] ?? k),
            datasets: [{ data: Object.values(repartition), backgroundColor: palette, borderWidth: 2, borderColor: '#fff' }],
        },
        options: { maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom' } } },
    });

    new Chart(document.getElementById('chartTypes'), {
        type: 'bar',
        data: {
            labels: Object.keys(topTypes),
            datasets: [{ label: 'Vêtements', data: Object.values(topTypes), backgroundColor: colors.green, borderRadius: 4 }],
        },
        options: {
            indexAxis: 'y', maintainAspectRatio: false,
            scales: { x: { beginAtZero: true, grid, ticks: { precision: 0 } }, y: { grid: { display: false } } },
            plugins: { legend: { display: false } },
        },
    });
})();
</script>
@endpush
