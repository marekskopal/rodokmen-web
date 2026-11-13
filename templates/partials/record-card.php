<?php
/** @var Rodokmen\Site\View\View $v */
/** @var Rodokmen\Site\Data\Model\Record $r */
$open ??= false;
?>
<article class="record" id="z-<?= $v->e($r->key) ?>">
    <header class="record-head">
        <span class="kind kind-<?= $v->e($r->kind) ?>"><?= $v->e($v->kindLabel($r->kind)) ?></span>
        <h3><a href="/zapis/<?= $v->e(rawurlencode($r->key)) ?>"><?= $v->e($r->title) ?></a></h3>
        <span class="muted"><?= $v->e($r->date) ?></span>
    </header>
    <?php if ($r->img): ?>
        <figure class="scan">
            <a href="<?= $v->e($v->imageUrl($r->key, true)) ?>" data-lightbox>
                <img src="<?= $v->e($v->imageUrl($r->key)) ?>" alt="<?= $v->e($r->title) ?>" loading="lazy">
                <?php foreach ($r->boxes as $box): ?>
                    <span class="scan-box" style="left: <?= $box->x0 * 100 ?>%; top: <?= $box->y0 * 100 ?>%; width: <?= ($box->x1 - $box->x0) * 100 ?>%; height: <?= ($box->y1 - $box->y0) * 100 ?>%"></span>
                <?php endforeach ?>
            </a>
        </figure>
    <?php endif ?>
    <?php if ($r->summary !== ''): ?>
        <p class="record-summary"><?= $v->e($r->summary) ?></p>
    <?php endif ?>
    <?php if ($r->cs !== '' || $r->orig !== ''): ?>
        <details class="record-text" <?= $open ? 'open' : '' ?>>
            <summary>Přepis a překlad</summary>
            <div class="texts">
                <?php if ($r->orig !== ''): ?>
                    <div><h4>Přepis originálu</h4><p class="transcript"><?= nl2br($v->e($r->orig)) ?></p></div>
                <?php endif ?>
                <?php if ($r->cs !== ''): ?>
                    <div><h4>Český překlad</h4><p><?= nl2br($v->e($r->cs)) ?></p></div>
                <?php endif ?>
            </div>
        </details>
    <?php endif ?>
    <footer class="record-source muted">
        <?= $v->e($r->archive) ?>, <?= $v->e($r->vdesc) ?><?php if ($r->scan > 0): ?>, snímek <?= $r->scan ?><?php endif ?>
        <?php if ($r->url !== ''): ?> · <a href="<?= $v->e($r->url) ?>" target="_blank" rel="noopener noreferrer">originál v archivu ↗</a><?php endif ?>
    </footer>
</article>
