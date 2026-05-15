# Reglas para Desarrollo Asistido por Inteligencia Artificial

## 1. Propósito del documento

Este documento define las reglas que deben seguir las herramientas de inteligencia artificial
cuando participen en el desarrollo, modificación, refactorización o documentación del sistema.

El objetivo es asegurar que la IA:
- Respete la arquitectura del sistema
- Mantenga consistencia en nombres
- No rompa la estructura del proyecto
- No duplique lógica existente
- No cree estructuras paralelas
- Trabaje como un desarrollador dentro del equipo

Estas reglas son obligatorias para cualquier uso de IA en el proyecto.

---

## 2. Principios generales

La IA debe comportarse como un desarrollador que trabaja dentro de un proyecto existente,
no como un generador de código aislado.

Principios:

1. La arquitectura del sistema es obligatoria.
2. Las convenciones de nombres son obligatorias.
3. El diccionario de dominio define el lenguaje del sistema.
4. La estructura del proyecto no debe modificarse sin justificación.
5. La IA debe integrarse al sistema existente, no crear sistemas nuevos dentro del sistema.
6. La consistencia del proyecto es más importante que la creatividad del código.
7. Antes de crear algo nuevo, la IA debe buscar si ya existe algo similar.
8. La IA debe reutilizar código existente cuando sea posible.
9. La IA debe evitar duplicación de lógica.
10. La IA debe documentar cambios importantes.

---

## 3. Reglas sobre arquitectura

La IA debe respetar las capas del sistema:

- Presentation
- Application
- Domain
- Infrastructure
- Kernel

Reglas:

1. No colocar lógica de negocio en Presentation.
2. No colocar lógica de infraestructura en Domain.
3. No colocar lógica de UI en Application.
4. No usar Kernel para lógica de negocio.
5. Cada clase debe ubicarse en su capa correcta.
6. No crear carpetas fuera de la estructura oficial.
7. No mover archivos entre capas sin justificación.
8. No saltarse capas para acceder directamente a base de datos desde Presentation.
9. Respetar el flujo:
   Presentation → Application → Domain → Infrastructure
10. El Domain debe ser independiente de Infrastructure.

---

## 4. Reglas sobre nombres

La IA debe respetar el documento de convenciones de nombres.

Reglas:

1. No inventar nuevos estilos de nombres.
2. Respetar snake_case en SQL.
3. Respetar camelCase en métodos y variables.
4. Respetar PascalCase en clases.
5. Respetar nombres definidos en el diccionario de dominio.
6. No crear sinónimos para la misma entidad.
7. No cambiar nombres existentes sin autorización.
8. Si existe Cliente, no crear Customer.
9. Si existe usuario_id, no crear id_usuario.
10. Mantener consistencia entre SQL, backend, API y frontend.

---

## 5. Reglas sobre base de datos

La IA debe seguir las reglas SQL del proyecto.

Reglas:

1. Toda tabla debe tener PK id.
2. Las FKs deben terminar en _id.
3. No usar FLOAT para dinero.
4. Usar DECIMAL para cantidades monetarias.
5. Respetar nombres de tablas en plural.
6. No crear tablas duplicadas para el mismo concepto.
7. No agregar columnas sin revisar el dominio.
8. No eliminar columnas sin revisar impacto.
9. No cambiar estructura sin migración.
10. Respetar relaciones del dominio.

---

## 6. Reglas sobre APIs

La IA debe seguir las reglas de APIs del proyecto.

Reglas:

1. Usar rutas en plural.
2. No usar verbos en endpoints.
3. Usar métodos HTTP correctamente.
4. Respetar formato estándar de respuestas JSON.
5. No crear endpoints duplicados.
6. No cambiar endpoints existentes sin versionar.
7. Mantener consistencia en nombres de endpoints.
8. Seguir estructura /api/recurso/{id}.
9. Documentar endpoints nuevos.
10. Mantener APIs predecibles.

---

## 7. Reglas sobre modificaciones de código

Cuando la IA modifique código existente:

1. No cambiar nombres de variables sin razón.
2. No cambiar estructura de clases sin razón.
3. No reescribir módulos completos si solo se requiere un cambio pequeño.
4. No eliminar código sin revisar dependencias.
5. No duplicar funciones existentes.
6. No cambiar firmas de métodos sin revisar llamadas existentes.
7. Mantener estilo de código existente.
8. Mantener comentarios importantes.
9. Agregar comentarios en lógica compleja.
10. Explicar cambios importantes.

---

## 8. Reglas sobre creación de nuevos módulos

Antes de crear un módulo nuevo, la IA debe verificar:

1. Si el módulo ya existe.
2. Si la funcionalidad pertenece a un módulo existente.
3. En qué capa debe vivir.
4. Qué entidades del dominio afecta.
5. Qué tablas SQL necesita.
6. Qué endpoints API necesita.
7. Qué servicios de aplicación necesita.
8. Qué repositorios necesita.
9. Qué validaciones necesita.
10. Cómo afecta a otros módulos.

No se deben crear módulos aislados sin integrarlos al dominio del sistema.

---

## 9. Reglas sobre documentación

La IA debe ayudar a mantener documentación del sistema.

Debe documentar:

- Nuevas entidades
- Nuevos módulos
- Cambios de arquitectura
- Nuevos endpoints
- Cambios de base de datos
- Nuevas reglas del negocio
- Nuevas integraciones
- Nuevos servicios
- Scripts importantes
- Cambios estructurales

La documentación forma parte del sistema.

---

## 10. Reglas finales

La IA debe comportarse como:

- Arquitecto de software
- Desarrollador backend
- Diseñador de base de datos
- Documentador técnico
- Integrador de sistemas
- Miembro del equipo de desarrollo

La IA no debe comportarse como:
- Generador de código aislado
- Script sin contexto
- Programador improvisado
- Herramienta que ignora la arquitectura
- Sistema que crea estructuras paralelas

La IA debe respetar:
- Arquitectura
- Convenciones de nombres
- Diccionario de dominio
- Reglas SQL
- Reglas de API
- Estructura del proyecto
- Documentación del sistema

Estas reglas son obligatorias para el desarrollo asistido por inteligencia artificial.
