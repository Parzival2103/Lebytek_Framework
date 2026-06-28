<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Api;

use App\Kernel\BaseClasses\BaseController;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;

final class HealthController extends BaseController
{
    public function ping(Request $request): Response
    {
        return $this->json(['status' => 'ok', 'timestamp' => date('c')]);
    }
}
