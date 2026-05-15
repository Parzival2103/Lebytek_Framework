# Arquitectura del Sistema

## 1. Propósito del documento

Este documento define la arquitectura oficial del sistema y establece las reglas estructurales que deben seguir todos los desarrolladores, integraciones, automatizaciones y herramientas de inteligencia artificial que participen en el desarrollo del proyecto.

El objetivo de esta arquitectura es:

- Mantener consistencia estructural en todo el sistema.
- Evitar mezcla de responsabilidades entre componentes.
- Permitir que múltiples desarrolladores trabajen en el mismo proyecto sin romper la estructura.
- Facilitar mantenimiento, escalabilidad y reutilización de módulos.
- Servir como referencia obligatoria para cualquier cambio estructural en el sistema.
- Establecer una base clara para el desarrollo asistido por inteligencia artificial.

Este documento es normativo; las nuevas implementaciones deben respetar esta arquitectura.

El **dashboard administrativo** como módulo plataforma extensible (KPIs/actividad contribuidos por proveedores) se describe en [modulo-dashboard.md](../modules/modulo-dashboard.md), sin duplicar aquí el checklist de dominio [uso-de-modulo-dominio.md](../modules/uso-de-modulo-dominio.md).

---

## 2. Visión arquitectónica general

El sistema utilizará una arquitectura híbrida MVC + Onion.

La arquitectura se divide en dos enfoques que trabajan juntos:

### MVC (Modelo – Vista – Controlador)
Se utilizará para organizar la interacción web, rutas, controladores, vistas y respuestas HTTP/JSON.

MVC define cómo entra y sale la información del sistema.

### Onion Architecture
Se utilizará para organizar internamente la lógica del sistema, separando responsabilidades entre dominio, aplicación, infraestructura y capas transversales.

Onion define cómo se organiza el núcleo del negocio y sus dependencias.

La combinación de ambos modelos permite:

- Separación clara de responsabilidades.
- Dominio independiente de la infraestructura.
- Escalabilidad del sistema.
- Mantenibilidad a largo plazo.
- Reutilización de lógica de negocio.
- Integración con múltiples interfaces (web, API, scripts, automatizaciones, IA).

---

## 3. Principios arquitectónicos del sistema

El desarrollo del sistema deberá seguir los siguientes principios:

1. Separación estricta de responsabilidades.
2. Bajo acoplamiento entre capas.
3. Alta cohesión dentro de cada capa.
4. El dominio del negocio es el núcleo del sistema y debe protegerse.
5. La infraestructura no define reglas de negocio.
6. La presentación no contiene lógica de negocio.
7. Los controladores no deben contener lógica compleja.
8. El sistema debe poder crecer sin romper la arquitectura base.
9. Las nuevas funcionalidades deben integrarse respetando las capas existentes.
10. No se deben crear accesos directos que salten capas sin justificación.
11. El Kernel contiene únicamente componentes transversales del sistema.
12. La arquitectura debe ser entendible por desarrolladores humanos y herramientas de IA.
13. La consistencia estructural es más importante que la velocidad de implementación.

---

## 4. Capas oficiales del sistema

El sistema se divide en cinco capas principales:

1. Presentation  
2. Application  
3. Domain  
4. Infrastructure  
5. Kernel  

Cada capa tiene responsabilidades específicas y reglas de dependencia.

---

## 5. Capa Presentation

La capa Presentation es responsable de la interacción con el exterior del sistema.

Incluye conceptualmente:

- Rutas
- Controladores
- Vistas
- Respuestas HTTP
- Respuestas JSON
- Middlewares
- Validaciones de entrada básicas
- Adaptadores de entrada
- Adaptadores de salida

Responsabilidades:

- Recibir solicitudes del usuario o del sistema externo.
- Transformar datos de entrada.
- Llamar a la capa Application.
- Devolver respuestas.
- Renderizar vistas.
- Formatear respuestas JSON.

Reglas:

- No debe contener lógica de negocio.
- No debe contener queries complejos.
- No debe contener reglas del dominio.
- No debe contener acceso directo a infraestructura salvo casos controlados.
- Debe delegar la lógica a Application.

---

## 6. Capa Application

La capa Application coordina los casos de uso del sistema.

Incluye conceptualmente:

- Casos de uso
- Servicios de aplicación
- Orquestación de procesos
- Validaciones de flujo
- DTOs
- Comandos
- Consultas
- Coordinación entre Domain e Infrastructure

Responsabilidades:

- Ejecutar casos de uso del sistema.
- Coordinar entidades del dominio.
- Llamar repositorios o servicios externos.
- Manejar transacciones.
- Controlar el flujo del proceso.
- Transformar datos entre capas.

Reglas:

- No debe contener lógica de presentación.
- No debe depender de frameworks de UI.
- No debe contener detalles técnicos de infraestructura.
- Debe utilizar el dominio para reglas de negocio.
- Debe actuar como intermediario entre Presentation y Domain/Infrastructure.

---

## 7. Capa Domain

La capa Domain representa el núcleo del negocio.

Incluye conceptualmente:

- Entidades
- Value Objects
- Reglas de negocio
- Políticas
- Contratos
- Interfaces de repositorios
- Invariantes
- Validaciones de negocio
- Eventos de dominio (si se implementan)

Responsabilidades:

- Definir el lenguaje del negocio.
- Definir las reglas principales del sistema.
- Mantener la consistencia del modelo.
- Representar las entidades del negocio.
- Definir contratos que la infraestructura debe implementar.

Reglas:

- No debe depender de Presentation.
- No debe depender de Infrastructure.
- No debe depender de base de datos.
- No debe depender de frameworks.
- Debe ser la capa más estable del sistema.
- El dominio define reglas, no la infraestructura.

Nivel de implementación:
Domain intermedio-estricto, lo que implica que las reglas importantes deben vivir en el dominio y no en controladores ni queries aislados.

---

## 8. Capa Infrastructure

La capa Infrastructure implementa los detalles técnicos del sistema.

Incluye conceptualmente:

- Implementaciones de repositorios
- Acceso a base de datos
- APIs externas
- Servicios de correo
- Integraciones
- Almacenamiento de archivos
- Logs
- Caché
- Servicios externos
- Adaptadores de infraestructura

Responsabilidades:

- Persistencia de datos.
- Comunicación con servicios externos.
- Implementación técnica de contratos definidos en Domain o Application.
- Manejo de almacenamiento.
- Manejo de servicios técnicos.

Reglas:

- No define reglas de negocio.
- No controla flujo de aplicación.
- Implementa contratos, no define el dominio.
- No debe contener lógica de presentación.
- Debe ser reemplazable sin afectar el dominio.

---

## 9. Capa Kernel

La capa Kernel contiene los componentes transversales del sistema que son compartidos por todas las capas y módulos.

Incluye conceptualmente:

- Configuración base
- Manejo de errores base
- Excepciones base
- Utilidades comunes
- Helpers estructurales
- Clases base abstractas
- Componentes de seguridad comunes
- Constantes globales controladas
- Bootstrapping del sistema
- Herramientas internas reutilizables
- Componentes compartidos entre módulos

Reglas:

- Kernel no debe contener lógica de negocio específica.
- Kernel no debe convertirse en una carpeta de código sin organización.
- Kernel contiene herramientas base del sistema, no funcionalidades de módulos.
- Kernel puede ser utilizado por todas las capas.
- Los cambios en Kernel deben ser cuidadosamente controlados.

El Kernel representa la base técnica del sistema.

**Páginas de error HTTP (404, 403, 500):** el `Bootstrap` del Kernel invoca `registerPresentationErrorRenderers()` definido en `app/Presentation/bootstrap_error_renderers.php`. Esa función registra *callables* en `Response` que hacen `require` de las vistas bajo `Presentation/Views/errors/`. Es una decisión de **composición en el arranque**: el contrato en Kernel sigue siendo “renderer inyectable”; las rutas físicas de las plantillas permanecen en Presentation. No se añade lógica de negocio en Kernel.

---

## 10. Reglas de dependencia entre capas

Las dependencias entre capas deben seguir estas reglas:

- Presentation puede depender de Application.
- Application puede depender de Domain.
- Infrastructure puede depender de Domain o Application para implementar contratos.
- Domain no depende de Presentation.
- Domain no depende de Infrastructure.
- Kernel puede ser utilizado por todas las capas.
- Ninguna capa debe saltarse la arquitectura sin justificación.
- Las dependencias deben apuntar hacia el dominio, no hacia la presentación.

Esto asegura que el núcleo del negocio permanezca independiente.

---

## 11. Flujo estándar de una solicitud

El flujo típico dentro del sistema será:

1. La solicitud entra por una ruta.
2. La ruta llama a un controlador en Presentation.
3. El controlador procesa la entrada.
4. El controlador llama a un caso de uso o servicio en Application.
5. Application coordina la operación.
6. Domain aplica reglas de negocio.
7. Infrastructure maneja persistencia o servicios externos.
8. Application recibe los resultados.
9. Presentation devuelve la respuesta al usuario.

Este flujo debe respetarse en la mayoría de las operaciones del sistema.

---

## 12. Estructura global del sistema (vista general)

La estructura global del proyecto seguirá una organización por capas.

Ejemplo conceptual:
```text
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
/docs
/scripts
/tests
```

La estructura detallada de carpetas y archivos está en [estructura_proyecto.md](./estructura_proyecto.md).

---

## 13. Modularidad y crecimiento del sistema

El sistema crecerá mediante módulos funcionales, pero la arquitectura seguirá organizada por capas globales.

Las nuevas funcionalidades deberán:

- Integrarse en las capas existentes.
- Respetar la separación de responsabilidades.
- No crear estructuras paralelas.
- No duplicar lógica existente.
- Mantener consistencia con el lenguaje del dominio.
- Respetar las reglas de dependencia entre capas.

El crecimiento del sistema debe ser ordenado y estructurado.

---

## 14. Reglas para cambios y refactorización

Cualquier cambio estructural deberá cumplir:

- No romper la arquitectura definida.
- No mover lógica de negocio fuera de Domain/Application sin justificación.
- No agregar código en Kernel sin razón estructural.
- No duplicar lógica existente.
- No saltar capas para acelerar desarrollo.
- Documentar cambios importantes en arquitectura.
- Mantener consistencia de nombres y módulos.
- Revisar impacto en otras capas antes de modificar.

---

## 15. Reglas para desarrollo asistido por IA

Las herramientas de inteligencia artificial que participen en el desarrollo del proyecto deben seguir estas reglas:

- Respetar la arquitectura definida en este documento.
- No crear estructuras fuera de las capas oficiales.
- No renombrar componentes estructurales existentes.
- Mantener las convenciones de nombres del proyecto.
- Ubicar cada nueva clase, módulo o archivo en su capa correspondiente.
- No colocar lógica de negocio en Presentation.
- No colocar lógica técnica en Domain.
- No usar Kernel para código improvisado.
- Si detecta inconsistencias arquitectónicas, debe reportarlas antes de modificarlas.
- Las implementaciones deben integrarse al sistema existente, no crear estructuras paralelas.

La IA debe trabajar como un desarrollador que respeta la arquitectura del sistema.