<?php use App\Kernel\Helpers\ViewHelper; ?>
<?php if (!empty($titulo)): ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h4 fw-semibold mb-0"><?= ViewHelper::e($titulo) ?></h1>
</div>
<?php endif; ?>
