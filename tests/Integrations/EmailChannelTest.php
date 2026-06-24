<?php
// tests/Integrations/EmailChannelTest.php
declare(strict_types=1);

use App\Application\DTO\Mail\MensajeCorreo;
use App\Domain\Integrations\MessageRequest;
use App\Domain\Interfaces\MailerInterface;
use App\Infrastructure\Integrations\Channels\EmailChannel;

/** Mailer falso que captura el último mensaje o lanza. */
final class SpyMailer implements MailerInterface
{
    public ?MensajeCorreo $last = null;
    public function __construct(private bool $throw = false) {}
    public function enviar(MensajeCorreo $mensaje): void
    {
        if ($this->throw) { throw new \RuntimeException('smtp down'); }
        $this->last = $mensaje;
    }
}

test('key() es "email"', function (): void {
    assert_same('email', (new EmailChannel(new SpyMailer()))->key());
});

test('adapta MessageRequest a MensajeCorreo (asunto desde meta) y delega', function (): void {
    $mailer = new SpyMailer();
    $c = new EmailChannel($mailer);
    $res = $c->send(new MessageRequest('email', 'a@b.com', '<p>hola</p>', ['subject' => 'Bienvenida', 'name' => 'Ada']));
    assert_true($res->ok, 'ok');
    assert_same('a@b.com', $mailer->last->destinatario, 'destinatario');
    assert_same('Ada', $mailer->last->nombreDestinatario, 'nombre');
    assert_same('Bienvenida', $mailer->last->asunto, 'asunto desde meta');
    assert_same('<p>hola</p>', $mailer->last->html, 'cuerpo html');
});

test('si el mailer lanza, devuelve failed sin propagar', function (): void {
    $c = new EmailChannel(new SpyMailer(true));
    $res = $c->send(new MessageRequest('email', 'a@b.com', 'x'));
    assert_true($res->ok === false, 'failed cuando el mailer lanza');
});
