# Estructura del Proyecto

## 1. Propósito del documento

Este documento define la estructura oficial de carpetas y archivos del proyecto.  
Su objetivo es mantener una organización consistente del sistema, facilitar el mantenimiento,
permitir el trabajo en equipo y asegurar que todos los componentes del sistema se ubiquen
en el lugar correcto según la arquitectura definida.

Esta estructura debe ser respetada por todos los desarrolladores y herramientas de inteligencia artificial.

---

## 2. Estructura global del proyecto

Estructura general del sistema:

/app
    /Presentation
    /Application
    /Domain
    /Infrastructure
    /Kernel

/config
/database
/public
/routes
/storage
/tests
/docs
/scripts

---

## 3. Carpeta /app

Contiene toda la lógica principal del sistema organizada por capas.

### /app/Presentation
Contiene todo lo relacionado con la interacción con el usuario o APIs.

Subcarpetas:
- Controllers
- Views
- Middlewares
- Requests
- Responses
- Api
- Web

---

### /app/Application
Contiene la lógica de aplicación y casos de uso del sistema.

Subcarpetas:
- UseCases
- Services
- DTO
- Commands
- Queries
- Validators
- Mappers

---

### /app/Domain
Contiene el núcleo del negocio.

Subcarpetas:
- Entities
- ValueObjects
- Interfaces
- Rules
- Policies
- Events
- Exceptions

---

### /app/Infrastructure
Contiene implementaciones técnicas.

Subcarpetas:
- Persistence
- Repositories
- Database
- ExternalServices
- Storage
- Mail
- Cache
- Logging
- Integrations
- Queue

---

### /app/Kernel
Contiene componentes transversales del sistema.

Subcarpetas:
- Config
- Exceptions
- Helpers
- Security
- Http
- Database
- Logging
- Utilities
- Traits
- BaseClasses
- Constants

---

## 4. Carpeta /config

Archivos de configuración del sistema.

Ejemplos:
- app.php
- database.php
- mail.php
- cache.php
- services.php
- auth.php
- filesystems.php

---

## 5. Carpeta /database

Contiene todo lo relacionado con base de datos.

Subcarpetas:
- migrations
- seeds (semillas `.sql` ejecutadas por `scripts/seed.php`; la carpeta `seeders/` solo documenta el enlace)
- factories
- schema
- backups

---

## 6. Carpeta /public

Carpeta pública accesible desde el navegador.

Contenido:
- index.php
- assets
- css
- js
- images
- uploads

---

## 7. Carpeta /routes

Define todas las rutas del sistema.

Ejemplos:
- web.php
- api.php
- clientes.php
- eventos.php
- facturacion.php
- usuarios.php

---

## 8. Carpeta /storage

Archivos generados por el sistema.

Subcarpetas:
- logs
- uploads
- temp
- cache
- exports
- imports

---

## 9. Carpeta /tests

Pruebas del sistema.

Subcarpetas:
- Unit
- Feature
- Integration
- Performance

---

## 10. Carpeta /docs

Documentación del sistema.

Archivos:
- arquitectura.md
- convenciones_nombres.md
- estructura_proyecto.md
- reglas_api.md
- diccionario_dominio.md
- reglas_sql.md
- reglas_ia.md

---

## 11. Carpeta /scripts

Scripts de automatización.

Ejemplos:
- backups
- migraciones
- importadores
- exportadores
- mantenimiento
- sincronizacion
- jobs
- cron

---

## 12. Reglas generales de la estructura

1. No crear carpetas fuera de esta estructura sin documentarlo.
2. Cada archivo debe pertenecer a una capa.
3. No mezclar lógica de diferentes capas.
4. Controllers solo en Presentation.
5. Casos de uso en Application.
6. Entidades en Domain.
7. Repositorios en Infrastructure.
8. Utilidades generales en Kernel.
9. Configuraciones en config.
10. Migraciones en database/migrations.
11. Archivos públicos solo en public.
12. Documentación en docs.
13. Scripts en scripts.
14. Tests en tests.

Esta estructura es obligatoria para todo el proyecto.
