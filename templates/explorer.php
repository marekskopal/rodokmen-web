<?php
/** @var Rodokmen\Site\View\View $v */
/** @var Rodokmen\Site\Data\Repository $repo */
/** @var Rodokmen\Site\Data\Model\Person $person */
/** @var list<list<string|null>> $generations */
/** @var list<int> $deeper */
/** @var list<string>|null $path */
/** @var Rodokmen\Site\Data\Model\Person $home */
$roles = [1 => ['otec', 'matka'], 2 => ['děd', 'babička'], 3 => ['praděd', 'prababička']];
$child = $repo->person($person->child);
?>
<div class="explorer-head">
    <div>
        <p class="eyebrow">Prohlížeč předků</p>
        <h1><?= $v->e($person->name) ?> <span class="muted thin"><?= $v->e($v->years($person)) ?></span></h1>
        <?php if ($path !== null && count($path) > 1): ?>
            <nav class="trail" aria-label="Cesta k výchozí osobě">
                <?php foreach (array_reverse($path) as $i => $slug): $p = $repo->person($slug); ?>
                    <?php if ($i > 0): ?><span class="trail-sep">›</span><?php endif ?>
                    <a href="<?= $v->e($v->explorerUrl($slug)) ?>" class="<?= $slug === $person->slug ? 'current' : '' ?>"><?= $v->e($p->name ?? $slug) ?></a>
                <?php endforeach ?>
            </nav>
        <?php endif ?>
    </div>
    <div class="explorer-actions">
        <a class="btn btn-primary" href="/strom/<?= $v->e(rawurlencode($person->slug)) ?>">Celý strom předků</a>
        <a class="btn" href="/vejir/<?= $v->e(rawurlencode($person->slug)) ?>">Vějíř předků</a>
        <a class="btn" href="<?= $v->e($v->personUrl($person->slug)) ?>">Detail osoby</a>
        <?php if ($person->slug !== $home->slug): ?>
            <a class="btn btn-ghost" href="<?= $v->e($v->explorerUrl($home->slug)) ?>">Zpět na <?= $v->e($home->name) ?></a>
        <?php endif ?>
    </div>
</div>

<div class="hourglass" style="--cols: <?= count($generations[array_key_last($generations)]) ?>">
    <?php for ($level = count($generations) - 1; $level >= 1; $level--): $row = $generations[$level]; ?>
        <div class="hg-row hg-level-<?= $level ?>" style="--n: <?= count($row) ?>">
            <?php foreach ($row as $i => $slug): ?>
                <div class="hg-cell">
                    <?php
                    $p = $repo->person($slug);
                    $role = $roles[$level][$i % 2] ?? '';
                    $size = $level >= 3 ? 'pcard-small' : '';
                    require __DIR__ . '/partials/person-card.php';
                    $size = '';
                    if ($level === count($generations) - 1 && ($deeper[$i] ?? 0) > 0): ?>
                        <a class="hg-more" href="<?= $v->e($v->explorerUrl($slug)) ?>">+ <?= (int) $deeper[$i] ?> dalších ↑</a>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    <?php endfor ?>
    <div class="hg-row hg-focus" style="--n: 1">
        <div class="hg-cell">
            <?php $p = $person; $role = $person->rel; $size = 'pcard-focus'; $explore = false; require __DIR__ . '/partials/person-card.php'; $explore = true; $size = '' ?>
        </div>
    </div>
    <?php if ($child !== null): ?>
        <div class="hg-row hg-child" style="--n: 1">
            <div class="hg-cell">
                <?php $p = $child; $role = $child->isFemale() ? 'dcera' : 'syn'; require __DIR__ . '/partials/person-card.php' ?>
            </div>
        </div>
    <?php endif ?>
</div>

<?php if (count($generations) === 1): ?>
    <p class="empty-note">O předcích této osoby zatím nic nevíme.</p>
<?php endif ?>

<aside class="legend">
    <span><?= $v->statusMark('doc') ?>doloženo matrikou</span>
    <span><?= $v->statusMark('prob') ?>pravděpodobné</span>
    <span><?= $v->statusMark('fam') ?>údaj od rodiny</span>
    <span><?= $v->statusMark('mh') ?>jen z cizího rodokmenu</span>
    <span class="muted">Barva proužku = rod. Klikněte na kohokoli a posunete se o generaci výš.</span>
</aside>
