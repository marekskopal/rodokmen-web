<?php
/** @var Rodokmen\Site\View\View $v */
/** @var Rodokmen\Site\Data\Repository $repo */
/** @var Rodokmen\Site\Data\Model\Person $home */
/** @var list<Rodokmen\Site\View\LineSummary> $cards */
/** @var Rodokmen\Site\Data\Model\Story|null $story */
/** @var list<Rodokmen\Site\Data\Model\Story> $otherStories */
/** @var array<string, Rodokmen\Site\Data\Model\Story> $lineStories */
/** @var Rodokmen\Site\Data\Model\Stats $stats */
/** @var list<string> $stories */
$sides = [
    'Po otci' => array_values(array_filter($cards, static fn ($c) => $c->isPaternal())),
    'Po matce' => array_values(array_filter($cards, static fn ($c) => !$c->isPaternal())),
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

<?php if ($story !== null || $otherStories !== []): ?>
<section class="section">
    <?php if ($story !== null): ?>
        <?php require __DIR__ . '/partials/story-card.php' ?>
    <?php endif ?>
    <?php if ($otherStories !== []): ?>
        <h2 class="section-title"><?= $story !== null ? 'Další příběhy rodů' : 'Příběhy rodů' ?></h2>
        <ul class="story-list">
            <?php foreach ($otherStories as $other): ?>
                <li style="--c: <?= $v->e($other->line->color ?? 'var(--accent)') ?>">
                    <a href="<?= $v->e($other->url()) ?>"><?= $v->e($other->title) ?></a>
                    <?php if ($other->lead !== ''): ?><span class="muted"><?= $v->e($other->lead) ?></span><?php endif ?>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</section>
<?php endif ?>

<?php foreach ($sides as $sideTitle => $sideCards): if ($sideCards === []) { continue; } ?>
<section class="section">
    <h2 class="section-title"><?= $v->e($sideTitle) ?></h2>
    <div class="lines">
        <?php foreach ($sideCards as $card): $line = $card->line; $lineUrl = '/rod/' . rawurlencode($line->slug); ?>
            <article class="line-card" style="--c: <?= $v->e($line->color) ?>">
                <header>
                    <h3><a href="<?= $v->e($lineUrl) ?>"><?= $v->e($line->name) ?></a></h3>
                    <span class="line-meta"><?= $v->e($v->plural($card->count, 'osoba', 'osoby', 'osob')) ?> · <?= $v->e($v->plural($card->generations, 'generace', 'generace', 'generací')) ?></span>
                </header>
                <p><?= $v->e($line->desc) ?></p>
                <?php if ($card->oldest !== null): ?>
                    <p class="line-oldest"><span class="muted">Nejvzdálenější známý předek:</span>
                        <a href="<?= $v->e($v->personUrl($card->oldest->slug)) ?>"><?= $v->e($card->oldest->name) ?></a>
                        <span class="muted"><?= $v->e($card->oldest->y) ?></span></p>
                <?php endif ?>
                <?php if ($card->places !== []): ?>
                    <p class="chips"><?php foreach (array_slice($card->places, 0, 5) as $place): ?><span class="chip"><?= $v->e($place) ?></span><?php endforeach ?></p>
                <?php endif ?>
                <?php if (isset($lineStories[$line->slug])): ?>
                    <p class="line-story"><a href="<?= $v->e($lineStories[$line->slug]->url()) ?>"><?= $v->e($lineStories[$line->slug]->title) ?> →</a></p>
                <?php endif ?>
                <footer>
                    <a href="<?= $v->e($lineUrl) ?>">O rodu →</a>
                    <?php if ($card->root !== null): ?>
                        <a href="<?= $v->e($v->explorerUrl($card->root->slug)) ?>" class="muted">předci</a>
                    <?php endif ?>
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
        <?php foreach ($stories as $storyTitle): ?>
            <li><a href="/zajimavosti#<?= $v->e(rawurlencode($storyTitle)) ?>"><?= $v->e($storyTitle) ?></a></li>
        <?php endforeach ?>
    </ul>
</section>
<?php endif ?>
