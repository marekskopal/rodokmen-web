<?php
/** @var Rodokmen\Site\View\View $v */
/** @var Rodokmen\Site\Data\Repository $repo */
/** @var array<int, list<Rodokmen\Site\Data\Model\Person>> $byGeneration */
/** @var array<string, Rodokmen\Site\Data\Model\Line> $lines */
/** @var string $activeLine */
$genNames = [0 => 'Výchozí osoba', 1 => 'Rodiče', 2 => 'Prarodiče', 3 => 'Praprarodiče'];
?>
<header class="page-head">
    <p class="eyebrow">Přehled</p>
    <h1>Generace</h1>
    <p class="lead">Všichni předci seřazení podle generací. Filtrujte podle rodu nebo hledejte jméno či místo.</p>
</header>

<div class="filters" data-filter-root>
    <input type="search" class="filter-text" placeholder="Filtrovat jméno, místo, řemeslo…" data-filter-text aria-label="Filtrovat">
    <div class="filter-chips" role="group" aria-label="Rod">
        <button type="button" class="chip chip-btn <?= $activeLine === '' ? 'on' : '' ?>" data-filter-line="">Všechny rody</button>
        <?php foreach ($lines as $line): ?>
            <button type="button" class="chip chip-btn <?= $activeLine === $line->slug ? 'on' : '' ?>" data-filter-line="<?= $v->e($line->slug) ?>" style="--c: <?= $v->e($line->color) ?>"><?= $v->lineDot($line->k) ?><?= $v->e($line->name) ?></button>
        <?php endforeach ?>
    </div>
    <label class="filter-check"><input type="checkbox" data-filter-doc> jen doložené matrikou</label>
</div>

<?php foreach ($byGeneration as $gen => $people): ?>
    <section class="gen" data-gen>
        <h2 class="gen-title">
            <span class="gen-num"><?= (int) $gen ?></span>
            <?= $v->e($genNames[$gen] ?? $gen . '. generace předků') ?>
            <span class="muted thin" data-gen-count><?= count($people) ?></span>
        </h2>
        <div class="table-wrap">
            <table class="people">
                <thead><tr><th>Jméno</th><th>Roky</th><th>Vztah</th><th>Místa</th><th>Údaj</th></tr></thead>
                <tbody>
                <?php foreach ($people as $p):
                    $placeNames = $repo->placeNamesOf($p->slug); ?>
                    <tr data-line="<?= $v->e($p->lineSlug ?? '') ?>" data-st="<?= $v->e($p->st) ?>"
                        data-text="<?= $v->e(mb_strtolower($p->name . ' ' . implode(' ', $placeNames) . ' ' . implode(' ', $p->facts))) ?>">
                        <td><?= $v->personLink($p->slug) ?></td>
                        <td class="nowrap"><?= $v->e($v->years($p)) ?></td>
                        <td class="muted"><?= $v->e($p->rel) ?></td>
                        <td class="muted"><?= $v->e(implode(', ', array_slice($placeNames, 0, 3))) ?></td>
                        <td><?= $v->status($p) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endforeach ?>
<p class="empty-note" data-filter-empty hidden>Nic neodpovídá filtru.</p>
