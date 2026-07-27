-- Idempotent plantilla catalog seed by clave.

INSERT INTO `dom_mkt_plantillas` (`clave`, `asunto`, `cuerpo`, `activo`)
SELECT 'lead_welcome', 'Recibimos tu solicitud — Lebytek', '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibimos tu solicitud</title>
</head>
<body style="margin:0; padding:24px; background:#f0f2f5; font-family:Arial, Helvetica, sans-serif; color:#212529;">

<div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent; mso-hide:all;">
    Recibimos tu solicitud. Un especialista de Lebytek te contactará pronto con los siguientes pasos.</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td align="center">
            <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0"
                style="background:#ffffff; border-radius:12px; overflow:hidden;">
                <tr>
                    <td style="background:#0f172a; padding:32px; text-align:center;">
                        <p style="margin:0 0 8px; color:rgba(255,255,255,0.85); font-size:13px; font-weight:600; letter-spacing:0.04em; text-transform:uppercase;">
                            Lebytek                        </p>
                        <h1 style="margin:0; color:#ffffff; font-size:26px; line-height:1.25;">
                            Recibimos tu solicitud                        </h1>
                                                <p style="margin:12px 0 0; color:rgba(255,255,255,0.88); font-size:15px; line-height:1.5;">
                            Estamos preparando tu demo de WhatsApp API                        </p>
                                            </td>
                </tr>
                <tr>
                    <td style="padding:32px;">

<p style="margin:0 0 12px; font-size:16px;">
    Hola <strong>{{nombre}}</strong>,
</p>

<p style="margin:0 0 24px; line-height:1.7;">
    Gracias por contactarnos. Hemos recibido tu solicitud de demo y un especialista de
    <strong>Lebytek</strong> la revisará en breve para ayudarte a integrar
    <strong>WhatsApp en tu negocio</strong> con nuestra API.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <tr>
        <td align="center" style="padding:20px; background:#f8f9fa; border:1px solid #dee2e6; border-radius:8px;">
            <p style="margin:0 0 12px; font-size:14px; color:#6c757d;">Tu código de verificación:</p>
            <p style="margin:0 0 12px; font-family:Consolas, Monaco, monospace; font-size:28px; font-weight:bold; letter-spacing:4px; color:#0f172a;">
                {{codigo}}            </p>
            <p style="margin:0; font-size:14px; color:#6c757d;">El código caduca en 24 horas</p>
        </td>
    </tr>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <a href="{{verify_url}}"
   style="
        display:inline-block;
        background:#0d6efd;
        color:#ffffff;
        text-decoration:none;
        padding:14px 24px;
        border-radius:8px;
        font-weight:bold;
   ">
    Verificar mi correo</a>
        </td>
    </tr>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <tr>
        <td style="padding:12px 0; border-bottom:1px solid #e9ecef;">
            <span style="color:#25D366; font-weight:bold; margin-right:8px;">&#10003;</span>
            <strong>API WhatsApp</strong> — envía y recibe mensajes desde tus sistemas
        </td>
    </tr>
    <tr>
        <td style="padding:12px 0; border-bottom:1px solid #e9ecef;">
            <span style="color:#25D366; font-weight:bold; margin-right:8px;">&#10003;</span>
            <strong>Campañas masivas</strong> — promociones y avisos con seguimiento
        </td>
    </tr>
    <tr>
        <td style="padding:12px 0;">
            <span style="color:#25D366; font-weight:bold; margin-right:8px;">&#10003;</span>
            <strong>Integración rápida</strong> — conecta tu app en minutos, no semanas
        </td>
    </tr>
</table>

<div style="
    background:#f0fdf4;
    border-left:4px solid #25D366;
    padding:16px;
    border-radius:6px;
    margin-bottom:24px;
">
    <strong>¿Qué sigue?</strong>
    <ul style="margin:12px 0 0 18px; padding:0; line-height:1.7;">
                <li>Verifica tu correo con el código o el enlace de arriba.</li>
                <li>Revisamos tu solicitud y validamos tu caso de uso.</li>
                <li>Recibirás por correo tus credenciales de acceso a la API.</li>
            </ul>
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <a href="{{landing_url}}#paquetes"
   style="
        display:inline-block;
        background:#25D366;
        color:#ffffff;
        text-decoration:none;
        padding:14px 24px;
        border-radius:8px;
        font-weight:bold;
   ">
    Ver paquetes y precios</a>
        </td>
    </tr>
</table>

<p style="margin:0; font-size:14px; color:#6c757d; line-height:1.7;">
    ¿Tienes dudas? Escríbenos a
    <a href="mailto:soporte@lebytek.com" style="color:#0d6efd;">soporte@lebytek.com</a>
    y con gusto te ayudamos.
</p>

                    </td>
                </tr>
                <tr>
                    <td style="background:#f8f9fa; padding:24px; text-align:center; font-size:13px; color:#6c757d; line-height:1.7;">
                        &copy; 2026 Lebytek<br>
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
', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_plantillas` WHERE `clave` = 'lead_welcome');

INSERT INTO `dom_mkt_plantillas` (`clave`, `asunto`, `cuerpo`, `activo`)
SELECT 'lead_api_credentials', 'Tus credenciales demo — Lebytek', '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tu acceso a la API está listo</title>
</head>
<body style="margin:0; padding:24px; background:#f0f2f5; font-family:Arial, Helvetica, sans-serif; color:#212529;">

<div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent; mso-hide:all;">
    Tu demo está lista. Aquí tienes tu token y la base URL para conectar con la API de Lebytek.</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td align="center">
            <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0"
                style="background:#ffffff; border-radius:12px; overflow:hidden;">
                <tr>
                    <td style="background:#0d6efd; padding:32px; text-align:center;">
                        <p style="margin:0 0 8px; color:rgba(255,255,255,0.85); font-size:13px; font-weight:600; letter-spacing:0.04em; text-transform:uppercase;">
                            Framework Lebytek                        </p>
                        <h1 style="margin:0; color:#ffffff; font-size:26px; line-height:1.25;">
                            Tu acceso a la API está listo                        </h1>
                                                <p style="margin:12px 0 0; color:rgba(255,255,255,0.88); font-size:15px; line-height:1.5;">
                            Bienvenido a la plataforma de mensajería de Lebytek                        </p>
                                            </td>
                </tr>
                <tr>
                    <td style="padding:32px;">

<p style="margin:0 0 12px; font-size:16px;">
    Hola <strong>{{nombre}}</strong>,
</p>

<p style="margin:0 0 24px; line-height:1.7;">
    Tu solicitud ha sido aprobada y ya puedes comenzar a utilizar nuestra
    <strong>API de WhatsApp</strong>. A continuación encontrarás tus credenciales de acceso.
</p>

<p style="margin:0 0 8px;"><strong>Base URL</strong></p>
<p style="margin:0 0 10px; font-size:13px; line-height:1.5; color:#6c757d;">
    Copia esta URL tal cual para el prefijo de tus peticiones a la API.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:12px;">
    <tr>
        <td style="
            background:#f8f9fa;
            border:1px solid #dee2e6;
            border-radius:8px;
            padding:12px 14px;
        ">
            <input
                type="text"
                readonly
                value="{{api_base_url}}"
                onclick="this.focus();this.select();"
                style="
                    width:100%;
                    box-sizing:border-box;
                    border:none;
                    background:transparent;
                    font-family:Consolas, Monaco, ''Courier New'', monospace;
                    font-size:14px;
                    line-height:1.5;
                    color:#212529;
                    padding:0;
                    margin:0;
                    outline:none;
                    -webkit-user-select:all;
                    user-select:all;
                "
            />
        </td>
    </tr>
</table>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">
    <tr>
        <td align="left">
            <a
                href="#"
                onclick="event.preventDefault();var el=this.closest(''table'').previousElementSibling.querySelector(''input'');if(el){el.focus();el.select();try{document.execCommand(''copy'');}catch(e){}}return false;"
                style="
                    display:inline-block;
                    background:#0d6efd;
                    color:#ffffff !important;
                    text-decoration:none;
                    font-size:14px;
                    font-weight:600;
                    padding:10px 18px;
                    border-radius:6px;
                "
            >Copiar al portapapeles</a>
        </td>
    </tr>
    <tr>
        <td style="padding-top:8px; font-size:12px; color:#6c757d;">
            Si el botón no funciona en tu cliente de correo, toca el campo de arriba y usa <strong>Ctrl+C</strong> (o <strong>Cmd+C</strong>).
        </td>
    </tr>
</table>

<p style="margin:0 0 8px;"><strong>Token de acceso</strong></p>
<p style="margin:0 0 10px; font-size:13px; line-height:1.5; color:#6c757d;">
    Copia <strong>todo</strong> el texto del recuadro, incluido el número y el símbolo <strong>|</strong> (ej. <code style="font-family:monospace;">15|abc…</code>). Ese valor completo es tu token Bearer.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:12px;">
    <tr>
        <td style="
            background:#f8f9fa;
            border:1px solid #dee2e6;
            border-radius:8px;
            padding:12px 14px;
        ">
            <input
                type="text"
                readonly
                value="{{token}}"
                onclick="this.focus();this.select();"
                style="
                    width:100%;
                    box-sizing:border-box;
                    border:none;
                    background:transparent;
                    font-family:Consolas, Monaco, ''Courier New'', monospace;
                    font-size:14px;
                    line-height:1.5;
                    color:#212529;
                    padding:0;
                    margin:0;
                    outline:none;
                    -webkit-user-select:all;
                    user-select:all;
                "
            />
        </td>
    </tr>
</table>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">
    <tr>
        <td align="left">
            <a
                href="#"
                onclick="event.preventDefault();var el=this.closest(''table'').previousElementSibling.querySelector(''input'');if(el){el.focus();el.select();try{document.execCommand(''copy'');}catch(e){}}return false;"
                style="
                    display:inline-block;
                    background:#0d6efd;
                    color:#ffffff !important;
                    text-decoration:none;
                    font-size:14px;
                    font-weight:600;
                    padding:10px 18px;
                    border-radius:6px;
                "
            >Copiar al portapapeles</a>
        </td>
    </tr>
    <tr>
        <td style="padding-top:8px; font-size:12px; color:#6c757d;">
            Si el botón no funciona en tu cliente de correo, toca el campo de arriba y usa <strong>Ctrl+C</strong> (o <strong>Cmd+C</strong>).
        </td>
    </tr>
</table>

<div style="
    background:#eef7ff;
    border-left:4px solid #0d6efd;
    padding:16px;
    border-radius:6px;
    margin-bottom:24px;
">
    <strong>Próximos pasos</strong>
    <ul style="margin:12px 0 0 18px; padding:0; line-height:1.7;">
                <li>Copia la Base URL y el Token desde los recuadros de arriba (usa el botón Copiar o selecciona todo el texto).</li>
                <li>Usa el token completo en el header &lt;code style=&quot;font-family:monospace;&quot;&gt;Authorization: Bearer &amp;lt;token&amp;gt;&lt;/code&gt;.</li>
                <li>Consulta la documentación de integración para ver ejemplos y endpoints.</li>
                <li>Abre el &lt;strong&gt;Sandbox demo&lt;/strong&gt; en la documentación: pega tu token, escanea el QR y envía tu primer WhatsApp en minutos.</li>
            </ul>
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <a href="{{docs_url}}/#sandbox"
   style="
        display:inline-block;
        background:#0d6efd;
        color:#ffffff;
        text-decoration:none;
        padding:14px 24px;
        border-radius:8px;
        font-weight:bold;
   ">
    Probar demo (5 min)</a>
        </td>
    </tr>
</table>


<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <a href="{{packages_url}}"
   style="
        display:inline-block;
        background:#0d6efd;
        color:#ffffff;
        text-decoration:none;
        padding:14px 24px;
        border-radius:8px;
        font-weight:bold;
   ">
    Ver paquetes</a>
        </td>
    </tr>
</table>

<div style="
    background:#fff8e6;
    border-left:4px solid #ffc107;
    padding:16px;
    border-radius:6px;
    margin-bottom:24px;
    font-size:14px;
    line-height:1.7;
">
    <strong>Importante:</strong><br><br>• Estas credenciales son personales y confidenciales.<br>• El token de acceso <strong>no volverá a mostrarse</strong>.<br>• No compartas tus credenciales con terceros.<br>• Si sospechas que tu token fue comprometido, contacta a <a href="mailto:soporte@lebytek.com" style="color:#856404;">soporte@lebytek.com</a>.<br>• El uso de la API está sujeto a nuestras políticas de seguridad y uso responsable.</div>

<p style="margin:0; line-height:1.7;">
    Gracias por confiar en <strong>Lebytek</strong>. Estamos emocionados de formar parte de tu proyecto
    y ayudarte a integrar WhatsApp en tus aplicaciones.
</p>

                    </td>
                </tr>
                <tr>
                    <td style="background:#f8f9fa; padding:24px; text-align:center; font-size:13px; color:#6c757d; line-height:1.7;">
                        &copy; 2026 Framework Lebytek<br>
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
', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_plantillas` WHERE `clave` = 'lead_api_credentials');

INSERT INTO `dom_mkt_plantillas` (`clave`, `asunto`, `cuerpo`, `activo`)
SELECT 'membership_activated', 'Tu membresía Lebytek está activa', '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membresía activada</title>
</head>
<body style="margin:0; padding:24px; background:#f0f2f5; font-family:Arial, Helvetica, sans-serif; color:#212529;">

<div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent; mso-hide:all;">
    Tu membresía Lebytek está activa. Aquí tienes tu nuevo token de acceso.</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td align="center">
            <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0"
                style="background:#ffffff; border-radius:12px; overflow:hidden;">
                <tr>
                    <td style="background:#198754; padding:32px; text-align:center;">
                        <p style="margin:0 0 8px; color:rgba(255,255,255,0.85); font-size:13px; font-weight:600; letter-spacing:0.04em; text-transform:uppercase;">
                            Framework Lebytek                        </p>
                        <h1 style="margin:0; color:#ffffff; font-size:26px; line-height:1.25;">
                            Membresía activada                        </h1>
                                                <p style="margin:12px 0 0; color:rgba(255,255,255,0.88); font-size:15px; line-height:1.5;">
                            Gracias por confiar en Lebytek                        </p>
                                            </td>
                </tr>
                <tr>
                    <td style="padding:32px;">

<p style="margin:0 0 12px; font-size:16px;">
    Hola <strong>{{nombre}}</strong>,
</p>

<p style="margin:0 0 24px; line-height:1.7;">
    Tu pago fue confirmado. Tu cuenta WhatsApp existente se actualizó al plan
    <strong>{{plan}}</strong> ({{ciclo}}).
    Cuota: <strong>${{cuota}} MXN</strong>.
</p>

<p style="margin:0 0 8px;"><strong>Base URL</strong></p>
<p style="margin:0 0 10px; font-size:13px; line-height:1.5; color:#6c757d;">
    Usa esta URL como prefijo de tus peticiones a la API.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:12px;">
    <tr>
        <td style="
            background:#f8f9fa;
            border:1px solid #dee2e6;
            border-radius:8px;
            padding:12px 14px;
        ">
            <input
                type="text"
                readonly
                value="{{api_base_url}}"
                onclick="this.focus();this.select();"
                style="
                    width:100%;
                    box-sizing:border-box;
                    border:none;
                    background:transparent;
                    font-family:Consolas, Monaco, ''Courier New'', monospace;
                    font-size:14px;
                    line-height:1.5;
                    color:#212529;
                    padding:0;
                    margin:0;
                    outline:none;
                    -webkit-user-select:all;
                    user-select:all;
                "
            />
        </td>
    </tr>
</table>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">
    <tr>
        <td align="left">
            <a
                href="#"
                onclick="event.preventDefault();var el=this.closest(''table'').previousElementSibling.querySelector(''input'');if(el){el.focus();el.select();try{document.execCommand(''copy'');}catch(e){}}return false;"
                style="
                    display:inline-block;
                    background:#0d6efd;
                    color:#ffffff !important;
                    text-decoration:none;
                    font-size:14px;
                    font-weight:600;
                    padding:10px 18px;
                    border-radius:6px;
                "
            >Copiar al portapapeles</a>
        </td>
    </tr>
    <tr>
        <td style="padding-top:8px; font-size:12px; color:#6c757d;">
            Si el botón no funciona en tu cliente de correo, toca el campo de arriba y usa <strong>Ctrl+C</strong> (o <strong>Cmd+C</strong>).
        </td>
    </tr>
</table>

<p style="margin:0 0 8px;"><strong>Nuevo token de acceso</strong></p>
<p style="margin:0 0 10px; font-size:13px; line-height:1.5; color:#6c757d;">
    Copia el token completo para el header Authorization: Bearer.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:12px;">
    <tr>
        <td style="
            background:#f8f9fa;
            border:1px solid #dee2e6;
            border-radius:8px;
            padding:12px 14px;
        ">
            <input
                type="text"
                readonly
                value="{{token}}"
                onclick="this.focus();this.select();"
                style="
                    width:100%;
                    box-sizing:border-box;
                    border:none;
                    background:transparent;
                    font-family:Consolas, Monaco, ''Courier New'', monospace;
                    font-size:14px;
                    line-height:1.5;
                    color:#212529;
                    padding:0;
                    margin:0;
                    outline:none;
                    -webkit-user-select:all;
                    user-select:all;
                "
            />
        </td>
    </tr>
</table>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">
    <tr>
        <td align="left">
            <a
                href="#"
                onclick="event.preventDefault();var el=this.closest(''table'').previousElementSibling.querySelector(''input'');if(el){el.focus();el.select();try{document.execCommand(''copy'');}catch(e){}}return false;"
                style="
                    display:inline-block;
                    background:#0d6efd;
                    color:#ffffff !important;
                    text-decoration:none;
                    font-size:14px;
                    font-weight:600;
                    padding:10px 18px;
                    border-radius:6px;
                "
            >Copiar al portapapeles</a>
        </td>
    </tr>
    <tr>
        <td style="padding-top:8px; font-size:12px; color:#6c757d;">
            Si el botón no funciona en tu cliente de correo, toca el campo de arriba y usa <strong>Ctrl+C</strong> (o <strong>Cmd+C</strong>).
        </td>
    </tr>
</table>

<div style="
    background:#fff8e6;
    border-left:4px solid #ffc107;
    padding:16px;
    border-radius:6px;
    margin-bottom:24px;
    font-size:14px;
    line-height:1.7;
">
    <strong>Importante:</strong><br><br>• El token de demo anterior fue revocado por seguridad.<br>• Este token <strong>no volverá a mostrarse</strong>. Guárdalo en un lugar seguro.<br>• No necesitas escanear un nuevo QR: tu instancia WhatsApp sigue siendo la misma.</div>

<p style="margin:0; line-height:1.7;">
    Si tienes dudas, escríbenos a
    <a href="mailto:soporte@lebytek.com" style="color:#0d6efd;">soporte@lebytek.com</a>.
</p>

                    </td>
                </tr>
                <tr>
                    <td style="background:#f8f9fa; padding:24px; text-align:center; font-size:13px; color:#6c757d; line-height:1.7;">
                        &copy; 2026 Framework Lebytek<br>
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
', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_plantillas` WHERE `clave` = 'membership_activated');

INSERT INTO `dom_mkt_plantillas` (`clave`, `asunto`, `cuerpo`, `activo`)
SELECT 'membership_payment_failed', 'Problema con tu pago — acción requerida', '<p>Hola {{nombre}},</p><p>No pudimos cobrar tu plan {{plan}} ({{ciclo}}). Tienes {{grace_hours}} horas para actualizar el pago:</p><p><a href="{{retry_url}}">Reintentar pago</a></p>', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_plantillas` WHERE `clave` = 'membership_payment_failed');

INSERT INTO `dom_mkt_plantillas` (`clave`, `asunto`, `cuerpo`, `activo`)
SELECT 'membership_cancelled_reactivate', 'Tu cuenta fue cancelada — puedes reactivar', '<p>Hola {{nombre}},</p><p>Cancelamos {{cuenta}} por falta de pago. Reactiva cuando quieras:</p><p><a href="{{retry_url}}">Reactivar membresía</a></p>', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_plantillas` WHERE `clave` = 'membership_cancelled_reactivate');

-- Align legacy stub key if present
UPDATE `dom_mkt_plantillas`
SET `clave` = 'lead_welcome',
    `asunto` = 'Recibimos tu solicitud — Lebytek'
WHERE `clave` = 'lead_autoresponder'
  AND NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `dom_mkt_plantillas` WHERE `clave` = 'lead_welcome') t);
