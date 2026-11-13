<?php
/** @var Rodokmen\Site\View\View $v */
/** @var list<Rodokmen\Site\Data\Model\Record> $records */
$kinds = array_values(array_unique(array_map(static fn (Rodokmen\Site\Data\Model\Record $r): string => $r->kind, $records)));
?>
<header class="page-head">
    <p class="eyebrow">Prameny</p>
    <h1>Zápisy z matrik</h1>
    <p class="lead"><?= count($records) ?> zápisů, ze kterých rodokmen vychází. U každého je výřez snímku, přepis a překlad.</p>
</header>

<div class="filters" data-filter-root>
    <input type="search" class="filter-text" placeholder="Filtrovat jméno, obec, rok…" data-filter-text aria-label="Filtrovat">
    <div class="filter-chips" role="group" aria-label="Druh zápisu">
        <button type="button" class="chip chip-btn on" data-filter-kind="">Vše</button>
        <?php foreach ($kinds as $kind): ?>
            <button type="button" class="chip chip-btn" data-filter-kind="<?= $v->e($kind) ?>"><?= $v->e($v->kindLabel($kind)) ?></button>
        <?php endforeach ?>
    </div>
</div>

<div class="record-grid">
    <?php foreach ($records as $r): ?>
        <a class="record-tile" href="/zapis/<?= $v->e(rawurlencode($r->key)) ?>" data-kind="<?= $v->e($r->kind) ?>"
           data-text="<?= $v->e(mb_strtolower($r->title . ' ' . $r->date . ' ' . $r->summary . ' ' . $r->vdesc)) ?>">
            <?php if ($r->img): ?>
                <span class="record-thumb"><img src="<?= $v->e($v->imageUrl($r->key)) ?>" alt="" loading="lazy"></span>
            <?php endif ?>
            <span class="record-tile-body">
                <span class="kind kind-<?= $v->e($r->kind) ?>"><?= $v->e($v->kindLabel($r->kind)) ?></span>
                <strong><?= $v->e($r->title) ?></strong>
                <span class="muted"><?= $v->e($r->date) ?> · <?= $v->e($r->vdesc) ?></span>
            </span>
        </a>
    <?php endforeach ?>
</div>
<p class="empty-note" data-filter-empty hidden>Nic neodpovídá filtru.</p>
