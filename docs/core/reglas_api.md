# Reglas de API y Rutas

## 1. Propósito del documento

Este documento define las reglas oficiales para la creación de APIs y rutas dentro del sistema.
Su objetivo es mantener consistencia, escalabilidad y claridad en la comunicación entre frontend,
backend, servicios externos y módulos internos del sistema.

Todas las APIs del sistema deben seguir estas reglas.

---

## 2. Estructura general de URLs

Formato base:

/api/recurso
/api/recurso/{id}
/api/recurso/{id}/accion
/api/recurso/{id}/subrecurso
/api/recurso/{id}/subrecurso/{sub_id}

Ejemplos:
- /api/clientes
- /api/clientes/15
- /api/clientes/15/pagos
- /api/eventos
- /api/eventos/3/invitados
- /api/facturas
- /api/usuarios

Reglas:
- Siempre minúsculas.
- Usar plural.
- Usar guiones para separar palabras.
- No usar camelCase en URLs.
- No usar verbos en la URL.
- La URL representa recursos, no acciones.

---

## 3. Métodos HTTP

Se deben utilizar correctamente los métodos HTTP:

GET
- Obtener información
- No modifica datos

POST
- Crear registros
- Ejecutar procesos

PUT
- Actualizar completamente un registro

PATCH
- Actualizar parcialmente un registro

DELETE
- Eliminar un registro

Ejemplos:

GET /api/clientes
GET /api/clientes/15
POST /api/clientes
PUT /api/clientes/15
PATCH /api/clientes/15
DELETE /api/clientes/15

---

## 4. Formato de respuestas JSON

Todas las APIs deben devolver respuestas con la misma estructura:

{
  "success": true,
  "message": "Mensaje descriptivo",
  "data": {},
  "errors": []
}

Ejemplo correcto:

{
  "success": true,
  "message": "Cliente creado correctamente",
  "data": {
    "id": 15,
    "nombre": "Juan Perez"
  },
  "errors": []
}

Ejemplo de error:

{
  "success": false,
  "message": "Error de validación",
  "data": null,
  "errors": {
    "nombre": ["El nombre es obligatorio"]
  }
}

---

## 5. Paginación

Formato estándar para listas grandes:

GET /api/clientes?page=1&limit=20

Respuesta:

{
  "success": true,
  "message": "",
  "data": [],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 150,
    "pages": 8
  }
}

---

## 6. Filtros

Los filtros deben enviarse por query parameters.

Ejemplos:

/api/clientes?activo=1
/api/eventos?fecha_inicio=2026-01-01&fecha_fin=2026-01-31
/api/pagos?cliente_id=15
/api/productos?categoria_id=3

---

## 7. Ordenamiento

Formato:

/api/clientes?sort=nombre
/api/clientes?sort=nombre&order=asc
/api/clientes?sort=created_at&order=desc

---

## 8. Versionado de API

Las APIs deben poder versionarse:

/api/v1/clientes
/api/v1/eventos
/api/v1/facturas

Cuando exista una nueva versión:
/api/v2/clientes

---

## 9. Nombres de endpoints

Reglas:
- Usar sustantivos, no verbos.
- Usar plural.
- No usar nombres técnicos.
- Usar nombres del dominio del negocio.

Correcto:
- /api/clientes
- /api/eventos
- /api/facturas
- /api/pagos

Incorrecto:
- /api/getClientes
- /api/createCliente
- /api/updateCliente

---

## 10. Acciones especiales

Cuando una acción no es CRUD:

Formato:
/api/recurso/{id}/accion

Ejemplos:
- /api/facturas/15/cancelar
- /api/eventos/3/cerrar
- /api/usuarios/10/reset-password
- /api/pagos/25/generar-recibo

---

## 11. Autenticación

Las APIs protegidas deben requerir autenticación mediante:

- Token
- JWT
- API Key
- Session

Formato recomendado:
Authorization: Bearer TOKEN

---

## 12. Reglas generales

1. Todas las APIs deben devolver JSON.
2. Todas las respuestas deben tener la misma estructura.
3. No mezclar lógica de negocio en rutas.
4. Las rutas solo dirigen al controlador.
5. Usar nombres del dominio del negocio.
6. Mantener consistencia en todos los endpoints.
7. Documentar endpoints importantes.
8. Versionar APIs cuando haya cambios grandes.
9. No romper endpoints existentes sin versionar.
10. Mantener las APIs predecibles y consistentes.

Estas reglas son obligatorias para todo el sistema.
