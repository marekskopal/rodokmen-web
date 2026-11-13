<?php
/** @var Rodokmen\Site\View\View $v */
/** @var list<array<string, mixed>> $places */
/** @var array<string, array<string, mixed>> $lines */
?>
<header class="page-head">
    <p class="eyebrow">Odkud pocházíme</p>
    <h1>Mapa</h1>
    <p class="lead">Obce, kde předci žili. Barva odpovídá rodu, kliknutím na místo uvidíte, kdo tam žil.</p>
</header>
<div class="map-layout">
    <div id="map" class="map" data-places='<?= $v->json($places) ?>'></div>
    <aside class="map-list">
        <?php
        $sorted = $places;
        usort($sorted, static fn ($a, $b) => strcoll($a['name'], $b['name']));
        foreach ($sorted as $place): ?>
            <details class="place" id="<?= $v->e(rawurlencode($place['name'])) ?>" data-place="<?= $v->e($place['name']) ?>">
                <summary>
                    <?php foreach ($place['groups'] as $g): ?><span class="dot" style="--c: <?= $v->e($g['color']) ?>"></span><?php endforeach ?>
                    <?= $v->e($place['name']) ?>
                    <?php if ($place['years'] !== []): ?><span class="muted thin"><?= $v->e(implode('–', array_unique($place['years']))) ?></span><?php endif ?>
                </summary>
                <ul>
                    <?php foreach ($place['groups'] as $g): foreach ($g['people'] as $p): ?>
                        <li><a href="<?= $v->e($p['url']) ?>"><?= $v->e($p['name']) ?></a> <span class="muted"><?= $v->e($p['y']) ?></span></li>
                    <?php endforeach; endforeach ?>
                </ul>
            </details>
        <?php endforeach ?>
    </aside>
</div>
