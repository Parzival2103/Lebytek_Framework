-- Registro público y recuperación de contraseña (spec 2026-06-12).
-- Tabla de tokens multi-propósito + verificación de email + rol 'usuario'.
-- Idempotente: se puede re-ejecutar en cada despliegue.
SET NAMES utf8mb4;

ALTER TABLE `auth_usuarios` ADD COLUMN IF NOT EXISTS `email_verificado_en` DATETIME DEFAULT NULL AFTER `ultimo_acceso`;

CREATE TABLE IF NOT EXISTS `auth_tokens` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED    NOT NULL,
  `tipo`       VARCHAR(30)     NOT NULL,
  `token_hash` CHAR(64)        NOT NULL,
  `expira_en`  DATETIME        NOT NULL,
  `usado_en`   DATETIME        DEFAULT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tokens_usuario_tipo` (`usuario_id`, `tipo`),
  INDEX `idx_tokens_hash` (`token_hash`),
  CONSTRAINT `fk_tokens_usuario`
      FOREIGN KEY (`usuario_id`) REFERENCES `auth_usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `auth_roles` (`nombre`, `slug`, `descripcion`) VALUES
  ('Usuario', 'usuario', 'Usuario registrado desde el formulario público');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` = 'dashboard.ver'
WHERE `r`.`slug` = 'usuario';
