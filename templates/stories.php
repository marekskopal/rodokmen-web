<?php
/** @var Rodokmen\Site\View\View $v */
/** @var list<Rodokmen\Site\Data\Model\Story> $stories */
?>
<header class="page-head">
    <p class="eyebrow">Rodokmen</p>
    <h1>Příběhy rodů</h1>
    <p class="lead">Jak žily jednotlivé rody – od nejstaršího známého předka k dnešku.</p>
</header>
<div class="story-cards">
    <?php foreach ($stories as $story): ?>
        <?php require __DIR__ . '/partials/story-card.php' ?>
    <?php endforeach ?>
</div>
