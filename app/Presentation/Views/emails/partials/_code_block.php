<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/** @var string $label */
/** @var string $value */
?>
<p style="margin:0 0 8px;"><strong><?= ViewHelper::e($label) ?></strong></p>
<div style="
    background:#f8f9fa;
    border:1px solid #dee2e6;
    border-radius:8px;
    padding:14px;
    font-family:Consolas, Monaco, monospace;
    font-size:14px;
    margin-bottom:20px;
    word-break:break-all;
"><?= ViewHelper::e($value) ?></div>
