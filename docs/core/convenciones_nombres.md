# Convenciones de Nombres del Proyecto

## 1. Propósito del documento

Este documento define las convenciones oficiales de nombres que deben utilizarse en todo el proyecto, incluyendo:

- Base de datos
- Tablas
- Columnas
- Variables
- Clases
- Métodos
- Archivos
- Carpetas
- Rutas
- APIs
- Variables de entorno
- JSON
- Identificadores
- Módulos

El objetivo es mantener consistencia, legibilidad, mantenibilidad y evitar ambigüedades en el sistema.

Estas convenciones deben ser respetadas por todos los desarrolladores y herramientas de inteligencia artificial que trabajen en el proyecto.

---

## 2. Reglas generales de nombres

1. Los nombres deben ser descriptivos y claros.
2. No usar abreviaciones innecesarias.
3. No usar nombres genéricos como: data, info, temp, obj, var, test.
4. No mezclar idiomas dentro del mismo nombre.
5. Mantener **un solo idioma por dominio** y **consistencia global** en el proyecto para nombres y conceptos públicos del código/API; cumplido lo anterior, español e inglés son aceptables según las convenciones del equipo pero **sin mezclar idiomas dentro del mismo identificador**.
6. No usar espacios en nombres de archivos o variables.
7. No usar caracteres especiales.
8. Los nombres deben representar el dominio del negocio.
9. Evitar nombres demasiado largos.
10. Mantener consistencia en todo el proyecto.

---

## 3. Convenciones para Base de Datos

### Tablas
- snake_case
- plural
Ejemplos: usuarios, clientes, eventos, facturas, productos, visitas_frecuentes

### Columnas
- snake_case
Ejemplos: id, nombre, created_at, cliente_id, fecha_evento

### Llaves primarias
- id

### Llaves foráneas
- tabla_id
Ejemplos: usuario_id, cliente_id, evento_id

### Tablas pivote
- tabla_tabla
Ejemplos: usuarios_roles, eventos_invitados

---

## 4. Convenciones para PHP

### Clases
- PascalCase
Ejemplos: Usuario, ClienteService, ReporteVentas

### Métodos
- camelCase
Ejemplos: obtenerUsuarios(), crearCliente()

### Variables
- camelCase
Ejemplos: $usuarioId, $clienteNombre

### Constantes
- UPPER_SNAKE_CASE
Ejemplos: APP_NAME, MAX_UPLOAD_SIZE

---

## 5. Convenciones para Archivos

### Clases
- PascalCase.php

### Vistas
- snake_case.php

### Rutas
- snake_case.php

---

## 6. Convenciones para Carpetas

- PascalCase para capas (Presentation, Application, Domain, Infrastructure, Kernel)
- PascalCase para subcarpetas de módulos dentro de las capas PHP (para compatibilidad con PSR-4 autoloading)
- snake_case para módulos en carpetas fuera de PHP (views, config, database, scripts)

Ejemplos de carpetas de capas:
Presentation
Application
Domain
Infrastructure
Kernel

Ejemplos de módulos PHP (PascalCase por PSR-4):
UseCases/Categorias
UseCases/Productos
DTO/Usuarios
Controllers/Admin

Ejemplos de módulos no-PHP (snake_case):
Views/admin/catalogo
Views/admin/usuarios
database/migrations
database/seeds

Nota: Las subcarpetas dentro de las capas PHP deben usar PascalCase porque
los namespaces PSR-4 requieren que el nombre del directorio coincida con
el segmento del namespace. Ejemplo: App\Application\UseCases\Categorias
requiere la carpeta Application/UseCases/Categorias/.

---

## 7. Convenciones para APIs y Rutas

Formato:
/api/recurso
/api/recurso/{id}
/api/recurso/{id}/accion

Ejemplos:
/api/clientes
/api/eventos
/api/facturas

---

## 8. Convenciones para JSON

- camelCase

Ejemplo:
{
  "clienteId": 15,
  "clienteNombre": "Juan Perez",
  "fechaRegistro": "2026-01-01",
  "activo": true
}

---

## 9. Convenciones para Variables de Entorno

- UPPER_SNAKE_CASE

Ejemplos:
APP_NAME
DB_HOST
DB_DATABASE
OPENAI_API_KEY
JWT_SECRET

---

## 10. Reglas de consistencia

1. El mismo concepto debe tener el mismo nombre en todo el sistema.
2. Si la tabla es clientes, la FK debe ser cliente_id.
3. Si la clase es Cliente, el archivo debe ser Cliente.php.
4. Si la API es /api/clientes, no debe existir /api/client.
5. No cambiar nombres existentes sin justificación.
6. Mantener el lenguaje del dominio consistente.

---

## 11. Reglas para uso de IA

- Respetar las convenciones del proyecto.
- No inventar nuevos estilos de nombres.
- No crear sinónimos para la misma entidad.
- Mantener consistencia entre base de datos, código y API.
