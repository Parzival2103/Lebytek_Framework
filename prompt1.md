Actúa como arquitecto de software senior especializado en PHP puro, MySQL, JavaScript vanilla y Bootstrap. Diseña la base de una aplicación web tipo PWA modular para administración empresarial, preparada para crecimiento real en producción.

Objetivo:
Construir un núcleo reutilizable de sistema administrativo con login, panel admin, RBAC por roles y módulos desacoplados, que sirva como base para futuros CRM/ERP internos.

Stack obligatorio:
- PHP puro
- MySQL
- JavaScript vanilla
- Bootstrap
- Variables de entorno (.env) para configuración sensible
- Sin frameworks backend
- Sin librerías pesadas innecesarias
- Se permite uso controlado de JavaScript para tablas estilo DataTables, interacciones UI y animaciones simples

Lineamientos arquitectónicos obligatorios:
- Usar MVC + Onion Architecture
- Cada módulo debe estar desacoplado de reglas de negocio
- La lógica de dominio no debe depender de la UI, Bootstrap, HTTP, SQL directo ni archivos globales
- Separar claramente:
  - Domain
  - Application
  - Infrastructure
  - Presentation
- Diseñar pensando en reutilización, mantenibilidad, escalabilidad y pruebas futuras
- Evitar archivos monolíticos y funciones mezcladas
- Cada responsabilidad debe estar claramente aislada

Requisitos funcionales base:
- Login seguro
- Control de acceso RBAC por roles y permisos
- Panel de administración
- Gestión de usuarios, roles y permisos
- Módulo de ajustes generales
- Layout configurable del menú:
  - top
  - side
  - bottom
- Responsive para escritorio y móvil
- Estilos visuales personalizables desde ajustes de administrador
- Carga modular por autoload y bootstrap central
- CRUDs preparados para escalar
- Base lista para dashboards, reportes y módulos activables

Base de datos:
- MySQL
- Conexión mediante variables de entorno
- Diseño preparado para usuarios, roles, permisos, relación rol-permiso y configuración general
- Evitar acoplar lógica de negocio directamente al SQL de presentación

Frontend:
- Bootstrap como base visual
- JavaScript vanilla para interacción
- Tablas dinámicas, filtros, confirmaciones, pequeñas animaciones y comportamiento UI
- La configuración visual debe permitir modificar temas, colores y disposición del menú desde admin

Contexto del proyecto:
Este proyecto será la base de una aplicación administrativa modular tipo CRM/ERP, usada para distintas operaciones de negocio. Debe permitir agregar módulos nuevos sin romper el núcleo. El sistema debe sentirse profesional, ordenado, limpio, extensible y listo para producción compartida en hosting tradicional con PHP/MySQL.

Reglas de implementación:
- No improvises estructura
- No mezcles lógica de negocio con vistas
- No pongas consultas SQL dentro de templates HTML
- No uses nombres ambiguos
- Propón convenciones claras desde el inicio
- Todo debe quedar preparado para crecer por módulos
- Prioriza claridad sobre “magia”
- Mantén compatibilidad con hosting compartido típico
- Usa includes/autoload de forma ordenada
- Considera seguridad base: sesiones, hash de contraseñas, validación, CSRF y separación de responsabilidades

Entrega en este orden:
1. Estructura de carpetas completa
2. Convención de nombres de archivos, clases, variables y módulos
3. Arquitectura explicada por capas
4. Flujo de autenticación
5. Modelo RBAC inicial
6. Bootstrap del proyecto
7. Configuración de entorno
8. Esquema SQL base
9. Ejemplo de módulo desacoplado
10. Base de panel admin configurable
11. Recomendaciones para escalar módulos futuros

Importante:
No generes todo de forma superficial. Quiero una base seria de proyecto, bien pensada, lista para convertirse en una plataforma modular real en PHP.

