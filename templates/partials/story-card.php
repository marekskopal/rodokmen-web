<?php
/** @var Rodokmen\Site\View\View $v */
/** @var Rodokmen\Site\Data\Model\Story $story */
?>
<a class="story-card" href="/pribeh" style="--c: <?= $v->e($story->line->color ?? 'var(--accent)') ?>">
    <span class="eyebrow">Příběh rodu</span>
    <strong class="story-card-title"><?= $v->e($story->title) ?></strong>
    <?php if ($story->lead !== ''): ?><span class="story-card-lead"><?= $v->e($story->lead) ?></span><?php endif ?>
    <span class="story-card-more"><?= $v->e($v->storyButton()) ?> →</span>
</a>
