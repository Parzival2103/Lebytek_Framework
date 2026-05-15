# Arquitectura del Menú Principal — Sistema para Imprenta de Artículos Personalizados

## Objetivo general

Definir la arquitectura funcional del menú principal de la aplicación web, organizando los módulos de acuerdo con el proceso real del negocio: captación del pedido, validación de información, cotización, producción, entrega, cobro y administración.

---

## Tabla general de menús

| Nombre del menú | Objetivo | Submenús | Acciones principales | Tablas relacionadas | Nivel de prioridad | Rol que lo usa |
|---|---|---|---|---|---|---|
| Dashboard | Mostrar el estado general del negocio y alertas operativas | Resumen, pedidos recientes, producción, entregas próximas, indicadores | Ver indicadores, ver alertas, accesos rápidos, consultar pendientes | pedidos, solicitudes, cotizaciones, ordenes_produccion, entregas, pagos, usuarios | Alta | Administrador, Ventas, Producción |
| Pedidos | Administrar el flujo comercial desde la solicitud hasta el cierre del pedido | Nuevo pedido, Solicitudes, Cotizaciones, Pedidos activos, Archivados | Crear pedido, revisar solicitudes, generar cotizaciones, cambiar estados, archivar | pedidos, solicitudes, cotizaciones, pedidos_detalle, estados_pedido, clientes | Muy alta | Ventas, Administrador |
| Formularios | Gestionar plantillas y respuestas para recopilar pedidos correctamente | Plantillas, Formularios enviados, Respuestas, Temporadas | Crear plantilla, enviar formulario, revisar respuestas, activar temporadas | formularios_plantillas, formularios_envios, formularios_respuestas, temporadas, solicitudes | Muy alta | Ventas, Administrador |
| Clientes | Administrar clientes, contactos e historial comercial | Clientes, Contactos, Historial de pedidos | Crear cliente, editar, agregar contacto, consultar historial | clientes, clientes_contactos, pedidos, solicitudes, cotizaciones | Alta | Ventas, Administración |
| Catálogo | Estandarizar los artículos personalizables y sus variantes | Productos, Categorías, Variantes, Técnicas de personalización, Precios base | Crear productos, clasificar, definir variantes, técnicas y precios | productos, categorias, variantes, tecnicas_personalizacion, precios_productos | Alta | Administrador, Ventas |
| Producción | Dar seguimiento operativo a lo aprobado para fabricación | Órdenes de trabajo, Diseño, Producción, Control de calidad | Crear OT, asignar diseño, mover estados, registrar calidad | ordenes_produccion, pedidos, disenos, produccion_estados, control_calidad | Muy alta | Producción, Administrador |
| Entregas y pagos | Cerrar el ciclo del pedido con entrega, cobro y facturación | Entregas, Cobranza, Facturación | Registrar entrega, pagos, saldos y factura | entregas, pagos, facturas, pedidos, clientes | Alta | Administración, Ventas |
| Reportes | Analizar operación, ventas y rendimiento | Ventas, Pedidos por temporada, Productos más vendidos, Tiempos de entrega | Filtrar, consultar, exportar, comparar periodos | pedidos, pedidos_detalle, productos, temporadas, entregas, pagos | Media | Administrador, Dirección |
| Administración | Configuración interna del sistema y control de acceso | Usuarios, Roles, Configuración, Catálogos auxiliares, Bitácora | Administrar usuarios, permisos, parámetros, catálogos y auditoría | usuarios, roles, permisos, configuraciones, catalogos_auxiliares, bitacora | Media | Administrador |

---

## 1. Dashboard

### Descripción
Es la vista inicial del sistema. Su función es concentrar la información operativa más importante del día para que el usuario identifique rápidamente qué pedidos requieren atención, cuáles están detenidos y qué entregas o cobros están próximos.

### Submenús o bloques
- Resumen general
- Pedidos recientes
- Producción
- Entregas próximas
- Indicadores

### Qué debería mostrar
- pedidos nuevos
- solicitudes pendientes de revisar
- cotizaciones sin enviar
- pedidos en diseño
- pedidos en producción
- entregas del día
- pagos pendientes
- alertas por retraso

### Objetivo funcional
Reducir el tiempo de supervisión y servir como centro de control del negocio.

---

## 2. Pedidos

### Descripción
Es el módulo central del sistema. Aquí se administra el flujo comercial y operativo del pedido, desde la creación manual o conversión de una solicitud, hasta el seguimiento de pedidos activos y su archivo histórico.

### Submenús

#### 2.1 Nuevo pedido
Permite registrar un pedido directamente desde el sistema cuando la información ya fue validada o el cliente ya confirmó el trabajo.

**Acciones clave**
- crear pedido manual
- asociar cliente
- agregar productos y variantes
- capturar fechas compromiso
- registrar anticipo inicial

#### 2.2 Solicitudes
Contiene las solicitudes recibidas desde formularios o capturas preliminares. Sirve como bandeja de revisión antes de convertirlas en cotización o pedido formal.

**Acciones clave**
- revisar información recibida
- validar datos incompletos
- pedir correcciones
- convertir a cotización
- descartar solicitudes

#### 2.3 Cotizaciones
Agrupa propuestas comerciales previas a la aprobación del pedido. Aquí se define costo, condiciones, vigencia y alcance del trabajo.

**Acciones clave**
- generar cotización
- editar importes
- enviar al cliente
- marcar como aprobada o rechazada
- convertir a pedido

#### 2.4 Pedidos activos
Muestra los pedidos que siguen en proceso operativo, ya sea en espera, diseño, producción, control de calidad o listos para entrega.

**Acciones clave**
- consultar estado
- actualizar avance
- ver incidencias
- vincular producción
- ver saldo pendiente

#### 2.5 Archivados
Concentra pedidos cerrados, entregados, cancelados o históricos.

**Acciones clave**
- consultar historial
- reactivar referencia
- duplicar pedido
- filtrar por cliente o temporada

---

## 3. Formularios

### Descripción
Este módulo resuelve el cuello de botella principal del negocio: la recopilación estratégica de pedidos. Permite diseñar plantillas y controlar formularios enviados para que los clientes proporcionen datos completos y estandarizados.

### Submenús

#### 3.1 Plantillas
Plantillas reutilizables para diferentes campañas, productos o temporadas.

**Acciones clave**
- crear plantilla
- definir campos dinámicos
- asociar producto o categoría
- activar o desactivar plantilla

#### 3.2 Formularios enviados
Registro de enlaces o formularios enviados a clientes específicos.

**Acciones clave**
- generar enlace
- asignar cliente
- reenviar formulario
- verificar apertura o uso

#### 3.3 Respuestas
Bandeja de respuestas recibidas desde formularios.

**Acciones clave**
- revisar respuesta
- validar adjuntos
- convertir a solicitud
- etiquetar por temporada o tipo de pedido

#### 3.4 Temporadas
Permite organizar campañas estacionales, catálogos de temporada y formularios especiales.

**Acciones clave**
- crear temporada
- activar catálogo temporal
- relacionar formularios con temporada
- cerrar temporada

---

## 4. Clientes

### Descripción
Gestiona la base comercial del negocio. No solo debe guardar datos básicos del cliente, sino también contactos relacionados y trazabilidad de sus pedidos anteriores.

### Submenús

#### 4.1 Clientes
Directorio principal de clientes.

**Acciones clave**
- alta y edición de cliente
- clasificación por tipo
- notas comerciales
- consulta rápida de pedidos

#### 4.2 Contactos
Permite manejar varios contactos por cliente.

**Acciones clave**
- registrar contacto
- asignar puesto o área
- asociar teléfono y correo
- marcar contacto principal

#### 4.3 Historial de pedidos
Vista consolidada de interacciones y trabajos previos del cliente.

**Acciones clave**
- ver pedidos anteriores
- repetir pedido
- consultar temporadas compradas
- revisar incidencias pasadas

---

## 5. Catálogo

### Descripción
Centraliza los artículos personalizables que el negocio ofrece. Sirve para alimentar formularios, cotizaciones y pedidos con información consistente.

### Submenús

#### 5.1 Productos
Lista principal de artículos que pueden personalizarse.

#### 5.2 Categorías
Agrupa productos por familia comercial o técnica.

#### 5.3 Variantes
Define opciones como talla, color, material, capacidad o presentación.

#### 5.4 Técnicas de personalización
Define procesos como serigrafía, bordado, vinil, sublimado, DTF o tampografía.

#### 5.5 Precios base
Controla precios iniciales por producto, técnica, cantidad o temporada.

### Acciones clave del módulo
- alta y edición de producto
- asociación de categoría
- definición de variantes
- configuración de técnica
- actualización de precios

---

## 6. Producción

### Descripción
Administra la ejecución del pedido una vez aprobado. Aquí el enfoque cambia de lo comercial a lo operativo.

### Submenús

#### 6.1 Órdenes de trabajo
Documento operativo que traduce el pedido en una instrucción de fabricación.

#### 6.2 Diseño
Área para gestionar archivos, artes, revisiones y aprobaciones visuales.

#### 6.3 Producción
Control de los trabajos que ya están en proceso de impresión o personalización.

#### 6.4 Control de calidad
Registro de revisión final, defectos, correcciones y liberación.

### Acciones clave
- generar orden de trabajo
- asignar responsable
- cargar archivo final
- cambiar estado de producción
- registrar incidencias y calidad

---

## 7. Entregas y pagos

### Descripción
Módulo encargado de cerrar el ciclo del pedido, tanto en la entrega física como en la recuperación del ingreso.

### Submenús

#### 7.1 Entregas
Controla entregas programadas, entregas parciales o completas.

#### 7.2 Cobranza
Permite registrar anticipos, pagos parciales, saldos pendientes y liquidaciones.

#### 7.3 Facturación
Relaciona el pedido con comprobantes fiscales y datos fiscales del cliente.

### Acciones clave
- programar entrega
- marcar pedido entregado
- registrar pago
- consultar saldos
- emitir o asociar factura

---

## 8. Reportes

### Descripción
Proporciona visibilidad estratégica para toma de decisiones. Su prioridad inicial es menor que la operación, pero es clave para crecimiento y control.

### Submenús

#### 8.1 Ventas
Análisis de ingresos por periodo, cliente, producto o técnica.

#### 8.2 Pedidos por temporada
Evalúa campañas o temporadas específicas.

#### 8.3 Productos más vendidos
Detecta cuáles artículos se mueven más y cuáles conviene impulsar.

#### 8.4 Tiempos de entrega
Mide cumplimiento operativo y retrasos.

### Acciones clave
- aplicar filtros
- visualizar tendencias
- exportar información
- comparar periodos

---

## 9. Administración

### Descripción
Concentra la configuración del sistema y su gobierno interno.

### Submenús

#### 9.1 Usuarios
Alta, edición y baja lógica de usuarios.

#### 9.2 Roles
Control de permisos y perfiles de acceso.

#### 9.3 Configuración
Parámetros generales del sistema, negocio, textos, estados y opciones.

#### 9.4 Catálogos auxiliares
Valores secundarios que apoyan formularios y módulos.

#### 9.5 Bitácora
Historial de acciones relevantes del sistema y usuarios.

### Acciones clave
- crear usuarios
- asignar permisos
- configurar parámetros
- administrar catálogos
- auditar acciones

---

## Notas estructurales

1. El eje del sistema debe girar alrededor de la captura correcta del pedido.
2. Formularios, solicitudes, cotizaciones y pedidos deben estar conectados como etapas.
3. Producción no debe iniciar sin aprobación comercial mínima.
4. La temporada debe afectar formularios, productos, precios y reportes.
5. El sistema debe permitir crecer hacia automatizaciones futuras sin romper esta estructura.
