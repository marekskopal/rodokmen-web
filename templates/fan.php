<?php
/** @var Rodokmen\Site\View\View $v */
/** @var Rodokmen\Site\Data\Model\Person $person */
/** @var int $depth */
/** @var int $available */
/** @var string $svg */
/** @var int $count */
/** @var Rodokmen\Site\Data\Model\Person $home */
/** @var Rodokmen\Site\View\FanLegend $legend */
$base = '/vejir/' . rawurlencode($person->slug);
$presets = array_values(array_unique(array_filter([4, 6, 8, 10, $available], static fn (int $n): bool => $n <= $available && $n >= 1)));
sort($presets);
?>
<div class="explorer-head">
    <div>
        <p class="eyebrow"><a href="<?= $v->e($v->explorerUrl($person->slug)) ?>">Prohlížeč předků</a></p>
        <h1>Vějíř předků <span class="muted thin"><?= $v->e($person->name) ?></span></h1>
        <p class="muted"><?= $v->e($v->plural($count, 'známý předek', 'známí předci', 'známých předků')) ?>. Uprostřed <?= $v->e($person->name) ?>, každý prstenec je o generaci starší; vlevo otcova, vpravo matčina strana.</p>
    </div>
    <div class="explorer-actions">
        <a class="btn" href="/strom/<?= $v->e(rawurlencode($person->slug)) ?>">Celý strom předků</a>
        <a class="btn" href="/predci/<?= $v->e(rawurlencode($person->slug)) ?>/plakat">Plakát k tisku</a>
        <a class="btn" href="<?= $v->e($v->explorerUrl($person->slug)) ?>">Zpět na prohlížeč</a>
        <?php if ($person->slug !== $home->slug): ?>
            <a class="btn btn-ghost" href="/vejir/<?= $v->e(rawurlencode($home->slug)) ?>">Vějíř osoby <?= $v->e($home->name) ?></a>
        <?php endif ?>
    </div>
</div>

<div class="ft-toolbar" data-fan-toolbar>
    <span class="fan-depth">Generací:
        <?php foreach ($presets as $n): ?>
            <a class="chip<?= $n === $depth ? ' chip-btn on' : '' ?>" href="<?= $v->e($base . '?generace=' . $n) ?>"><?= $n ?></a>
        <?php endforeach ?>
    </span>
    <span class="fan-zoom">
        <button type="button" class="chip chip-btn" data-fan="in" aria-label="Přiblížit">+</button>
        <button type="button" class="chip chip-btn" data-fan="out" aria-label="Oddálit">−</button>
        <button type="button" class="chip chip-btn" data-fan="reset">Celý</button>
    </span>
    <input type="search" class="filter-text ft-find" placeholder="Najít ve vějíři…" data-fan="find" aria-label="Najít ve vějíři">
</div>

<div class="fan-stage" data-fan-stage>
    <?= $svg ?>
</div>

<aside class="legend fan-legend">
    <?php foreach ($legend->lines as $line): ?>
        <span><span class="fan-key" style="--c:<?= $v->e($line->color) ?>"></span>rod <?= $v->e($line->name) ?></span>
    <?php endforeach ?>
    <?php if ($legend->sides): ?>
        <span><span class="fan-key fan-key-sides" style="--c:<?= $v->e(Rodokmen\Site\View\FanChart::SideColors[0]) ?>;--c2:<?= $v->e(Rodokmen\Site\View\FanChart::SideColors[1]) ?>"></span>mimo rody (otcova / matčina strana)</span>
    <?php endif ?>
    <?php if ($legend->uncertain): ?>
        <span><span class="fan-key fan-key-weak"></span><em>pravděpodobné nebo jen z cizího rodokmenu</em></span>
    <?php endif ?>
    <?php if ($legend->unknown): ?>
        <span><span class="fan-key fan-key-empty"></span>zatím neznámý předek</span>
    <?php endif ?>
    <span class="muted">Od generace pradědů je každá starší generace světlejší. Kolečkem myši nebo prsty přibližujte, tažením posouvejte, klepnutím na jméno otevřete detail.</span>
</aside>
