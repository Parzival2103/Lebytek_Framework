<?php

use App\Kernel\Helpers\ViewHelper;

?>
<link rel="stylesheet" href="<?= ViewHelper::asset('css/crud-engine.css') ?>">

<div class="container-fluid px-3 px-lg-4 py-3 py-lg-4 crud-engine ct-page">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            <div class="ct-form-card card border-0 shadow-sm ct-card">
                <div class="card-header bg-transparent border-bottom p-3 p-md-4 d-flex flex-column flex-md-row justify-content-md-between align-items-md-start gap-3">
                    <div>
                        <h1 class="ct-page-title h4 mb-1">
                            <?= !empty($isEdit) ? 'Editar' : 'Crear' ?> <?= ViewHelper::e((string) ($title ?? 'registro')) ?>
                        </h1>
                        <p class="ct-page-subtitle text-muted small mb-0">Los campos obligatorios llevan asterisco. Los errores se muestran bajo cada campo.</p>
                    </div>
                    <a href="/admin/crud/<?= ViewHelper::e((string) ($resource ?? '')) ?>"
                       class="btn btn-sm btn-outline-secondary align-self-stretch align-self-md-center">Volver al listado</a>
                </div>
                <div class="card-body p-3 p-md-4">
                    <form method="<?= ViewHelper::e($method ?? 'POST') ?>"
                          action="<?= ViewHelper::e($action ?? '') ?>"
                          enctype="multipart/form-data"
                          class="crud-form">
                        <?= ViewHelper::csrfField() ?>

                        <div class="row g-3">
                            <?php foreach (($fields ?? []) as $field): ?>
                                <?php
                                    $type = (string) ($field['type'] ?? 'text');
                                    $name = (string) ($field['name'] ?? '');
                                    $label = (string) ($field['label'] ?? $name);
                                    $value = $field['value'] ?? '';
                                    $required = !empty($field['required']);
                                    $readonly = !empty($field['readonly']);
                                    $readonlyPreservePost = !empty($field['readonlyPreservePost']);
                                    $hidden = !empty($field['hidden']);
                                    $col = (string) ($field['col'] ?? 'col-12');
                                    $options = is_array($field['options'] ?? null) ? $field['options'] : [];
                                    $fieldErrors = is_array($field['errors'] ?? null) ? $field['errors'] : [];
                                    $helpText = isset($field['help_text']) ? (string) $field['help_text'] : '';
                                ?>

                                <div class="<?= ViewHelper::e($hidden ? 'd-none' : $col) ?>">
                                    <label class="form-label fw-medium small mb-1" for="<?= ViewHelper::e($name) ?>">
                                        <?= ViewHelper::e($label) ?>
                                        <?php if ($required): ?><span class="text-danger" aria-hidden="true">*</span><?php endif; ?>
                                    </label>

                                    <?php if ($type === 'textarea'): ?>
                                        <textarea class="form-control <?= !empty($fieldErrors) ? 'is-invalid' : '' ?>"
                                                  id="<?= ViewHelper::e($name) ?>"
                                                  name="<?= ViewHelper::e($name) ?>"
                                                  rows="4"
                                                  <?= $required ? 'required' : '' ?>
                                                  <?= $readonly ? 'readonly' : '' ?>><?= ViewHelper::e((string) $value) ?></textarea>
                                    <?php elseif ($type === 'select'): ?>
                                        <?php if ($readonlyPreservePost): ?>
                                            <input type="hidden"
                                                   name="<?= ViewHelper::e($name) ?>"
                                                   value="<?= ViewHelper::e((string) $value) ?>">
                                        <?php endif; ?>
                                        <select class="form-select <?= !empty($fieldErrors) ? 'is-invalid' : '' ?> <?= $readonlyPreservePost ? 'opacity-75' : '' ?>"
                                                id="<?= ViewHelper::e($name) ?>"
                                                name="<?= ViewHelper::e($readonlyPreservePost ? $name . '__mirror' : $name) ?>"
                                                <?= (!$readonlyPreservePost && $required) ? 'required' : '' ?>
                                                <?= $readonlyPreservePost ? 'disabled tabindex="-1"' : ($readonly ? 'readonly' : '') ?>>
                                            <option value="">Selecciona…</option>
                                            <?php foreach ($options as $optValue => $optLabel): ?>
                                                <option value="<?= ViewHelper::e((string) $optValue) ?>" <?= ((string) $value === (string) $optValue) ? 'selected' : '' ?>>
                                                    <?= ViewHelper::e((string) $optLabel) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($type === 'checkbox'): ?>
                                        <?php if ($readonlyPreservePost): ?>
                                            <input type="hidden"
                                                   name="<?= ViewHelper::e($name) ?>"
                                                   value="<?= !empty($value) ? '1' : '0' ?>">
                                        <?php endif; ?>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input <?= !empty($fieldErrors) ? 'is-invalid' : '' ?> <?= $readonlyPreservePost ? 'opacity-75' : '' ?>"
                                                   type="checkbox"
                                                   id="<?= ViewHelper::e($name) ?>"
                                                   name="<?= ViewHelper::e($readonlyPreservePost ? $name . '__mirror' : $name) ?>"
                                                   value="1"
                                                   <?= !empty($value) ? 'checked' : '' ?>
                                                   <?= $readonlyPreservePost ? 'disabled tabindex="-1"' : ($readonly ? 'disabled' : '') ?>>
                                            <label class="form-check-label" for="<?= ViewHelper::e($name) ?>">Activo</label>
                                        </div>
                                    <?php elseif ($type === 'file'): ?>
                                        <input type="file"
                                               class="form-control <?= !empty($fieldErrors) ? 'is-invalid' : '' ?>"
                                               id="<?= ViewHelper::e($name) ?>"
                                               name="<?= ViewHelper::e($name) ?>"
                                               <?= $required && empty($isEdit) ? 'required' : '' ?>>
                                    <?php else: ?>
                                        <input type="<?= ViewHelper::e($type === 'hidden' ? 'hidden' : $type) ?>"
                                               class="form-control <?= !empty($fieldErrors) ? 'is-invalid' : '' ?>"
                                               id="<?= ViewHelper::e($name) ?>"
                                               name="<?= ViewHelper::e($name) ?>"
                                               value="<?= ViewHelper::e((string) $value) ?>"
                                               <?= $required ? 'required' : '' ?>
                                               <?= $readonly ? 'readonly' : '' ?>>
                                    <?php endif; ?>

                                    <?php if ($helpText !== ''): ?>
                                        <div class="form-text"><?= ViewHelper::e($helpText) ?></div>
                                    <?php endif; ?>

                                    <?php if (!empty($fieldErrors)): ?>
                                        <div class="invalid-feedback d-block">
                                            <?= ViewHelper::e((string) $fieldErrors[0]) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="crud-form-actions ct-actions flex-column flex-sm-row gap-2 mt-2">
                            <button type="submit" class="btn btn-primary order-2 order-sm-1">
                                <i class="bi bi-check-lg me-2" aria-hidden="true"></i><?= !empty($isEdit) ? 'Actualizar' : 'Guardar' ?>
                            </button>
                            <a href="/admin/crud/<?= ViewHelper::e((string) ($resource ?? '')) ?>"
                               class="btn btn-outline-secondary order-1 order-sm-2">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
