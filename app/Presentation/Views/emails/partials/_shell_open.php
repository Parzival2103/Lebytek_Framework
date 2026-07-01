<?php

use Lebytek\Framework\Kernel\Constants\AppConstants;
use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/** @var string $preheader */
/** @var string $headerBg */
/** @var string $headerTitle */
/** @var string $headerSubtitle */
/** @var string|null $empresaNombre */
$empresaNombre = AppConstants::resolveEmpresaNombre($empresaNombre ?? null);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ViewHelper::e($headerTitle) ?></title>
</head>
<body style="margin:0; padding:24px; background:#f0f2f5; font-family:Arial, Helvetica, sans-serif; color:#212529;">

<div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent; mso-hide:all;">
    <?= ViewHelper::e($preheader) ?>
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td align="center">
            <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0"
                style="background:#ffffff; border-radius:12px; overflow:hidden;">
                <tr>
                    <td style="background:<?= ViewHelper::e($headerBg) ?>; padding:32px; text-align:center;">
                        <p style="margin:0 0 8px; color:rgba(255,255,255,0.85); font-size:13px; font-weight:600; letter-spacing:0.04em; text-transform:uppercase;">
                            <?= ViewHelper::e($empresaNombre) ?>
                        </p>
                        <h1 style="margin:0; color:#ffffff; font-size:26px; line-height:1.25;">
                            <?= ViewHelper::e($headerTitle) ?>
                        </h1>
                        <?php if (($headerSubtitle ?? '') !== ''): ?>
                        <p style="margin:12px 0 0; color:rgba(255,255,255,0.88); font-size:15px; line-height:1.5;">
                            <?= ViewHelper::e($headerSubtitle) ?>
                        </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
