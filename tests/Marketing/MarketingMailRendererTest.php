<?php

declare(strict_types=1);

use App\Application\Marketing\MarketingMailRenderer;
use App\Domain\Marketing\Contracts\PlantillaRepositoryInterface;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;

final class FakePlantillas implements PlantillaRepositoryInterface
{
    /** @var array<string, array<string, mixed>> */
    public array $byClave = [];

    public function findActiveByClave(string $clave): ?array
    {
        return $this->byClave[$clave] ?? null;
    }
}

final class CapturingMailer implements MailerInterface
{
    /** @var list<MensajeCorreo> */
    public array $sent = [];

    public function enviar(MensajeCorreo $mensaje): void
    {
        $this->sent[] = $mensaje;
    }
}

test('renderer sustituye vars y envia asunto/cuerpo de plantilla', function (): void {
    $repo = new FakePlantillas();
    $repo->byClave['membership_payment_failed'] = [
        'id' => 1,
        'clave' => 'membership_payment_failed',
        'asunto' => 'Hola {{nombre}} — pago',
        'cuerpo' => '<p>{{plan}} <a href="{{retry_url}}">x</a></p>',
        'activo' => 1,
    ];
    $mailer = new CapturingMailer();
    $renderer = new MarketingMailRenderer($repo, $mailer);

    $renderer->send('membership_payment_failed', 'a@b.c', 'Ana', [
        'nombre' => 'Ana',
        'plan' => 'starter',
        'retry_url' => 'https://lebytek.com/r',
    ]);

    assert_same(1, count($mailer->sent));
    assert_same('Hola Ana — pago', $mailer->sent[0]->asunto);
    assert_true(str_contains($mailer->sent[0]->html, 'starter'));
    assert_true(str_contains($mailer->sent[0]->html, 'https://lebytek.com/r'));
});

test('renderer escapa HTML en vars de usuario', function (): void {
    $repo = new FakePlantillas();
    $repo->byClave['lead_welcome'] = [
        'id' => 2, 'clave' => 'lead_welcome', 'asunto' => 'x', 'cuerpo' => '{{nombre}}', 'activo' => 1,
    ];
    $mailer = new CapturingMailer();
    $renderer = new MarketingMailRenderer($repo, $mailer);
    $renderer->send('lead_welcome', 'a@b.c', 'x', ['nombre' => '<script>']);
    assert_true(str_contains($mailer->sent[0]->html, '&lt;script&gt;'));
    assert_false(str_contains($mailer->sent[0]->html, '<script>'));
});

test('renderer lanza si clave ausente y sin fallback', function (): void {
    $renderer = new MarketingMailRenderer(new FakePlantillas(), new CapturingMailer());
    $threw = false;
    try {
        $renderer->send('missing_key', 'a@b.c', 'x', []);
    } catch (\RuntimeException $e) {
        $threw = true;
    }
    assert_true($threw);
});
