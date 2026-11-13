<?php
/** @var Rodokmen\Site\View\View $v */
/** @var Rodokmen\Site\Data\Repository $repo */
?>
<div class="login">
    <form class="login-card" method="post" action="/prihlaseni">
        <svg class="login-mark" viewBox="0 0 32 32" aria-hidden="true"><path d="M16 29V15M16 15c0-5 3-8 8-9M16 15c0-5-3-8-8-9M16 20c0-3 2-5 5-6M16 20c0-3-2-5-5-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <h1><?= $v->e($repo->title()) ?></h1>
        <p class="muted">Rodinný web jen pro příbuzné. Zadejte heslo, které jste dostali od rodiny.</p>
        <?php if (!$configured): ?>
            <p class="alert">Heslo zatím není nastavené (SITE_PASSWORD_HASH a SITE_SECRET v .env).</p>
        <?php endif ?>
        <?php if ($error): ?>
            <p class="alert" role="alert"><?= $v->e($error) ?></p>
        <?php endif ?>
        <label for="heslo" class="sr-only">Heslo</label>
        <input id="heslo" name="heslo" type="password" placeholder="Heslo" autocomplete="current-password" required autofocus>
        <input type="hidden" name="k" value="<?= $v->e($target) ?>">
        <button type="submit" class="btn btn-primary">Vstoupit</button>
    </form>
</div>
