<?php
/** @var Rodokmen\Site\View\View $v */
/** @var Rodokmen\Site\Data\Repository $repo */
/** @var Rodokmen\Site\Data\Model\Person $home */
/** @var list<array{line: Rodokmen\Site\Data\Model\Line, root: Rodokmen\Site\Data\Model\Person|null, count: int, generations: int, oldest: Rodokmen\Site\Data\Model\Person|null, places: list<string>}> $cards */
/** @var Rodokmen\Site\Data\Model\Stats $stats */
/** @var list<string> $stories */
$sides = [
    'Po otci' => array_values(array_filter($cards, static fn ($c) => $c['line']->k <= 11)),
    'Po matce' => array_values(array_filter($cards, static fn ($c) => $c['line']->k >= 12)),
];
$father = $repo->person($home->father);
$mother = $repo->person($home->mother);
?>
<section class="hero">
    <div class="hero-text">
        <p class="eyebrow">Rodinný rodokmen</p>
        <h1><?= $v->e($repo->title()) ?></h1>
        <p class="lead">
            <?= $stats->ancestors ?> předků v <?= $stats->generations ?> generacích,
            <?= $stats->documented ?> z nich doložených zápisem v matrice.
            Vše dohledané v digitalizovaných matrikách státních archivů.
        </p>
        <div class="hero-actions">
            <a class="btn btn-primary" href="<?= $v->e($v->explorerUrl($home->slug)) ?>">Procházet předky</a>
            <a class="btn" href="/generace">Všechny generace</a>
        </div>
    </div>
    <div class="hero-tree" aria-label="Výchozí osoba">
        <div class="mini-row"><?php $p = $father; $role = 'otec'; require __DIR__ . '/partials/person-card.php' ?><?php $p = $mother; $role = 'matka'; require __DIR__ . '/partials/person-card.php' ?></div>
        <div class="mini-link"></div>
        <div class="mini-row"><?php $p = $home; $role = 'výchozí osoba'; $size = 'pcard-focus'; require __DIR__ . '/partials/person-card.php'; $size = '' ?></div>
    </div>
</section>

<section class="stats">
    <div><strong><?= $stats->ancestors ?></strong><span>předků</span></div>
    <div><strong><?= $stats->documented ?></strong><span>doloženo matrikou</span></div>
    <div><strong><?= $stats->records ?></strong><span>zápisů z matrik</span></div>
    <div><strong><?= $stats->generations ?></strong><span>generací</span></div>
</section>

<?php foreach ($sides as $sideTitle => $sideCards): if ($sideCards === []) { continue; } ?>
<section class="section">
    <h2 class="section-title"><?= $v->e($sideTitle) ?></h2>
    <div class="lines">
        <?php foreach ($sideCards as $card): $line = $card['line']; ?>
            <article class="line-card" style="--c: <?= $v->e($line->color) ?>">
                <header>
                    <h3><?= $v->e($line->name) ?></h3>
                    <span class="line-meta"><?= $v->e($v->plural($card['count'], 'osoba', 'osoby', 'osob')) ?> · <?= $v->e($v->plural($card['generations'], 'generace', 'generace', 'generací')) ?></span>
                </header>
                <p><?= $v->e($line->desc) ?></p>
                <?php if ($card['oldest'] !== null): ?>
                    <p class="line-oldest"><span class="muted">Nejvzdálenější známý předek:</span>
                        <a href="<?= $v->e($v->personUrl($card['oldest']->slug)) ?>"><?= $v->e($card['oldest']->name) ?></a>
                        <span class="muted"><?= $v->e($card['oldest']->y) ?></span></p>
                <?php endif ?>
                <?php if ($card['places'] !== []): ?>
                    <p class="chips"><?php foreach ($card['places'] as $place): ?><span class="chip"><?= $v->e($place) ?></span><?php endforeach ?></p>
                <?php endif ?>
                <footer>
                    <?php if ($card['root'] !== null): ?>
                        <a href="<?= $v->e($v->explorerUrl($card['root']->slug)) ?>">Předci rodu →</a>
                    <?php endif ?>
                    <a href="/generace?rod=<?= $v->e($line->slug) ?>" class="muted">seznam</a>
                </footer>
            </article>
        <?php endforeach ?>
    </div>
</section>
<?php endforeach ?>

<?php if ($stories !== []): ?>
<section class="section">
    <h2 class="section-title">Zajímavosti z matrik</h2>
    <ul class="stories">
        <?php foreach ($stories as $story): ?>
            <li><a href="/zajimavosti#<?= $v->e(rawurlencode($story)) ?>"><?= $v->e($story) ?></a></li>
        <?php endforeach ?>
    </ul>
</section>
<?php endif ?>
