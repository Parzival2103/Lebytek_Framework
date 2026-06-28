-- Intentos fallidos de login para rate limiting temporal (IP + email).
CREATE TABLE IF NOT EXISTS `auth_login_intentos` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dimension`  VARCHAR(10)     NOT NULL,
  `clave`      VARCHAR(255)    NOT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_login_intentos_busqueda` (`dimension`, `clave`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
