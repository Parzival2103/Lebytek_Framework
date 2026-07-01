<?php

use Lebytek\Framework\Kernel\Constants\AppConstants;
use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/** @var string|null $empresaNombre */
$empresaNombre = AppConstants::resolveEmpresaNombre($empresaNombre ?? null);
?>
                    </td>
                </tr>
                <tr>
                    <td style="background:#f8f9fa; padding:24px; text-align:center; font-size:13px; color:#6c757d; line-height:1.7;">
                        &copy; <?= date('Y') ?> <?= ViewHelper::e($empresaNombre) ?><br>
                        Soluciones de automatización e integración empresarial<br><br>
                        Este correo fue generado automáticamente. Por favor, no respondas a este mensaje.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

</body>
</html>
