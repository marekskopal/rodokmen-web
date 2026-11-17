<?php
/** @var Rodokmen\Site\View\View $v */
/** @var Rodokmen\Site\Data\Repository $repo */
/** @var Rodokmen\Site\Data\Model\Person $person */
/** @var non-empty-list<Rodokmen\Site\View\AncestorGeneration> $generations */
/** @var int $depth */
/** @var int $available */
/** @var bool $transcripts */
// Known ancestors: the person and the repeated entries do not count
$count = -1;
$listedNumbers = [];
foreach ($generations as $g) {
    foreach ($g->entries as $entry) {
        $listedNumbers[$entry->number] = true;
        $count += $entry->sameAs === null ? 1 : 0;
    }
}
$parent = function (?string $slug, int $number) use ($repo, $v, $listedNumbers): string {
    $p = $repo->person($slug);
    if ($p === null) {
        return '<span class="muted">neznámý</span>';
    }

    return isset($listedNumbers[$number])
        ? '<a href="#c-' . $number . '">č. ' . $number . '</a> ' . $v->e($p->name)
        : $v->e($p->name);
};
?>
<header class="page-head print-head">
    <p class="eyebrow">Vývod předků</p>
    <h1><?= $v->e($person->name) ?> <span class="muted thin"><?= $v->e($v->years($person)) ?></span></h1>
    <p class="lead" data-live="lead">
        <?= $v->e($v->plural($depth, 'generace', 'generace', 'generací')) ?> předků,
        <?= $v->e($v->plural($count, 'známý předek', 'známí předkové', 'známých předků')) ?>
        · <?= $v->e($repo->title()) ?> · <?= date('j. n. Y') ?>
    </p>
</header>

<form class="filters print-toolbar" method="get" data-live-form>
    <label>Generací
        <select name="generace">
            <?php for ($i = 1; $i <= max(1, $available); $i++): ?>
                <option value="<?= $i ?>" <?= $i === $depth ? 'selected' : '' ?>><?= $i ?></option>
            <?php endfor ?>
        </select>
    </label>
    <label class="filter-check"><input type="checkbox" name="zapisy" value="1" <?= $transcripts ? 'checked' : '' ?>> s přepisy zápisů</label>
    <button type="submit" class="btn" data-live-submit>Zobrazit</button>
    <button type="button" class="btn btn-primary" data-print>Tisk / PDF</button>
    <a class="btn" href="/predci/<?= $v->e(rawurlencode($person->slug)) ?>/plakat">Plakát předků</a>
    <a class="btn btn-ghost" href="<?= $v->e($v->explorerUrl($person->slug)) ?>">Zpět do prohlížeče</a>
</form>

<div data-live="list" data-live-busy>
<p class="muted anc-legend">Číslování: osoba má číslo 1, otec osoby s číslem n má číslo 2n, matka 2n + 1. Chybějící číslo znamená předka, kterého zatím neznáme.</p>

<?php foreach ($generations as $generation): ?>
    <section class="anc-gen">
        <h2><?= $v->e($generation->title()) ?> <span class="muted thin">(<?= count($generation->entries) ?>)</span></h2>
        <?php foreach ($generation->entries as $entry): $p = $entry->person; ?>
            <article class="anc-person" id="c-<?= $entry->number ?>" style="--c: <?= $v->e($repo->lineColor($p->line)) ?>">
                <header class="anc-head">
                    <span class="anc-num"><?= $entry->number ?></span>
                    <h3><a href="<?= $v->e($v->personUrl($p->slug)) ?>"><?= $v->e($p->name) ?></a></h3>
                    <span class="muted"><?= $v->e($v->years($p)) ?></span>
                    <?= $v->status($p) ?>
                </header>
                <?php if ($entry->sameAs !== null): ?>
                    <p class="muted">Tentýž předek jako <a href="#c-<?= $entry->sameAs ?>">č. <?= $entry->sameAs ?></a> – údaje jsou uvedené tam.</p>
                <?php else: ?>
                    <p class="anc-parents muted">
                        Otec: <?= $parent($p->father, $entry->fatherNumber()) ?> · Matka: <?= $parent($p->mother, $entry->motherNumber()) ?>
                    </p>
                    <?php if ($p->facts !== []): ?>
                        <ul class="anc-facts">
                            <?php foreach ($p->facts as $f): $fact = $v->fact($f); ?>
                                <li><?php if ($fact['label'] !== ''): ?><strong><?= $v->e($fact['label']) ?>:</strong> <?php endif ?><?= $v->e($fact['text']) ?></li>
                            <?php endforeach ?>
                        </ul>
                    <?php endif ?>
                    <?php if ($p->note !== ''): ?>
                        <p class="note"><?= $v->e($p->note) ?></p>
                    <?php endif ?>
                    <?php $records = $repo->recordsByKeys($p->records) ?>
                    <?php if ($records !== []): ?>
                        <ol class="anc-sources">
                            <?php foreach ($records as $r): ?>
                                <li>
                                    <strong><?= $v->e($v->kindLabel($r->kind)) ?></strong> <?= $v->e($r->date) ?> –
                                    <?= $v->e($r->archive) ?>, <?= $v->e($r->vdesc) ?><?php if ($r->scan > 0): ?>, snímek <?= $r->scan ?><?php endif ?>
                                    <?php if ($r->url !== ''): ?><span class="anc-url"><a href="<?= $v->e($r->url) ?>" target="_blank" rel="noopener noreferrer"><?= $v->e($r->url) ?></a></span><?php endif ?>
                                    <?php if ($transcripts && ($r->orig !== '' || $r->cs !== '')): ?>
                                        <div class="anc-texts">
                                            <?php if ($r->orig !== ''): ?><p class="transcript"><?= nl2br($v->e($r->orig)) ?></p><?php endif ?>
                                            <?php if ($r->cs !== ''): ?><p><?= nl2br($v->e($r->cs)) ?></p><?php endif ?>
                                        </div>
                                    <?php endif ?>
                                </li>
                            <?php endforeach ?>
                        </ol>
                    <?php endif ?>
                <?php endif ?>
            </article>
        <?php endforeach ?>
    </section>
<?php endforeach ?>
</div>
