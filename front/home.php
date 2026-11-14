<?php
declare(strict_types=1);

include('../../../inc/includes.php');
Session::checkLoginUser();

Html::header(__('Auditoría WiFi', 'auditoriawifi'), $_SERVER['PHP_SELF'], 'plugins');

$canConfigure = Session::haveRight('auditoriawifi:config', UPDATE);
$canViewEvents = Session::haveRight('auditoriawifi:events', READ);
?>
<style>
.aw-home{max-width:900px;margin:1.25rem auto}
.aw-home-intro{color:var(--tblr-secondary,#667085);margin-bottom:1.15rem}
.aw-home-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem}
.aw-home-card{display:flex;flex-direction:column;min-height:176px;border:1px solid var(--tblr-border-color,#dce1e7);border-radius:10px;background:var(--tblr-bg-surface,#fff);padding:1rem 1.1rem;box-shadow:0 1px 4px rgba(0,0,0,.035);transition:box-shadow .15s,border-color .15s}
.aw-home-card-top{display:flex;align-items:center;gap:.65rem;min-height:36px}
.aw-home-icon{width:36px;height:36px;flex:0 0 36px;display:inline-flex;align-items:center;justify-content:center;border-radius:9px;background:var(--tblr-bg-surface-secondary,#f1f4f9);color:var(--tblr-primary,#206bc4);font-size:1.25rem}
.aw-home-card h3{font-size:1rem;font-weight:600;margin:.65rem 0 0;line-height:1.25}
.aw-home-card p{color:var(--tblr-secondary,#667085);font-size:.9rem;line-height:1.45;flex:1;margin:.75rem 0 1rem}
.aw-home-action{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;align-self:flex-start;text-decoration:none;font-weight:600;border-radius:6px;padding:.42rem .75rem;font-size:.875rem;color:#fff;background:var(--tblr-primary,#206bc4);border:1px solid var(--tblr-primary,#206bc4)}
.aw-home-action:hover,.aw-home-action:focus{color:#fff;background:var(--tblr-primary-dark,#1b5aa6);border-color:var(--tblr-primary-dark,#1b5aa6);text-decoration:none}
.aw-home-card:hover{border-color:var(--tblr-primary,#206bc4);box-shadow:0 4px 14px rgba(0,0,0,.07)}
.aw-home-unavailable{align-self:flex-start;font-size:.82rem;color:var(--tblr-secondary,#667085);padding:.42rem 0}
@media(max-width:576px){.aw-home-grid{grid-template-columns:1fr}.aw-home-card{min-height:0}}
</style>
<div class="container-fluid aw-home">
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/front/central.php"><?= htmlspecialchars(__('Inicio'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item">Complementos</li><li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars(__('Auditoría WiFi', 'auditoriawifi'), ENT_QUOTES, 'UTF-8') ?></li></ol></nav>
<h2 class="h3 mb-1"><?= htmlspecialchars(__('Auditoría WiFi', 'auditoriawifi'), ENT_QUOTES, 'UTF-8') ?></h2>
<p class="aw-home-intro"><?= htmlspecialchars(__('Seleccione la opción que desea utilizar.', 'auditoriawifi'), ENT_QUOTES, 'UTF-8') ?></p>
<div class="aw-home-grid">
<section class="aw-home-card">
<div class="aw-home-card-top"><span class="aw-home-icon" aria-hidden="true"><i class="ti ti-settings"></i></span></div>
<h3><?= htmlspecialchars(__('Configuración', 'auditoriawifi'), ENT_QUOTES, 'UTF-8') ?></h3>
<p><?= htmlspecialchars(__('Defina qué tipos de activos se auditan y cuáles eventos deben generar notificaciones por FML Telegram.', 'auditoriawifi'), ENT_QUOTES, 'UTF-8') ?></p>
<?php if ($canConfigure): ?>
<a class="aw-home-action" href="/plugins/auditoriawifi/front/config.php"><?= htmlspecialchars(__('Configurar parámetros', 'auditoriawifi'), ENT_QUOTES, 'UTF-8') ?> <span aria-hidden="true">→</span></a>
<?php else: ?>
<span class="aw-home-unavailable"><?= htmlspecialchars(__('Opción disponible para usuarios con permisos de configuración.', 'auditoriawifi'), ENT_QUOTES, 'UTF-8') ?></span>
<?php endif; ?>
</section>
<section class="aw-home-card">
<div class="aw-home-card-top"><span class="aw-home-icon" aria-hidden="true"><i class="ti ti-history"></i></span></div>
<h3><?= htmlspecialchars(__('Eventos', 'auditoriawifi'), ENT_QUOTES, 'UTF-8') ?></h3>
<p><?= htmlspecialchars(__('Consulte el historial de cambios detectados en los activos supervisados por Auditoría WiFi.', 'auditoriawifi'), ENT_QUOTES, 'UTF-8') ?></p>
<?php if ($canViewEvents): ?>
<a class="aw-home-action" href="/plugins/auditoriawifi/front/alerts.php"><?= htmlspecialchars(__('Ver eventos', 'auditoriawifi'), ENT_QUOTES, 'UTF-8') ?> <span aria-hidden="true">→</span></a>
<?php else: ?>
<span class="aw-home-unavailable"><?= htmlspecialchars(__('Opción disponible para usuarios con permisos de lectura de configuración.', 'auditoriawifi'), ENT_QUOTES, 'UTF-8') ?></span>
<?php endif; ?>
</section>
</div>
</div>
<?php Html::footer(); ?>
