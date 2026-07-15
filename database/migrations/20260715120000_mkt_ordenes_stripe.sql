-- Stripe / pasarela: columnas de pago en órdenes de membresía.
-- Ops: MariaDB 10.3.3+ / MySQL 8.0.12+ support ADD COLUMN IF NOT EXISTS.
-- If VPS dialect rejects it, run each ADD once guarded by information_schema.

ALTER TABLE `dom_mkt_ordenes`
  ADD COLUMN IF NOT EXISTS `metodo_pago` VARCHAR(20) DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `payment_provider` VARCHAR(40) DEFAULT NULL AFTER `metodo_pago`,
  ADD COLUMN IF NOT EXISTS `payment_ref` VARCHAR(190) DEFAULT NULL AFTER `payment_provider`;

CREATE INDEX IF NOT EXISTS `idx_mkt_ordenes_payment_ref` ON `dom_mkt_ordenes` (`payment_ref`);
