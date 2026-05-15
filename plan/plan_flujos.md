# Flujos Funcionales del Sistema — Imprenta de Artículos Personalizados

## Objetivo general

Definir los flujos operativos principales de la aplicación tomando como base la arquitectura de menús propuesta. Estos flujos servirán como referencia para el diseño de vistas, base de datos, permisos, estados y automatizaciones.

---

## 1. Flujo general macro del negocio

```text
Catálogo + Temporadas
        ↓
Formularios
        ↓
Respuestas
        ↓
Solicitudes
        ↓
Cotizaciones
        ↓
Pedido aprobado
        ↓
Producción
        ↓
Control de calidad
        ↓
Entrega
        ↓
Cobranza / Facturación
        ↓
Reporte e historial
```

---

## 2. Flujo de captación por formulario

### Objetivo
Recibir información estructurada desde el cliente sin depender de mensajes sueltos por WhatsApp o llamadas incompletas.

### Flujo
1. El usuario interno entra a **Formularios > Plantillas**.
2. Selecciona o crea una plantilla según temporada, tipo de cliente o producto.
3. Desde **Formularios > Formularios enviados** genera un enlace para un cliente específico.
4. El cliente responde el formulario.
5. La respuesta entra en **Formularios > Respuestas**.
6. El personal revisa si la información está completa.
7. Si es válida, la respuesta se convierte en una **Solicitud**.
8. Si no está completa, se solicita corrección o se marca como pendiente.

### Módulos involucrados
- Formularios
- Clientes
- Pedidos

### Tablas probables
- formularios_plantillas
- formularios_envios
- formularios_respuestas
- clientes
- solicitudes

---

## 3. Flujo de captura manual de pedido

### Objetivo
Permitir que el personal cree un pedido directo cuando la información ya se negoció por otro medio.

### Flujo
1. El usuario entra a **Pedidos > Nuevo pedido**.
2. Busca o crea al cliente.
3. Selecciona productos del catálogo.
4. Define variantes, cantidades y técnica de personalización.
5. Captura fecha requerida, observaciones y posibles adjuntos.
6. Registra anticipo si existe.
7. Guarda el pedido con un estado inicial.
8. El pedido aparece en **Pedidos activos**.

### Módulos involucrados
- Pedidos
- Clientes
- Catálogo
- Entregas y pagos

### Tablas probables
- pedidos
- pedidos_detalle
- clientes
- productos
- variantes
- tecnicas_personalizacion
- pagos

---

## 4. Flujo de revisión de solicitudes

### Objetivo
Transformar respuestas o capturas preliminares en oportunidades comerciales ordenadas.

### Flujo
1. El personal abre **Pedidos > Solicitudes**.
2. Revisa los datos capturados.
3. Verifica producto, técnica, cantidades, archivos y fechas.
4. Si falta información, la marca como incompleta o solicita corrección.
5. Si está correcta, la convierte a cotización.
6. Queda registrada la trazabilidad entre solicitud y cotización.

### Módulos involucrados
- Pedidos
- Formularios
- Clientes

### Resultado esperado
Toda solicitud debe terminar en:
- cotización,
- descarte,
- o espera de información adicional.

---

## 5. Flujo de cotización

### Objetivo
Formalizar precio, condiciones y alcance antes de comprometer producción.

### Flujo
1. Desde **Pedidos > Cotizaciones** se crea o edita la propuesta.
2. Se calculan precios base, técnica, cantidades y extras.
3. Se define vigencia y observaciones.
4. Se envía al cliente.
5. El cliente aprueba o rechaza.
6. Si aprueba, la cotización se convierte en pedido confirmado.
   - *Nota de producto:* en la aplicación, la conversión técnica a pedido (`pedidos.cotizacion_id`) también puede ejecutarse cuando la cotización está en estado **enviada** (además de **aprobada**), siempre que la fecha de vigencia no esté vencida y no exista ya un pedido vinculado.
7. Si rechaza, queda cerrada en historial.

### Módulos involucrados
- Pedidos
- Catálogo
- Clientes

### Tablas probables
- cotizaciones
- cotizaciones_detalle
- precios_productos
- pedidos

---

## 6. Flujo de activación del pedido

### Objetivo
Llevar un pedido aprobado a la fase operativa sin pérdida de información.

### Flujo
1. Una cotización aprobada se convierte en pedido activo.
2. El sistema asigna estado inicial operativo.
3. El pedido aparece en **Pedidos > Pedidos activos**.
4. Se habilita la creación de orden de trabajo.
5. El Dashboard refleja el nuevo trabajo pendiente.

### Estado sugerido
- aprobado
- pendiente de diseño
- listo para producción
- en producción
- calidad
- listo para entrega
- entregado
- archivado

---

## 7. Flujo de diseño

### Objetivo
Gestionar artes, revisiones y aprobación visual antes de producción.

### Flujo
1. Desde **Producción > Diseño** se recibe el pedido pendiente de arte.
2. Se cargan archivos, bocetos o propuestas.
3. Se envía revisión al cliente si aplica.
4. Se registra versión aprobada.
5. Una vez aprobado, el pedido puede pasar a producción.

### Módulos involucrados
- Producción
- Pedidos
- Clientes

### Tablas probables
- disenos
- archivos_pedido
- pedidos
- bitacora

---

## 8. Flujo de orden de trabajo

### Objetivo
Traducir el pedido comercial a instrucciones de fabricación claras.

### Flujo
1. El usuario entra a **Producción > Órdenes de trabajo**.
2. Crea una orden ligada a un pedido.
3. Define responsables, técnica, prioridad y fecha objetivo.
4. Adjunta arte aprobado y especificaciones.
5. La orden queda lista para avanzar en producción.

### Módulos involucrados
- Producción
- Pedidos
- Catálogo

### Tablas probables
- ordenes_produccion
- pedidos
- pedidos_detalle
- tecnicas_personalizacion

---

## 9. Flujo de producción

### Objetivo
Dar seguimiento al avance real del trabajo.

### Flujo
1. El pedido entra a **Producción > Producción**.
2. Se marca estado: preparado, en proceso, detenido o terminado.
3. Se registran incidencias, mermas o reimpresiones.
4. Al finalizar, pasa a control de calidad.

### Estados sugeridos
- pendiente
- preparado
- en proceso
- pausado
- terminado
- enviado a calidad

---

## 10. Flujo de control de calidad

### Objetivo
Asegurar que el producto entregable cumpla con lo pactado.

### Flujo
1. El trabajo terminado entra a **Producción > Control de calidad**.
2. Se revisan cantidades, acabados, impresión, color y empaque.
3. Si hay error, regresa a producción o corrección.
4. Si cumple, se libera para entrega.

### Resultado esperado
Cada pedido debe quedar:
- aprobado para entrega,
- o regresado a corrección.

---

## 11. Flujo de entrega

### Objetivo
Controlar la salida física del pedido al cliente.

### Flujo
1. El pedido liberado aparece en **Entregas y pagos > Entregas**.
2. Se agenda fecha o método de entrega.
3. Se registra entrega parcial o total.
4. Se obtiene evidencia o confirmación si aplica.
5. El estado cambia a entregado o parcialmente entregado.

### Tablas probables
- entregas
- pedidos
- clientes

---

## 12. Flujo de cobranza

### Objetivo
Registrar y controlar ingresos del pedido.

### Flujo
1. Desde **Entregas y pagos > Cobranza** se consultan saldos pendientes.
2. Se registra anticipo, abono o liquidación.
3. El sistema recalcula saldo.
4. Si queda liquidado, se marca como pagado.

### Tablas probables
- pagos
- pedidos
- clientes

---

## 13. Flujo de facturación

### Objetivo
Relacionar el pedido con el comprobante fiscal correspondiente.

### Flujo
1. Desde **Entregas y pagos > Facturación** se identifica el pedido entregado o pagado.
2. Se validan datos fiscales del cliente.
3. Se registra factura emitida o referencia fiscal.
4. El pedido queda ligado a su comprobante.

### Tablas probables
- facturas
- clientes_datos_fiscales
- pedidos

---

## 14. Flujo de reportes

### Objetivo
Convertir la operación diaria en información útil para decisión.

### Flujo
1. El usuario entra a **Reportes**.
2. Selecciona tipo de reporte.
3. Aplica filtros por fecha, temporada, cliente, producto o estado.
4. Consulta resultados.
5. Exporta si es necesario.

### Reportes iniciales sugeridos
- ventas por periodo
- pedidos por temporada
- productos más vendidos
- tiempos de entrega
- pedidos cancelados
- clientes frecuentes

---

## 15. Flujo administrativo

### Objetivo
Mantener control de acceso, parámetros y trazabilidad del sistema.

### Flujo
1. Desde **Administración > Usuarios** se da de alta al personal.
2. En **Roles** se asignan permisos.
3. En **Configuración** se definen parámetros del negocio.
4. En **Catálogos auxiliares** se mantienen listas secundarias.
5. En **Bitácora** se consulta quién hizo qué y cuándo.

---

## 16. Flujo de consulta histórica

### Objetivo
Recuperar información pasada para reordenes, seguimiento o análisis.

### Flujo
1. El usuario consulta **Clientes > Historial de pedidos** o **Pedidos > Archivados**.
2. Filtra por cliente, temporada, producto o fecha.
3. Revisa especificaciones pasadas.
4. Duplica pedido o reutiliza referencia si hace falta.

---

## 17. Flujo resumido por módulos

### Dashboard
Monitorea y dirige.

### Pedidos
Captura, valida, cotiza y controla.

### Formularios
Estandariza la recopilación de información.

### Clientes
Conserva identidad comercial e historial.

### Catálogo
Estructura oferta, variantes y precios.

### Producción
Ejecuta lo aprobado.

### Entregas y pagos
Cierra la operación.

### Reportes
Analiza resultados.

### Administración
Gobierna el sistema.

---

## 18. Reglas estructurales recomendadas

1. Una respuesta de formulario no debe convertirse automáticamente en producción.
2. Toda producción debe originarse en un pedido aprobado.
3. Todo pedido debe estar ligado a un cliente.
4. Todo pedido debe tener al menos un detalle de producto.
5. Una temporada puede afectar formularios, productos, precios y reportes.
6. La bitácora debe registrar cambios críticos de estado.
7. Los roles deben limitar acceso según área: ventas, producción, administración o dirección.
