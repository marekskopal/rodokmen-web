<?php
/** @var Rodokmen\Site\View\View $v */
/** @var list<Rodokmen\Site\Data\Model\Issue> $issues */
/** @var list<Rodokmen\Site\Data\Model\Hypothesis> $hypotheses */
/** @var string|null $archive */
$open = array_values(array_filter($issues, static fn (Rodokmen\Site\Data\Model\Issue $i): bool => !$i->isSolved()));
$solved = array_values(array_filter($issues, static fn (Rodokmen\Site\Data\Model\Issue $i): bool => $i->isSolved()));
?>
<header class="page-head">
    <p class="eyebrow">Rozpracováno</p>
    <h1>Co ještě hledáme</h1>
    <p class="lead">Otevřené otázky, hypotézy a to, co by šlo zjistit jen v archivu. Víte-li něco, dejte vědět.</p>
</header>
<div class="prose">
    <?php if ($open !== []): ?>
        <h2>Otevřené otázky</h2>
        <?php foreach ($open as $i): ?><div class="card qa"><h3><?= $v->e($i->title) ?></h3><p><?= $v->e($i->text) ?></p></div><?php endforeach ?>
    <?php endif ?>
    <?php if ($hypotheses !== []): ?>
        <h2>Hypotézy</h2>
        <?php foreach ($hypotheses as $h): ?><div class="card qa qa-hyp"><h3><?= $v->e($h->title) ?></h3><p><?= $v->e($h->text) ?></p></div><?php endforeach ?>
    <?php endif ?>
    <?php if ($archive !== null): ?>
        <h2>Jen v archivu nebo na matrice</h2>
        <?= $v->markdown(preg_replace('/^#\s.*$/m', '', $archive) ?? '') ?>
    <?php endif ?>
    <?php if ($solved !== []): ?>
        <details class="solved"><summary>Vyřešené otázky (<?= count($solved) ?>)</summary>
            <?php foreach ($solved as $i): ?><div class="qa"><h3><?= $v->e($i->title) ?></h3><p><?= $v->e($i->text) ?></p></div><?php endforeach ?>
        </details>
    <?php endif ?>
</div>
