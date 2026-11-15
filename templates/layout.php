<?php
/** @var Rodokmen\Site\View\View $v */
/** @var Rodokmen\Site\Data\Repository $repo */
/** @var string $content */
$nav ??= '';
$bare ??= false;
$wide ??= false;
$siteTitle = $repo->title();
$items = [
    'home' => ['/', 'Úvod'],
    'explorer' => ['/predci', 'Předci'],
    'generations' => ['/generace', 'Generace'],
    'map' => ['/mapa', 'Mapa'],
    'records' => ['/zapisy', 'Zápisy'],
    'documents' => ['/dokumenty', 'Fotografie'],
    'notes' => ['/zajimavosti', 'Zajímavosti'],
];
?><!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $v->e(($title ?? '') !== $siteTitle && ($title ?? '') !== '' ? $title . ' · ' . $siteTitle : $siteTitle) ?></title>
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/app.css?v=11">
    <?php if (($nav === 'map') || !empty($needsMap)): ?>
        <?php /* Built by `pnpm build` from package.json; the query changes with the Leaflet version (cache). */ ?>
        <link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css?v=1.9.4">
        <script src="/assets/vendor/leaflet/leaflet.js?v=1.9.4" defer></script>
    <?php endif ?>
    <script src="/assets/app.js?v=11" defer></script>
    <script src="/assets/viewer.js?v=1" defer></script>
    <script src="/assets/theme.js?v=1"></script>
</head>
<body class="<?= $bare ? 'bare' : '' ?>">
<?php if (!$bare): ?>
<header class="top">
    <div class="top-inner">
        <a class="brand" href="/">
            <svg class="brand-mark" viewBox="0 0 32 32" aria-hidden="true"><path d="M16 29V15M16 15c0-5 3-8 8-9M16 15c0-5-3-8-8-9M16 20c0-3 2-5 5-6M16 20c0-3-2-5-5-6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
            <span><?= $v->e($siteTitle) ?></span>
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="mainnav" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
        <nav id="mainnav" class="nav">
            <?php foreach ($items as $key => [$href, $label]): ?>
                <a href="<?= $href ?>" class="<?= $nav === $key ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach ?>
            <a href="/hledat" class="nav-search <?= $nav === 'search' ? 'active' : '' ?>" aria-label="Hledat">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="m15.5 15.5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <span>Hledat</span>
            </a>
        </nav>
    </div>
</header>
<?php endif ?>
<main class="<?= $bare ? '' : 'page' ?> <?= $wide ? 'page-wide' : '' ?>">
<?= $content ?>
</main>
<?php if (!$bare): ?>
<footer class="foot">
    <div class="foot-inner">
        <p><?= $v->e($repo->siteConfig()->footer) ?></p>
        <p class="foot-disclaimer">
            Údaje na tomto webu jsme dohledávali v pramenech, ale mohou obsahovat chyby, být nepřesné nebo neúplné.
            <?php if ($repo->siteConfig()->contactEmail !== ''): ?>
                Máte-li cokoli na doplnění nebo opravu, napište<?= $repo->siteConfig()->contactName !== '' ? ' ' . $v->e($repo->siteConfig()->contactName) : '' ?>
                na <a href="mailto:<?= $v->e($repo->siteConfig()->contactEmail) ?>"><?= $v->e($repo->siteConfig()->contactEmail) ?></a>.
            <?php endif ?>
        </p>
        <p class="foot-links">
            <a href="/denik">Deník hledání</a>
            <a href="/hledame">Co ještě hledáme</a>
            <a href="/export/rodokmen.ged" download title="Rodokmen pro jiné genealogické programy (GEDCOM)">Stáhnout GEDCOM</a>
            <button type="button" class="linklike" data-theme-toggle>Světlý / tmavý režim</button>
            <form method="post" action="/odhlasit" class="inline"><button type="submit" class="linklike">Odhlásit</button></form>
        </p>
    </div>
</footer>
<?php endif ?>
<div class="viewer" hidden role="dialog" aria-modal="true" aria-label="Prohlížeč obrázku">
    <div class="viewer-stage"><img class="viewer-img" alt="" draggable="false"></div>
    <p class="viewer-status" role="status" hidden></p>
    <div class="viewer-bar">
        <button type="button" data-viewer="out" aria-label="Oddálit" title="Oddálit (−)">−</button>
        <span class="viewer-zoom" aria-hidden="true"></span>
        <button type="button" data-viewer="in" aria-label="Přiblížit" title="Přiblížit (+)">+</button>
        <button type="button" data-viewer="fit" aria-label="Přizpůsobit oknu" title="Přizpůsobit oknu (0)">⤢</button>
        <a data-viewer="orig" href="#" target="_blank" rel="noopener" aria-label="Otevřít originál v novém okně" title="Otevřít originál">↗</a>
        <button type="button" data-viewer="close" aria-label="Zavřít" title="Zavřít (Esc)">×</button>
    </div>
</div>
</body>
</html>
