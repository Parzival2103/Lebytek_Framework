-- core_archivos: ledger central de archivos subidos (uploads de cualquier módulo)
-- Soporta historial por entidad/colección (es_actual), soft-delete y autor.
-- Idempotente: se puede re-ejecutar en cada despliegue.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `core_archivos` (
  `id`             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `entidad_tipo`   VARCHAR(80)      NOT NULL,
  `entidad_id`     INT UNSIGNED     DEFAULT NULL,
  `coleccion`      VARCHAR(60)      NOT NULL DEFAULT 'default',
  `ruta`           VARCHAR(500)     NOT NULL,
  `thumbnail_ruta` VARCHAR(500)     DEFAULT NULL,
  `nombre_original` VARCHAR(255)    DEFAULT NULL,
  `mime`           VARCHAR(120)     DEFAULT NULL,
  `extension`      VARCHAR(20)      DEFAULT NULL,
  `tamano_bytes`   BIGINT UNSIGNED  NOT NULL DEFAULT 0,
  `disco`          VARCHAR(20)      NOT NULL DEFAULT 'public',
  `es_actual`      TINYINT(1)       NOT NULL DEFAULT 0,
  `creado_por`     INT UNSIGNED     DEFAULT NULL,
  `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at`     DATETIME         DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_archivos_entidad` (`entidad_tipo`, `entidad_id`, `coleccion`),
  INDEX `idx_archivos_actual`  (`entidad_tipo`, `entidad_id`, `coleccion`, `es_actual`),
  INDEX `idx_archivos_deleted` (`deleted_at`),
  CONSTRAINT `fk_archivos_creado_por`
      FOREIGN KEY (`creado_por`) REFERENCES `auth_usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
