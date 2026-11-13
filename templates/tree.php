<?php
/** @var Rodokmen\Site\View\View $v */
/** @var Rodokmen\Site\Data\Repository $repo */
/** @var Rodokmen\Site\Data\Model\Person $person */
/** @var int $count */
/** @var Rodokmen\Site\Data\Model\Person $home */

/**
 * Renders one node and, recursively, its known parents. Unknown parents are left out to keep
 * the tree compact; $seen guards against loops (pedigree collapse) on the current branch.
 *
 * @param array<string, true> $seen
 */
$node = static function (string $slug, int $depth, array $seen) use (&$node, $v, $repo): string {
    $p = $repo->person($slug);
    if ($p === null) {
        return '';
    }

    $seen[$slug] = true;
    $parents = [];
    foreach ([$p->father, $p->mother] as $parentSlug) {
        if ($parentSlug !== null && !isset($seen[$parentSlug]) && $depth < 30) {
            $parents[] = $parentSlug;
        }
    }

    $html = '<li class="ft-node' . ($parents !== [] ? ' ft-has' : '') . '">';
    $html .= '<div class="ft-card st-' . $v->e($p->st) . ' ft-' . ($p->isFemale() ? 'f' : 'm') . '" style="--c:' . $v->e($repo->lineColor($p->line)) . '">';
    $html .= '<a href="' . $v->e($v->personUrl($p->slug)) . '" class="ft-name" title="' . $v->e($p->name) . '">' . $v->e($p->name) . '</a>';
    $html .= '<span class="ft-years">' . $v->statusMark($p->st) . $v->e($v->years($p)) . '</span>';
    if ($parents !== []) {
        $html .= '<button type="button" class="ft-toggle" aria-expanded="true" title="Sbalit / rozbalit předky"></button>';
    }
    $html .= '</div>';

    if ($parents !== []) {
        $html .= '<ul>';
        foreach ($parents as $parentSlug) {
            $html .= $node($parentSlug, $depth + 1, $seen);
        }
        $html .= '</ul>';
    }

    return $html . '</li>';
};
?>
<div class="explorer-head">
    <div>
        <p class="eyebrow"><a href="<?= $v->e($v->explorerUrl($person->slug)) ?>">Prohlížeč předků</a></p>
        <h1>Celý strom předků <span class="muted thin"><?= $v->e($person->name) ?></span></h1>
        <p class="muted"><?= $v->e($v->plural($count, 'známý předek', 'známí předci', 'známých předků')) ?>. Strom roste zleva doprava: vlevo <?= $v->e($person->name) ?>, vpravo nejstarší generace. Otec je vždy na stejném řádku jako dítě, matka pod ním.</p>
    </div>
    <div class="explorer-actions">
        <a class="btn" href="<?= $v->e($v->explorerUrl($person->slug)) ?>">Zpět na prohlížeč</a>
        <a class="btn" href="/vejir/<?= $v->e(rawurlencode($person->slug)) ?>">Vějíř předků</a>
        <?php if ($person->slug !== $home->slug): ?>
            <a class="btn btn-ghost" href="/strom/<?= $v->e(rawurlencode($home->slug)) ?>">Strom osoby <?= $v->e($home->name) ?></a>
        <?php endif ?>
    </div>
</div>

<div class="ft-toolbar" data-ft-toolbar>
    <button type="button" class="chip chip-btn" data-ft="expand">Rozbalit vše</button>
    <button type="button" class="chip chip-btn" data-ft="depth" data-depth="3">Jen 3 generace</button>
    <button type="button" class="chip chip-btn" data-ft="depth" data-depth="5">5 generací</button>
    <label class="ft-zoom">Velikost <input type="range" min="40" max="120" value="100" step="5" data-ft="zoom" aria-label="Velikost stromu"></label>
    <input type="search" class="filter-text ft-find" placeholder="Najít ve stromu…" data-ft="find" aria-label="Najít ve stromu">
</div>

<div class="ft-scroll" data-ft-scroll>
    <ul class="ft" data-ft-tree>
        <?= $node($person->slug, 0, []) ?>
    </ul>
</div>

<aside class="legend">
    <span><?= $v->statusMark('doc') ?>doloženo matrikou</span>
    <span><?= $v->statusMark('prob') ?>pravděpodobné</span>
    <span><?= $v->statusMark('fam') ?>údaj od rodiny</span>
    <span><?= $v->statusMark('mh') ?>jen z cizího rodokmenu</span>
    <span class="muted">Barva proužku = rod. Šipkou u osoby sbalíte její předky, kliknutím na jméno otevřete detail.</span>
</aside>
