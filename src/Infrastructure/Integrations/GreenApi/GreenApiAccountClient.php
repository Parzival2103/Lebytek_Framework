<?php

declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Integrations\GreenApi;

use Lebytek\Framework\Domain\Integrations\ApiConnectorInterface;

/**
 * Operaciones de cuenta Green API (QR, estado, etc.) para una instancia concreta.
 *
 * @see https://green-api.com/en/docs/api/account/QR/
 */
final class GreenApiAccountClient
{
    private const QR_CDN_BASE = 'https://qr.green-api.com';

    public function __construct(
        private readonly ApiConnectorInterface $http,
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @return array{
     *     ok: bool,
     *     qr_base64: ?string,
     *     qr_url: string,
     *     error: ?string,
     *     api_type: ?string
     * }
     */
    public function fetchQr(string $instanceId, string $token): array
    {
        $instanceId = trim($instanceId);
        $token = trim($token);
        $qrUrl = self::QR_CDN_BASE . '/waInstance' . $instanceId . '/' . $token;

        if ($instanceId === '' || $token === '') {
            return [
                'ok'         => false,
                'qr_base64'  => null,
                'qr_url'     => $qrUrl,
                'error'      => 'Credenciales de instancia incompletas.',
                'api_type'   => null,
            ];
        }

        $apiUrl = rtrim($this->baseUrl, '/') . '/waInstance' . $instanceId . '/qr/' . $token;
        $res = $this->http->request('GET', $apiUrl);

        if ((int) ($res['status'] ?? 0) === 0) {
            return [
                'ok'         => false,
                'qr_base64'  => null,
                'qr_url'     => $qrUrl,
                'error'      => 'No se pudo contactar Green API: ' . (string) ($res['body'] ?? 'error de transporte'),
                'api_type'   => null,
            ];
        }

        $json = (array) ($res['json'] ?? []);
        $type = (string) ($json['type'] ?? '');
        $message = trim((string) ($json['message'] ?? ''));

        if ($type === 'qrCode' && $message !== '') {
            return [
                'ok'         => true,
                'qr_base64'  => preg_replace('/\s+/', '', $message) ?: null,
                'qr_url'     => $qrUrl,
                'error'      => null,
                'api_type'   => $type,
            ];
        }

        if ($type === 'alreadyLogged') {
            return [
                'ok'         => false,
                'qr_base64'  => null,
                'qr_url'     => $qrUrl,
                'error'      => null,
                'api_type'   => $type,
            ];
        }

        if ($type === 'error') {
            return [
                'ok'         => false,
                'qr_base64'  => null,
                'qr_url'     => $qrUrl,
                'error'      => $message !== '' ? $message : 'Green API devolvió un error al obtener el QR.',
                'api_type'   => $type,
            ];
        }

        return [
            'ok'         => false,
            'qr_base64'  => null,
            'qr_url'     => $qrUrl,
            'error'      => $message !== ''
                ? $message
                : 'Respuesta inesperada de Green API (HTTP ' . (int) ($res['status'] ?? 0) . ').',
            'api_type'   => $type !== '' ? $type : null,
        ];
    }

    /**
     * @return array{ok: bool, state: ?string, error: ?string}
     */
    public function fetchState(string $instanceId, string $token): array
    {
        $instanceId = trim($instanceId);
        $token = trim($token);

        if ($instanceId === '' || $token === '') {
            return ['ok' => false, 'state' => null, 'error' => 'Credenciales de instancia incompletas.'];
        }

        $url = rtrim($this->baseUrl, '/') . '/waInstance' . $instanceId . '/getStateInstance/' . $token;
        $res = $this->http->request('GET', $url);

        if ((int) ($res['status'] ?? 0) === 0) {
            return [
                'ok'    => false,
                'state' => null,
                'error' => 'No se pudo contactar Green API: ' . (string) ($res['body'] ?? 'error de transporte'),
            ];
        }

        $state = trim((string) (((array) ($res['json'] ?? []))['stateInstance'] ?? ''));

        return [
            'ok'    => true,
            'state' => $state !== '' ? $state : 'desconocido',
            'error' => null,
        ];
    }

    /**
     * @return array{
     *     phase: 'awaiting_scan'|'syncing'|'ready'|'error',
     *     message: ?string,
     *     qr_base64: ?string,
     *     qr_url: string,
     *     state: ?string
     * }
     */
    public function resolveActivationPhase(string $instanceId, string $token): array
    {
        $qr = $this->fetchQr($instanceId, $token);
        $stateResult = $this->fetchState($instanceId, $token);
        $state = $stateResult['ok'] ? (string) ($stateResult['state'] ?? 'desconocido') : null;
        $qrUrl = (string) ($qr['qr_url'] ?? '');

        if ($state === 'authorized') {
            return [
                'phase'       => 'ready',
                'message'     => 'Tu WhatsApp está conectado y listo para usar la API.',
                'qr_base64'   => null,
                'qr_url'      => $qrUrl,
                'state'       => $state,
            ];
        }

        if ($qr['api_type'] === 'alreadyLogged' || in_array($state, ['starting', 'yellowCard'], true)) {
            return [
                'phase'       => 'syncing',
                'message'     => 'El código QR ya fue escaneado. Tu instancia se está sincronizando con WhatsApp; esto puede tardar unos segundos.',
                'qr_base64'   => null,
                'qr_url'      => $qrUrl,
                'state'       => $state,
            ];
        }

        if ($qr['ok']) {
            return [
                'phase'       => 'awaiting_scan',
                'message'     => null,
                'qr_base64'   => $qr['qr_base64'],
                'qr_url'      => $qrUrl,
                'state'       => $state,
            ];
        }

        $error = (string) ($qr['error'] ?? '');
        if ($error === '' && !$stateResult['ok']) {
            $error = (string) ($stateResult['error'] ?? 'No se pudo consultar el estado de la instancia.');
        }
        if ($error === '') {
            $error = 'No se pudo obtener el código QR. Intenta más tarde.';
        }

        return [
            'phase'       => 'error',
            'message'     => $error,
            'qr_base64'   => null,
            'qr_url'      => $qrUrl,
            'state'       => $state,
        ];
    }
}
