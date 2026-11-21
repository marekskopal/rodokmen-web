<?php
/** @var Rodokmen\Site\View\View $v */
/** @var string $query */
?>
<header class="page-head">
    <h1>Hledat</h1>
    <p class="lead">Osoby, zápisy, příběhy a místa. Hledá se i v údajích (řemeslo, číslo domu, obec).</p>
</header>
<div class="search" data-search>
    <input type="search" class="search-input" value="<?= $v->e($query) ?>" placeholder="<?= $v->e($v->searchHint()) ?>" autofocus aria-label="Hledat">
    <p class="muted search-count" aria-live="polite"></p>
    <ul class="search-results"></ul>
</div>
