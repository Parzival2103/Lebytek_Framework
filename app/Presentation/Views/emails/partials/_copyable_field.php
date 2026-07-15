<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/** @var string $label */
/** @var string $value */
/** @var string|null $hint */
/** @var bool|null $showCopyButton */
?>
<p style="margin:0 0 8px;"><strong><?= ViewHelper::e($label) ?></strong></p>
<?php if (! empty($hint)): ?>
<p style="margin:0 0 10px; font-size:13px; line-height:1.5; color:#6c757d;">
    <?= $hint ?>
</p>
<?php endif; ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:12px;">
    <tr>
        <td style="
            background:#f8f9fa;
            border:1px solid #dee2e6;
            border-radius:8px;
            padding:12px 14px;
        ">
            <input
                type="text"
                readonly
                value="<?= ViewHelper::e($value) ?>"
                onclick="this.focus();this.select();"
                style="
                    width:100%;
                    box-sizing:border-box;
                    border:none;
                    background:transparent;
                    font-family:Consolas, Monaco, 'Courier New', monospace;
                    font-size:14px;
                    line-height:1.5;
                    color:#212529;
                    padding:0;
                    margin:0;
                    outline:none;
                    -webkit-user-select:all;
                    user-select:all;
                "
            />
        </td>
    </tr>
</table>
<?php if (! empty($showCopyButton)): ?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">
    <tr>
        <td align="left">
            <a
                href="#"
                onclick="event.preventDefault();var el=this.closest('table').previousElementSibling.querySelector('input');if(el){el.focus();el.select();try{document.execCommand('copy');}catch(e){}}return false;"
                style="
                    display:inline-block;
                    background:#0d6efd;
                    color:#ffffff !important;
                    text-decoration:none;
                    font-size:14px;
                    font-weight:600;
                    padding:10px 18px;
                    border-radius:6px;
                "
            >Copiar al portapapeles</a>
        </td>
    </tr>
    <tr>
        <td style="padding-top:8px; font-size:12px; color:#6c757d;">
            Si el botón no funciona en tu cliente de correo, toca el campo de arriba y usa <strong>Ctrl+C</strong> (o <strong>Cmd+C</strong>).
        </td>
    </tr>
</table>
<?php else: ?>
<div style="margin-bottom:20px;"></div>
<?php endif; ?>
