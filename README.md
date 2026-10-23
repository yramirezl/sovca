# SOVCA

Sistema de Gestión Ganadera **Ovina y Caprina**.

Proyecto basado en el código actualizado de BOVS, adecuado para operar contra la base
`sovca_qas`, con catálogos de razas ovinas y caprinas y códigos de animal con prefijo
`SOVCA-`.

## Documentación

- [Adecuación de SOVCA](docs/adecuacion_sovca.md): estado de la migración, cambios
  aplicados en base de datos y código, verificaciones y pendientes.
- [Despliegue — Modo offline MULTIFUNDO (v4.1)](docs/despliegue_offline_multifundo.md):
  pasos, migración de base de datos, verificación del API, comportamiento de los equipos
  que ya usan la app offline y plan de rollback.
- [Alerta por inactividad de fundos](docs/alerta_inactividad_fundos.md): aviso al productor
  cuando ningún usuario del fundo se conecta en 7 días, con registro de envíos y modo de prueba.
- [Catálogo de estatus del animal](docs/catalogo_estatus_animal.md): ACTIVO (hembras y machos),
  DONADOR ACTIVO / DONADOR (solo machos) y DONADORA ACTIVO / DONADORA (solo hembras), con la
  migración de datos y las validaciones de la ficha del animal, servicios y nacimientos.
- [Modo soporte](docs/modo_soporte.md): acceso temporal (2 h) de un administrador o super usuario
  a un fundo para revisar y corregir datos, sin cambiar de cuenta ni de rol, con bitácora
  `soporte_fundos` y cierre automático. Solo versión web.
- [Recuperación de contraseña](docs/recuperacion_password.md): enlace por correo para que el
  usuario cree una clave nueva (token de un solo uso, 30 min, captcha y sin revelar qué correos
  están registrados). Incluye la configuración SMTP y la migración de la base de datos.

