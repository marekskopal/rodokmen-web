<?php
/** @var Rodokmen\Site\View\View $v */
/** @var Rodokmen\Site\Data\Model\Record $record */
$r = $record;
$open = true;
?>
<header class="page-head">
    <p class="eyebrow"><a href="/zapisy">Zápisy z matrik</a></p>
    <h1><?= $v->e($r->title) ?></h1>
    <?php if ($r->people !== []): ?>
        <p class="chips">
            <?php foreach ($r->people as $slug): ?><span class="chip"><?= $v->personLink($slug) ?></span><?php endforeach ?>
        </p>
    <?php endif ?>
</header>
<div class="record-single">
    <?php require __DIR__ . '/partials/record-card.php' ?>
    <dl class="meta card">
        <dt>Archiv</dt><dd><?= $v->e($r->archive) ?></dd>
        <dt>Kniha</dt><dd><?= $v->e($r->vdesc) ?></dd>
        <dt>Signatura</dt><dd><?= $v->e($r->volume) ?></dd>
        <?php if ($r->scan > 0): ?><dt>Snímek</dt><dd><?= $r->scan ?></dd><?php endif ?>
        <dt>Portál</dt><dd><?= $v->e($r->portal) ?></dd>
    </dl>
</div>
