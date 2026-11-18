<?php
/** @var Rodokmen\Site\View\View $v */
/** @var Rodokmen\Site\Data\Repository $repo */
/** @var Rodokmen\Site\Data\Model\Person $person */
/** @var list<Rodokmen\Site\Data\Model\Record> $records */
/** @var list<Rodokmen\Site\Data\Model\Place> $places */
/** @var list<string>|null $path */
/** @var Rodokmen\Site\Data\Model\Person $home */
/** @var bool $isHome */
/** @var list<Rodokmen\Site\Data\Model\Side> $siblings */
/** @var list<Rodokmen\Site\Data\Model\Side> $relatives */
/** @var list<Rodokmen\Site\Data\Model\Side> $childSiblings */
/** @var Rodokmen\Site\Data\Model\Line|null $line */
/** @var Rodokmen\Site\Data\Model\Family|null $ownFamily */
/** @var list<Rodokmen\Site\Data\Model\PersonDocument> $documents */
use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Model\Side;

$spouse = $repo->person($person->spouse);
$facts = $v->timeline($person->facts);
$sideFolks = array_merge($siblings, $relatives);
$yearOf = static fn (string $y): int => preg_match('/\d{4}/', $y, $m) === 1 ? (int) $m[0] : 9999;
$siblingsByYear = $siblings;
usort($siblingsByYear, static fn (Side $a, Side $b): int => $yearOf($a->birthYear) <=> $yearOf($b->birthYear));
$sibling = static fn (Side $s): string => '<span class="sib" title="' . $v->e($s->text) . '">' . $v->e($s->name)
    . ($s->unsure ? ' <span class="muted">(?)</span>' : '')
    . ($s->isHalfSibling() ? ' <span class="muted">· nevlastní</span>' : '') . '</span>';
// Children: the ancestor in the direct line (linked) and his or her full siblings, by year of birth.
$children = array_merge(
    array_filter(array_map(fn (string $c): ?Person => $repo->person($c), $person->children)),
    $childSiblings,
);
usort($children, static fn (Person|Side $a, Person|Side $b): int
    => $yearOf($a instanceof Person ? $a->y : $a->birthYear) <=> $yearOf($b instanceof Person ? $b->y : $b->birthYear));
?>
<article class="person" style="--c: <?= $v->e($repo->lineColor($person->line)) ?>">
    <header class="person-head">
        <div>
            <p class="eyebrow">
                <?php if ($line !== null): ?><a href="/generace?rod=<?= $v->e($line->slug) ?>"><?= $v->lineDot($line->k) ?>Rod <?= $v->e($line->name) ?></a> · <?php endif ?>
                <?= $v->e(ucfirst($person->rel)) ?> · <?= $person->gen ?>. generace
            </p>
            <h1><?= $v->e($person->name) ?></h1>
            <p class="person-years"><?= $v->e($v->years($person)) ?> <?= $v->status($person) ?></p>
        </div>
        <div class="person-actions">
            <a class="btn btn-primary" href="<?= $v->e($v->explorerUrl($person->slug)) ?>">Předci</a>
            <a class="btn" href="/predci/<?= $v->e(rawurlencode($person->slug)) ?>/tisk">Vývod předků</a>
            <button type="button" class="btn btn-ghost" data-print>Tisk / PDF</button>
            <?php if (!$isHome): ?>
                <form method="post" action="/vychozi/<?= $v->e(rawurlencode($person->slug)) ?>">
                    <button type="submit" class="btn btn-ghost" title="Prohlížeč předků bude začínat touto osobou">Nastavit jako výchozí</button>
                </form>
            <?php else: ?>
                <span class="chip">výchozí osoba</span>
            <?php endif ?>
        </div>
    </header>

    <?php if ($path !== null && count($path) > 1): ?>
        <nav class="trail" aria-label="Cesta k výchozí osobě">
            <span class="muted">Cesta k osobě <?= $v->e($home->name) ?>:</span>
            <?php foreach ($path as $i => $slug): $p = $repo->person($slug); ?>
                <?php if ($i > 0): ?><span class="trail-sep">→</span><?php endif ?>
                <a href="<?= $v->e($v->personUrl($slug)) ?>" class="<?= $slug === $person->slug ? 'current' : '' ?>"><?= $v->e($p->name ?? $slug) ?></a>
            <?php endforeach ?>
        </nav>
    <?php endif ?>

    <div class="person-grid">
        <div class="person-main">
            <section class="card">
                <h2>Život</h2>
                <?php if ($facts === []): ?>
                    <p class="muted">Zatím bez údajů.</p>
                <?php else: ?>
                    <ol class="timeline">
                        <?php foreach ($facts as $fact): ?>
                            <li class="tl tl-<?= $v->e($fact['type']) ?>">
                                <span class="tl-dot" aria-hidden="true"></span>
                                <?php if ($fact['label'] !== ''): ?><span class="tl-label"><?= $v->e($fact['label']) ?></span><?php endif ?>
                                <span class="tl-text"><?= $v->e($fact['text']) ?></span>
                            </li>
                        <?php endforeach ?>
                    </ol>
                <?php endif ?>
                <?php if ($person->note !== ''): ?>
                    <p class="note"><?= $v->e($person->note) ?></p>
                <?php endif ?>
            </section>

            <?php if ($documents !== []): ?>
                <section class="card">
                    <h2>Fotografie a dokumenty</h2>
                    <div class="gallery">
                        <?php foreach ($documents as $doc): ?>
                            <figure>
                                <a href="<?= $v->e($v->imageUrl($doc->img, true)) ?>" data-lightbox><img src="<?= $v->e($v->imageUrl($doc->img)) ?>"<?= $v->srcset($doc->img, '(max-width: 640px) 50vw, 240px') ?> alt="<?= $v->e($doc->caption) ?>" loading="lazy"></a>
                                <figcaption><?= $v->e($doc->caption) ?></figcaption>
                            </figure>
                        <?php endforeach ?>
                    </div>
                </section>
            <?php endif ?>

            <section>
                <h2 class="section-title">Zápisy z matrik <span class="muted thin">(<?= count($records) ?>)</span></h2>
                <?php if ($records === []): ?>
                    <p class="muted">K této osobě zatím nemáme žádný zápis z matriky.</p>
                <?php endif ?>
                <?php foreach ($records as $r) { require __DIR__ . '/partials/record-card.php'; } ?>
            </section>
        </div>

        <aside class="person-side">
            <section class="card">
                <h2>Rodina</h2>
                <dl class="family">
                    <dt>Otec</dt><dd><?= $v->personLink($person->father, $person->fatherUnknown ? 'neuveden' : 'neznámý') ?></dd>
                    <dt>Matka</dt><dd><?= $v->personLink($person->mother, $person->motherUnknown ? 'neuvedena' : 'neznámá') ?></dd>
                    <?php if ($spouse !== null): ?><dt><?= $person->isFemale() ? 'Manžel' : 'Manželka' ?></dt><dd><?= $v->personLink($spouse->slug) ?></dd><?php endif ?>
                    <?php if ($siblings !== []): ?>
                        <dt>Sourozenci</dt>
                        <dd><?php foreach ($siblingsByYear as $s): ?><div><?= $sibling($s) ?></div><?php endforeach ?></dd>
                    <?php endif ?>
                    <?php if ($children !== []): ?>
                        <dt>Děti</dt>
                        <dd><?php foreach ($children as $c): ?><div><?= $c instanceof Person ? $v->personLink($c->slug) : $sibling($c) ?></div><?php endforeach ?></dd>
                    <?php endif ?>
                </dl>
            </section>

            <?php if ($sideFolks !== []): ?>
                <section class="card">
                    <h2>Sourozenci a příbuzní</h2>
                    <ul class="side-list">
                        <?php foreach ($sideFolks as $s): ?>
                            <li><strong><?= $v->e($s->name) ?></strong><br><span class="muted"><?= $v->e($s->text) ?></span></li>
                        <?php endforeach ?>
                    </ul>
                </section>
            <?php endif ?>

            <?php if ($places !== []): ?>
                <section class="card">
                    <h2>Místa</h2>
                    <div class="minimap" data-map='<?= $v->json(array_map(fn ($pl) => ['name' => $pl->name, 'lat' => $pl->lat, 'lon' => $pl->lon], $places)) ?>' data-color="<?= $v->e($repo->lineColor($person->line)) ?>"></div>
                    <p class="chips"><?php foreach ($places as $pl): ?><a class="chip" href="/mapa#<?= $v->e(rawurlencode($pl->name)) ?>"><?= $v->e($pl->name) ?></a><?php endforeach ?></p>
                </section>
            <?php endif ?>
        </aside>
    </div>
</article>
