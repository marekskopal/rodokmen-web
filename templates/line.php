<?php
/** @var Rodokmen\Site\View\View $v */
/** @var Rodokmen\Site\Data\Repository $repo */
/** @var Rodokmen\Site\View\LineSummary $summary */
/** @var array<int, list<Rodokmen\Site\Data\Model\Person>> $byGeneration */
/** @var Rodokmen\Site\Data\Model\Story|null $story */
$line = $summary->line;
?>
<article class="line-page" style="--c: <?= $v->e($line->color) ?>">
<header class="page-head line-head">
    <p class="eyebrow"><a href="/">Rody</a> · <?= $summary->isPaternal() ? 'po otci' : 'po matce' ?></p>
    <h1><?= $v->lineDot($line->k) ?><?= $v->e($line->name) ?></h1>
    <?php if ($line->desc !== ''): ?><p class="lead"><?= $v->e($line->desc) ?></p><?php endif ?>
    <p class="muted"><?= $v->e($v->plural($summary->count, 'osoba', 'osoby', 'osob')) ?> · <?= $v->e($v->plural($summary->generations, 'generace', 'generace', 'generací')) ?></p>
    <div class="hero-actions">
        <?php if ($summary->root !== null): ?>
            <a class="btn btn-primary" href="<?= $v->e($v->explorerUrl($summary->root->slug)) ?>">Předci rodu</a>
        <?php endif ?>
        <a class="btn" href="/generace?rod=<?= $v->e(rawurlencode($line->slug)) ?>">V přehledu generací</a>
    </div>
</header>

<?php if ($story !== null): ?>
    <?php require __DIR__ . '/partials/story-card.php' ?>
<?php endif ?>

<?php if ($summary->oldest !== null): ?>
    <p class="line-oldest"><span class="muted">Nejvzdálenější známý předek:</span>
        <?= $v->personLink($summary->oldest->slug) ?> <span class="muted"><?= $v->e($summary->oldest->y) ?></span></p>
<?php endif ?>
<?php if ($summary->places !== []): ?>
    <p class="chips"><?php foreach ($summary->places as $place): ?><a class="chip" href="/mapa#<?= $v->e(rawurlencode($place)) ?>"><?= $v->e($place) ?></a><?php endforeach ?></p>
<?php endif ?>

<?php foreach ($byGeneration as $gen => $people): ?>
    <section class="gen">
        <h2 class="gen-title">
            <span class="gen-num"><?= (int) $gen ?></span>
            <?= $v->e($v->generationName($gen)) ?>
            <span class="muted thin"><?= count($people) ?></span>
        </h2>
        <div class="table-wrap">
            <table class="people">
                <thead><tr><th class="col-name">Jméno</th><th class="col-years">Roky</th><th class="col-rel">Vztah</th><th class="col-places">Místa</th><th class="col-status">Údaj</th></tr></thead>
                <tbody>
                <?php foreach ($people as $p): ?>
                    <tr>
                        <td><?= $v->personLink($p->slug) ?></td>
                        <td class="nowrap"><?= $v->e($v->years($p)) ?></td>
                        <td class="muted"><?= $v->e($p->shortRel()) ?></td>
                        <td class="muted"><?= $v->e(implode(', ', array_slice($repo->placeNamesOf($p->slug), 0, 3))) ?></td>
                        <td><?= $v->status($p) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endforeach ?>
</article>
