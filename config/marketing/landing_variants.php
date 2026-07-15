<?php

declare(strict_types=1);

/**
 * Manifiestos de variantes de landing (código). Contrato estable para futura CMS.
 *
 * Section catalog ids → reveal_id actual en markup:
 *   testimonios → testimonials, lead_form → cta (v2); v1 usa data-section.
 */
return [
    'catalog' => ['hero', 'trust', 'features', 'pricing', 'testimonios', 'faq', 'lead_form'],

    'reveal_id_map' => [
        'hero' => 'hero',
        'trust' => 'trust',
        'features' => 'features',
        'pricing' => 'pricing',
        'testimonios' => 'testimonials',
        'faq' => 'faq',
        'lead_form' => 'cta',
    ],

    'variants' => [
        'v1' => [
            'slug' => 'v1',
            'shell' => 'v1',
            'status' => 'active',
            'sections' => [
                ['id' => 'hero', 'enabled' => true],
                ['id' => 'trust', 'enabled' => true],
                ['id' => 'features', 'enabled' => true],
                ['id' => 'pricing', 'enabled' => true],
                ['id' => 'testimonios', 'enabled' => true],
                ['id' => 'faq', 'enabled' => true],
                ['id' => 'lead_form', 'enabled' => true],
            ],
            'copy_overrides' => [],
            'seo' => [
                'title' => 'API WhatsApp Business Lebytek | Automatiza Mensajes y Campañas',
                'description' => 'Automatiza WhatsApp para tu negocio con Lebytek. Envía campañas, notificaciones y respuestas automáticas en minutos. Planes desde $2,199/mes. Demo inmediata.',
            ],
            'weight_default' => 0.5,
        ],
        'v2' => [
            'slug' => 'v2',
            'shell' => 'v2',
            'status' => 'active',
            'sections' => [
                ['id' => 'hero', 'enabled' => true],
                ['id' => 'trust', 'enabled' => true],
                ['id' => 'features', 'enabled' => true],
                ['id' => 'pricing', 'enabled' => true],
                ['id' => 'testimonios', 'enabled' => true],
                ['id' => 'faq', 'enabled' => true],
                ['id' => 'lead_form', 'enabled' => true],
            ],
            'copy_overrides' => [],
            'seo' => [
                'title' => 'WhatsApp Business API Lebytek | Campañas y Automatización',
                'description' => 'Conecta WhatsApp Business en minutos. Campañas, notificaciones y respuestas automáticas. Demo inmediata — Lebytek.',
            ],
            'weight_default' => 0.5,
        ],
    ],
];
