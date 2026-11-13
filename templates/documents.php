<?php
/** @var Rodokmen\Site\View\View $v */
/** @var list<Rodokmen\Site\Data\Model\Document> $documents */
?>
<header class="page-head">
    <p class="eyebrow">Z rodinných alb</p>
    <h1>Fotografie a dokumenty</h1>
    <p class="lead">Fotky a dokumenty, které poslala rodina.</p>
</header>
<div class="gallery gallery-large">
    <?php foreach ($documents as $doc): ?>
        <figure>
            <a href="<?= $v->e($v->imageUrl($doc->img, true)) ?>" data-lightbox><img src="<?= $v->e($v->imageUrl($doc->img)) ?>" alt="<?= $v->e($doc->caption) ?>" loading="lazy"></a>
            <figcaption>
                <?= $v->e($doc->caption) ?>
                <?php if ($doc->people !== []): ?>
                    <span class="chips"><?php foreach ($doc->people as $slug): ?><span class="chip"><?= $v->personLink($slug) ?></span><?php endforeach ?></span>
                <?php endif ?>
            </figcaption>
        </figure>
    <?php endforeach ?>
</div>
<?php if ($documents === []): ?><p class="empty-note">Zatím žádné fotografie.</p><?php endif ?>
