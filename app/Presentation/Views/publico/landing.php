<?php
// app/Presentation/Views/publico/landing.php

use App\Kernel\Helpers\ViewHelper;

$bloques  = $bloques ?? [];
$paquetes = $paquetes ?? [];
$hero     = $bloques['hero'] ?? ['titulo' => '', 'subtitulo' => '', 'cta_texto' => '', 'cta_url' => '#'];
?>
<section class="py-5 bg-white">
    <div class="container text-center py-4">
        <h1 class="display-5 fw-bold"><?= ViewHelper::e($hero['titulo'] ?? '') ?></h1>
        <p class="lead text-muted"><?= ViewHelper::e($hero['subtitulo'] ?? '') ?></p>
        <?php if (!empty($hero['cta_texto'])): ?>
            <a href="<?= ViewHelper::e($hero['cta_url'] ?? '#') ?>" class="btn btn-primary btn-lg mt-3">
                <?= ViewHelper::e($hero['cta_texto']) ?>
            </a>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($paquetes)): ?>
<section class="py-5" id="paquetes">
    <div class="container">
        <h2 class="h3 text-center mb-4">Paquetes</h2>
        <div class="row g-4 justify-content-center">
            <?php foreach ($paquetes as $p): ?>
                <div class="col-md-4">
                    <div class="card h-100 <?= !empty($p['destacado']) ? 'border-primary' : '' ?>">
                        <div class="card-body text-center">
                            <?php if (!empty($p['badge'])): ?>
                                <span class="badge bg-primary mb-2"><?= ViewHelper::e($p['badge']) ?></span>
                            <?php endif; ?>
                            <h3 class="h5"><?= ViewHelper::e($p['nombre'] ?? '') ?></h3>
                            <p class="display-6 fw-bold"><?= ViewHelper::e((string) ($p['precio_mensual'] ?? '')) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
