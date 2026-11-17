<?php
/** @var Rodokmen\Site\View\View $v */
/** @var Rodokmen\Site\Data\Repository $repo */
/** @var Rodokmen\Site\Data\Model\Person $person */
/** @var Rodokmen\Site\View\Poster\PosterOptions $options */
/** @var string $heading */
/** @var int $available */
/** @var int $readable */
/** @var bool $auto */
/** @var Rodokmen\Site\View\Poster\PosterSheet $sheet */
use Rodokmen\Site\View\Poster\Orientation;
use Rodokmen\Site\View\Poster\PaperFormat;
use Rodokmen\Site\View\Poster\Poster;
use Rodokmen\Site\View\Poster\PosterLayout;
use Rodokmen\Site\View\Poster\PosterOptions;
use Rodokmen\Site\View\Poster\PosterStyle;

$points = $sheet->smallestPoints();
?>
<header class="page-head poster-head">
    <p class="eyebrow"><a href="<?= $v->e($v->explorerUrl($person->slug)) ?>">Prohlížeč předků</a></p>
    <h1>Plakát předků <span class="muted thin"><?= $v->e($person->name) ?></span></h1>
    <p class="lead">Předci na jednom velkém listu papíru – k vytištění, nebo k uložení jako PDF pro tiskárnu.</p>
</header>

<form class="filters poster-toolbar" method="get" data-live-form>
    <div class="poster-fields">
        <label class="field"><span class="field-label">Rozvržení</span>
            <select name="rozvrzeni">
                <?php foreach (PosterLayout::cases() as $layout): ?>
                    <option value="<?= $layout->value ?>" <?= $layout === $options->layout ? 'selected' : '' ?>><?= $v->e($layout->label()) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <label class="field"><span class="field-label">Papír</span>
            <select name="format">
                <?php foreach (PaperFormat::cases() as $format): ?>
                    <option value="<?= $format->value ?>" <?= $format === $options->format ? 'selected' : '' ?>><?= $v->e($format->label()) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <label class="field"><span class="field-label">Orientace</span>
            <select name="orientace">
                <?php foreach (Orientation::cases() as $orientation): ?>
                    <option value="<?= $orientation->value ?>" <?= $orientation === $options->orientation ? 'selected' : '' ?>><?= $v->e($orientation->label()) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <label class="field"><span class="field-label">Generací</span>
            <select name="generace" data-live="depth">
                <option value="" <?= $auto ? 'selected' : '' ?>>doporučeno (<?= $readable ?>)</option>
                <?php for ($i = 1; $i <= max(1, $available); $i++): ?>
                    <option value="<?= $i ?>" <?= !$auto && $i === $options->depth ? 'selected' : '' ?>><?= $i ?></option>
                <?php endfor ?>
            </select>
        </label>
        <?php if (count(PosterStyle::cases()) > 1): ?>
            <label class="field"><span class="field-label">Styl</span>
                <select name="styl">
                    <?php foreach (PosterStyle::cases() as $style): ?>
                        <option value="<?= $style->value ?>" <?= $style === $options->style ? 'selected' : '' ?>><?= $v->e($style->label()) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
        <?php endif ?>
        <label class="field poster-title-field"><span class="field-label">Nadpis</span>
            <input type="text" class="filter-text" name="nadpis" value="<?= $v->e($options->title) ?>" maxlength="<?= PosterOptions::MaxTitleLength ?>" placeholder="<?= $v->e($heading) ?>">
        </label>
    </div>
    <div class="poster-actions">
        <p class="poster-summary" data-live="summary" aria-live="polite">
            <?= $v->e($options->format->label() . ' ' . $options->orientation->label()) ?>
            (<?= $options->width() ?> × <?= $options->height() ?> mm), nejmenší písmo jmen <?= $v->e(number_format($points, 1, ',', '')) ?> pt.
            <?php if ($points < Poster::ReadablePoints): ?>
                <strong class="poster-warn">Takové písmo bude špatně čitelné – zvolte větší papír nebo méně generací.</strong>
            <?php endif ?>
        </p>
        <button type="submit" class="btn" data-live-submit>Zobrazit</button>
        <a class="btn" data-live="svg" href="/predci/<?= $v->e(rawurlencode($person->slug)) ?>/plakat.svg?<?= $v->e($options->query(!$auto)) ?>" download>Stáhnout SVG</a>
        <button type="button" class="btn btn-primary" data-print>Tisk / PDF</button>
    </div>
</form>

<p class="muted poster-hint">V dialogu tisku zvolte „Uložit jako PDF“ (nebo tiskárnu) – velikost papíru se nastaví sama a okraje nejsou potřeba. Nejspolehlivěji to funguje v Chrome, Edge a Firefoxu. SVG je pro tiskárnu nebo další úpravy; písmo historického stylu (EB Garamond) má v sobě.</p>

<div class="poster-preview" data-live="preview" data-live-busy>
    <div class="poster-sheet <?= $v->e($options->pageName()) ?>" style="--w: <?= $options->width() ?>mm; --h: <?= $options->height() ?>mm; --ratio: <?= round($options->width() / $options->height(), 4) ?>">
        <?= $sheet->svg ?>
    </div>
</div>
