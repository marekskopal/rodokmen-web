<?php
/** @var Rodokmen\Site\View\View $v */
/** @var Rodokmen\Site\Data\Model\Person $person */
/** @var int $depth */
/** @var int $available */
/** @var string $svg */
/** @var int $count */
/** @var Rodokmen\Site\Data\Model\Person $home */
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

<aside class="legend">
    <span><span class="fan-key" style="--c:#EFB45F;--o:1"></span>doloženo nebo od rodiny</span>
    <span><span class="fan-key" style="--c:#EFB45F;--o:.55"></span><em>pravděpodobné</em></span>
    <span><span class="fan-key fan-key-empty"></span>zatím neznámé</span>
    <span class="muted">Barva = rod. Kolečkem myši nebo prsty přibližujte, tažením posouvejte, klepnutím na jméno otevřete detail.</span>
</aside>
