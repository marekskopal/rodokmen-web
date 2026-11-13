<?php
/** @var Rodokmen\Site\View\View $v */
/** @var string $html */
?>
<header class="page-head">
    <h1><?= $v->e($title) ?></h1>
</header>
<div class="prose <?= !empty($wide) ? 'prose-wide' : '' ?>" data-anchor-headings>
    <?= $html ?>
</div>
