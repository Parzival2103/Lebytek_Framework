<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use Lebytek\Framework\Kernel\EnvLoader;

final class BankTransferConfig
{
    /** @return array{bank_name:string,beneficiary:string,clabe:string,account:string,proof_guide:string,reference:string} */
    public static function forOrder(string $publicId): array
    {
        return [
            'bank_name' => (string) EnvLoader::get('MKT_BANK_NAME', ''),
            'beneficiary' => (string) EnvLoader::get('MKT_BANK_BENEFICIARY', ''),
            'clabe' => (string) EnvLoader::get('MKT_BANK_CLABE', ''),
            'account' => (string) EnvLoader::get('MKT_BANK_ACCOUNT', ''),
            'proof_guide' => (string) EnvLoader::get(
                'MKT_BANK_PROOF_GUIDE',
                'Envía el comprobante por WhatsApp o correo al equipo de operaciones Lebytek indicando la referencia de la orden.',
            ),
            'reference' => 'ORD-'.$publicId,
        ];
    }
}
