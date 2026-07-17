<?php

declare(strict_types=1);

use App\Application\Marketing\MarketingMailRenderer;
use App\Domain\Marketing\Contracts\PlantillaRepositoryInterface;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;

final class NullPlantillaRepository implements PlantillaRepositoryInterface
{
    public function findActiveByClave(string $clave): ?array
    {
        return null;
    }
}

function marketingMailRenderer(MailerInterface $mailer): MarketingMailRenderer
{
    return new MarketingMailRenderer(new NullPlantillaRepository(), $mailer);
}
