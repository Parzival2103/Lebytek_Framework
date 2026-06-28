<?php

declare(strict_types=1);

namespace Lebytek\Framework\Presentation\Controllers\Api;

use Lebytek\Framework\Kernel\BaseClasses\BaseController;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;

final class HealthController extends BaseController
{
    public function ping(Request $request): Response
    {
        return $this->json(['status' => 'ok', 'timestamp' => date('c')]);
    }
}
