# Diccionario de Dominio

## 1. Propósito del documento

El diccionario de dominio define el lenguaje oficial del sistema.
Su objetivo es asegurar que las entidades, conceptos, módulos y términos del negocio
se nombren de la misma forma en toda la aplicación, incluyendo:

- Base de datos
- Backend
- APIs
- Frontend
- Documentación
- Reportes
- Integraciones
- IA
- Automatizaciones

Este documento evita inconsistencias de nombres y define el lenguaje oficial del sistema.

---

## 2. Reglas generales del dominio

1. Cada entidad del sistema debe tener un nombre oficial.
2. Ese nombre debe usarse en todo el sistema.
3. No se deben usar sinónimos para la misma entidad.
4. El nombre de la entidad debe existir en:
   - Base de datos
   - Código
   - API
   - Documentación
5. Las relaciones entre entidades deben estar definidas.
6. El dominio representa el negocio, no la tecnología.
7. Este documento debe actualizarse cuando se agreguen nuevas entidades.

---

## 3. Estructura del diccionario de entidades

Cada entidad del sistema debe documentarse con la siguiente estructura:

Entidad:
Descripción:
Tabla SQL:
Clase:
Endpoint API:
Campos principales:
Relaciones:
Notas:

---

## 4. Entidades del sistema

### Usuario
Descripción:
Representa a una persona que puede acceder al sistema.

Tabla SQL:
usuarios

Clase:
Usuario

Endpoint API:
/api/usuarios

Campos principales:
- id
- nombre
- apellido
- email
- password
- avatar
- activo
- ultimo_acceso
- created_at
- updated_at

Relaciones:
- Un usuario puede tener muchos roles (tabla pivote usuarios_roles)
- Un usuario puede crear registros en bitácora

Notas:
Los usuarios controlan el acceso al sistema.

---

### Rol
Descripción:
Define los permisos y niveles de acceso del sistema.

Tabla SQL:
roles

Clase:
Rol

Endpoint API:
/api/roles

Campos principales:
- id
- nombre
- slug
- descripcion
- activo
- created_at

Relaciones:
- Un rol tiene muchos usuarios (tabla pivote usuarios_roles)
- Un rol tiene muchos permisos (tabla pivote roles_permisos)

---

### Permiso
Descripción:
Representa una acción específica que puede realizarse en el sistema. Se asigna a roles.

Tabla SQL:
permisos

Clase:
Permiso

Endpoint API:
/api/permisos

Campos principales:
- id
- nombre
- slug
- modulo
- descripcion
- created_at

Relaciones:
- Un permiso pertenece a muchos roles (tabla pivote roles_permisos)

Notas:
Los permisos se agrupan por módulo. Ejemplo: "pedidos.ver", "pedidos.crear".

---

### Categoria
Descripción:
Agrupación o clasificación de productos del catálogo.

Tabla SQL:
categorias

Clase:
Categoria

Endpoint API:
/api/categorias

Campos principales:
- id
- nombre
- descripcion
- icono
- activo
- created_at

Relaciones:
- Una categoría tiene muchos productos

---

### Producto
Descripción:
Representa un producto o servicio que puede venderse o utilizarse.

Tabla SQL:
productos

Clase:
Producto

Endpoint API:
/api/productos

Campos principales:
- id
- categoria_id
- nombre
- descripcion
- imagenes
- activo
- created_at
- updated_at

Relaciones:
- Un producto pertenece a una categoría
- Un producto puede tener variantes
- Un producto puede tener precios por técnica/temporada
- Un producto puede estar en pedidos

---

### Variante
Descripción:
Variación de un producto (tamaño, color, material, etc.).

Tabla SQL:
variantes

Clase:
Variante

Endpoint API:
/api/variantes

Campos principales:
- id
- producto_id
- nombre
- valor
- created_at

Relaciones:
- Una variante pertenece a un producto

---

### TecnicaPersonalizacion
Descripción:
Método o técnica utilizada para personalizar un producto (serigrafía, bordado, sublimación, etc.).

Tabla SQL:
tecnicas_personalizacion

Clase:
TecnicaPersonalizacion

Endpoint API:
/api/tecnicas-personalizacion

Campos principales:
- id
- nombre
- descripcion
- activo
- created_at

Relaciones:
- Una técnica puede estar asociada a precios de productos
- Una técnica puede usarse en órdenes de producción

---

### PrecioProducto
Descripción:
Precio de un producto según técnica de personalización, temporada y cantidad mínima.

Tabla SQL:
precios_productos

Clase:
PrecioProducto

Campos principales:
- id
- producto_id
- tecnica_id
- temporada_id
- precio_unitario
- cantidad_minima
- created_at

Relaciones:
- Un precio pertenece a un producto
- Un precio puede estar asociado a una técnica
- Un precio puede estar asociado a una temporada

---

### Temporada
Descripción:
Período de tiempo que agrupa formularios, precios y reportes.

Tabla SQL:
temporadas

Clase:
Temporada

Endpoint API:
/api/temporadas

Campos principales:
- id
- nombre
- descripcion
- fecha_inicio
- fecha_fin
- activo
- created_at
- updated_at

Relaciones:
- Una temporada puede tener formularios
- Una temporada puede afectar precios de productos

---

### Formulario envío y captura
Descripción:
Un **envío** (`formularios_envios`) es el enlace público (token) generado a partir de una plantilla. Una **captura** (`formularios_capturas`) es un envío completo del formulario por una persona en ese enlace. Cada valor de campo (`formularios_respuestas`) pertenece a una captura concreta.

Tabla SQL:
formularios_envios, formularios_capturas, formularios_respuestas

Campos relevantes:
- `formularios_envios.modo`: `unico` (solo una captura por enlace) o `multiple` (varias capturas por el mismo enlace, p. ej. encuesta).
- `formularios_envios.respondido_at`: marca la primera vez que existió al menos una captura.

Relaciones:
- Un envío tiene muchas capturas (modo múltiple) o una (modo único en uso típico).
- Una captura tiene muchas filas en `formularios_respuestas` (una por campo respondido).

Notas:
En listados de administración, «Respuestas» globales enumeran **capturas** (cada fila es un llenado), no envíos.

---

### Cliente
Descripción:
Representa una persona o empresa que recibe servicios o compra productos.

Tabla SQL:
clientes

Clase:
Cliente

Endpoint API:
/api/clientes

Campos principales:
- id
- nombre
- tipo (empresa/persona)
- rfc
- email
- telefono
- notas
- activo
- created_at
- updated_at

Relaciones:
- Un cliente puede tener contactos
- Un cliente puede tener datos fiscales
- Un cliente puede tener solicitudes
- Un cliente puede tener cotizaciones
- Un cliente puede tener pedidos

---

### Solicitud
Descripción:
Petición o requerimiento inicial de un cliente antes de generar cotización.

Tabla SQL:
solicitudes

Clase:
Solicitud

Endpoint API:
/api/solicitudes

Campos principales:
- id
- cliente_id
- temporada_id
- origen
- estado
- notas
- creador_id
- created_at
- updated_at

Relaciones:
- Una solicitud pertenece a un cliente
- Una solicitud puede pertenecer a una temporada
- Una solicitud puede generar cotizaciones

---

### Cotizacion
Descripción:
Propuesta comercial con detalle de productos, cantidades y precios para un cliente.

Tabla SQL:
cotizaciones

Clase:
Cotizacion

Endpoint API:
/api/cotizaciones

Campos principales:
- id
- solicitud_id
- cliente_id
- folio
- estado
- vigencia
- subtotal
- descuento
- total
- creador_id
- created_at
- updated_at

Relaciones:
- Una cotización pertenece a un cliente
- Una cotización puede venir de una solicitud
- Una cotización tiene detalle (cotizaciones_detalle)
- Una cotización en estado **enviada** o **aprobada**, con vigencia no vencida, puede convertirse en pedido mediante la acción explícita en la UI (un solo pedido por cotización; ver `pedidos.cotizacion_id`)

Documentación relacionada:
- Modelo de PDF, personalización del emisor (cabecera, logo, folio) y paridad con creación de pedido: [pedidos_cotizaciones_pdf.md](pedidos_cotizaciones_pdf.md).

---

### Pedido
Descripción:
Orden confirmada de compra con productos, cantidades y precios.

Tabla SQL:
pedidos

Clase:
Pedido

Endpoint API:
/api/pedidos

Campos principales:
- id
- cotizacion_id
- cliente_id
- folio
- estado
- fecha_requerida
- notas
- subtotal
- descuento
- total
- anticipo
- creador_id
- created_at
- updated_at

Relaciones:
- Un pedido pertenece a un cliente
- Un pedido puede venir de una cotización
- Un pedido tiene detalle (pedidos_detalle)
- Un pedido tiene historial de estados (estados_pedido)
- Un pedido puede tener diseños
- Un pedido puede tener órdenes de producción
- Un pedido puede tener entregas
- Un pedido puede tener pagos
- Un pedido puede tener facturas

---

### Diseno
Descripción:
Archivo de diseño gráfico asociado a un pedido, con control de versiones y aprobación.

Tabla SQL:
disenos

Clase:
Diseno

Campos principales:
- id
- pedido_id
- version
- archivo
- estado
- observaciones
- aprobado_at
- creador_id
- created_at

Relaciones:
- Un diseño pertenece a un pedido

---

### OrdenProduccion
Descripción:
Instrucción de trabajo para producir los productos de un pedido.

Tabla SQL:
ordenes_produccion

Clase:
OrdenProduccion

Campos principales:
- id
- pedido_id
- folio
- responsable_id
- tecnica_id
- prioridad
- fecha_objetivo
- estado
- observaciones
- creador_id
- created_at
- updated_at

Relaciones:
- Una orden pertenece a un pedido
- Una orden puede tener un responsable (usuario)
- Una orden puede tener controles de calidad

---

### ControlCalidad
Descripción:
Resultado de la revisión de calidad de una orden de producción.

Tabla SQL:
control_calidad

Clase:
ControlCalidad

Campos principales:
- id
- orden_id
- resultado
- observaciones
- revisado_por_id
- created_at

Relaciones:
- Un control de calidad pertenece a una orden de producción

---

### Entrega
Descripción:
Registro de entrega de productos de un pedido al cliente.

Tabla SQL:
entregas

Clase:
Entrega

Endpoint API:
/api/entregas

Campos principales:
- id
- pedido_id
- tipo (parcial/total)
- fecha_programada
- fecha_real
- estado
- observaciones
- creador_id
- created_at

Relaciones:
- Una entrega pertenece a un pedido

---

### Pago
Descripción:
Representa un abono o pago realizado para un pedido.

Tabla SQL:
pagos

Clase:
Pago

Endpoint API:
/api/pagos

Campos principales:
- id
- pedido_id
- tipo (anticipo/abono/liquidacion/devolucion)
- monto
- referencia
- observaciones
- creador_id
- created_at

Relaciones:
- Un pago pertenece a un pedido

---

### Factura
Descripción:
Documento fiscal o comprobante de cobro asociado a un pedido.

Tabla SQL:
facturas

Clase:
Factura

Endpoint API:
/api/facturas

Campos principales:
- id
- pedido_id
- folio
- uuid_fiscal
- monto
- estado
- creador_id
- created_at

Relaciones:
- Una factura pertenece a un pedido

---

### Configuracion
Descripción:
Parámetro de configuración del sistema almacenado como clave-valor.

Tabla SQL:
configuraciones

Clase:
(sin entidad de dominio — acceso via ConfiguracionService)

Campos principales:
- id
- clave
- valor
- tipo
- descripcion
- updated_at

Notas:
Se accede a través de ConfiguracionService en la capa Application.

---

### Bitacora
Descripción:
Registro de auditoría de acciones realizadas en el sistema.

Tabla SQL:
bitacoras

Clase:
(sin entidad de dominio — acceso via BitacoraRepository)

Campos principales:
- id
- usuario_id
- accion
- tabla
- registro_id
- detalle
- ip
- created_at

Relaciones:
- Una entrada de bitácora puede pertenecer a un usuario

---

## 5. Reglas del lenguaje del dominio

Estas reglas son obligatorias:

1. Si la entidad se llama Cliente, debe llamarse Cliente en todo el sistema.
2. No usar customer, client, persona para la misma entidad.
3. Si la tabla es clientes, la clase debe ser Cliente.
4. Si la API es /api/clientes, no debe existir /api/customer.
5. El lenguaje del dominio debe ser consistente en:
   - Base de datos
   - Backend
   - API
   - Frontend
   - Reportes
   - Documentación
6. Las entidades deben representar el negocio, no la tecnología.
7. No usar nombres técnicos como:
   - data
   - registro
   - item
   - objeto
8. El diccionario de dominio es la referencia oficial del lenguaje del sistema.

---

## 6. Expansión del dominio

Cuando se agregue una nueva entidad al sistema, se debe agregar a este documento
con la estructura definida.

Este documento debe crecer junto con el sistema.
