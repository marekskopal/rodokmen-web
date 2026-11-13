<?php
/** @var Rodokmen\Site\View\View $v */
/** @var Rodokmen\Site\Data\Model\Person|null $p */
/** @var string|null $role */
/** @var bool|null $explore link to the explorer instead of the person page */
$explore ??= true;
$size ??= '';
if ($p === null): ?>
    <div class="pcard pcard-empty <?= $v->e($size) ?>">
        <?php if (!empty($role)): ?><span class="pcard-role"><?= $v->e($role) ?></span><?php endif ?>
        <span class="pcard-name">neznámý</span>
    </div>
<?php else: ?>
    <a class="pcard <?= $v->e($size) ?> st-<?= $v->e($p->st) ?>"
       href="<?= $v->e($explore ? $v->explorerUrl($p->slug) : $v->personUrl($p->slug)) ?>"
       style="--c: <?= $v->e($repo->lineColor($p->line)) ?>">
        <?php if (!empty($role)): ?><span class="pcard-role"><?= $v->e($role) ?></span><?php endif ?>
        <span class="pcard-name"><?= $v->e($p->name) ?></span>
        <span class="pcard-years"><?= $v->statusMark($p->st) ?><?= $v->e($v->years($p)) ?></span>
    </a>
<?php endif;
