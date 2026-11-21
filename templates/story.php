<?php
/** @var Rodokmen\Site\View\View $v */
/** @var Rodokmen\Site\Data\Model\Story $story */
/** @var string $html */
?>
<header class="page-head">
    <?php if ($story->line !== null): ?>
        <p class="eyebrow"><a href="/rod/<?= $v->e(rawurlencode($story->line->slug)) ?>"><?= $v->lineDot($story->line->k) ?>Rod <?= $v->e($story->line->name) ?></a></p>
    <?php endif ?>
    <h1><?= $v->e($story->title) ?></h1>
    <?php if ($story->lead !== ''): ?><p class="lead"><?= $v->e($story->lead) ?></p><?php endif ?>
</header>
<div class="prose" data-anchor-headings>
    <?= $html ?>
</div>
<p class="next-step"><a href="/zajimavosti">Další zajímavosti z rodokmenu →</a></p>
