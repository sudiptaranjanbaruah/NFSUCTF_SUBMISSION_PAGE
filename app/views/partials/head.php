<?php /** @var string $title */ ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(isset($title) && $title ? $title . ' · ' . setting('event_name') : setting('event_name')) ?></title>
<meta name="description" content="<?= e(setting('event_tagline')) ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="icon" href="<?= e(setting('logo_url') ?: asset('img/logo.svg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap">
<link rel="stylesheet" href="<?= asset('css/tokens.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<?php if (setting('brand_accent')): ?>
<style>:root,:root[data-theme]{--accent:<?= e(setting('brand_accent')) ?>;--accent-strong:<?= e(setting('brand_accent')) ?>;--accent-soft:color-mix(in srgb,<?= e(setting('brand_accent')) ?> 12%,transparent);--accent-line:color-mix(in srgb,<?= e(setting('brand_accent')) ?> 32%,transparent)}</style>
<?php endif ?>
<script src="<?= asset('js/theme.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>" defer></script>
