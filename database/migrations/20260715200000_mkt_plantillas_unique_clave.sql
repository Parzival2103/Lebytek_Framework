-- Idempotent unique on clave (drop non-unique index if present).
ALTER TABLE `dom_mkt_plantillas`
  DROP INDEX `idx_mkt_plantillas_clave`,
  ADD UNIQUE KEY `uq_mkt_plantillas_clave` (`clave`);
