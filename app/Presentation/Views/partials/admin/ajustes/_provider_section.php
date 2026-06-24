<?php
// app/Presentation/Views/partials/admin/ajustes/_provider_section.php

use App\Kernel\Helpers\ViewHelper;

/** @var \App\Domain\Interfaces\SettingsSectionProviderInterface $section */
/** @var array $configuracion */
$c = $configuracion ?? [];
ob_start();
if ($section->vista() !== null) {
    echo ViewHelper::partial($section->vista(), ['configuracion' => $c]);
} else {
?>
<div class="row g-3">
    <?php foreach ($section->campos() as $campo): ?>
        <?php
            $name = $campo['name'];
            $type = $campo['type'] ?? 'text';
            $val  = $c[$name] ?? ($campo['default'] ?? '');
        ?>
        <div class="col-md-6">
            <label for="<?= ViewHelper::e($name) ?>" class="form-label fw-medium small"><?= ViewHelper::e($campo['label']) ?></label>
            <?php if ($type === 'toggle'): ?>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="<?= ViewHelper::e($name) ?>" name="<?= ViewHelper::e($name) ?>"
                           value="1" <?= ((string) $val) === '1' ? 'checked' : '' ?>>
                </div>
            <?php elseif ($type === 'select' && !empty($campo['options'])): ?>
                <select id="<?= ViewHelper::e($name) ?>" name="<?= ViewHelper::e($name) ?>" class="form-select">
                    <?php foreach ($campo['options'] as $ov => $ol): ?>
                        <option value="<?= ViewHelper::e($ov) ?>" <?= ((string) $val) === (string) $ov ? 'selected' : '' ?>><?= ViewHelper::e($ol) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="<?= $type === 'secret' ? 'password' : 'text' ?>" id="<?= ViewHelper::e($name) ?>" name="<?= ViewHelper::e($name) ?>"
                       class="form-control" value="<?= ViewHelper::e((string) $val) ?>">
            <?php endif; ?>
            <?php if (!empty($campo['help'])): ?>
                <div class="form-text"><?= ViewHelper::e($campo['help']) ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php
}
$bodyHtml = ob_get_clean();
echo ViewHelper::partial('admin/ajustes_accordion_item', [
    'collapseId' => 'ajustesCollapse' . ucfirst(preg_replace('/[^a-z0-9]/i', '', $section->clave())),
    'headingId'  => 'ajustesHeading' . ucfirst(preg_replace('/[^a-z0-9]/i', '', $section->clave())),
    'title'      => $section->titulo(),
    'iconClass'  => $section->icono(),
    'bodyHtml'   => $bodyHtml,
]);
