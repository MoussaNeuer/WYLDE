-- ═══════════════════════════════════════════════════════════════════════════
--  WYLDE — Schéma de base de données
--  MySQL 8.x / MariaDB · utf8mb4 · InnoDB
--
--  Conventions (cf. cahier des charges §11 et décisions de Phase 1) :
--   · Montants en DECIMAL(12,0) : FCFA, sans centimes, jamais FLOAT.
--   · Clés primaires auto-incrémentées, clés étrangères contraignantes.
--   · Index sur email, slug, SKU, statut, dates et références de commande.
--   · created_at / updated_at sur toutes les entités principales.
--   · Pas de table password_resets : aucun envoi d'email en V1.
--   · Bilingue FR/EN : colonnes name_en / description_en, repli sur le
--     français si la colonne est vide.
--   · Chaque produit possède au moins une variante (taille « UNIQUE »
--     si le produit n'a pas de tailles) : le stock est toujours par variante.
--   · Checkout invité : customers.user_id est NULLABLE.
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ───────────────────────────────────────────────────────────────────────────
--  users — comptes clients et_back-office
--  role Admin : V1 un seul compte, mais l'architecture prévoit l'ajout
--  d'un gestionnaire (manager) ou d'un assistant plus tard (cf. §4).
-- ───────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(120)  NOT NULL,
    `email`         VARCHAR(190)  NOT NULL,
    `password_hash` VARCHAR(255)  NOT NULL,
    `role`          ENUM('customer','manager','admin') NOT NULL DEFAULT 'customer',
    `status`        ENUM('active','suspended')        NOT NULL DEFAULT 'active',
    `phone`         VARCHAR(30)   NULL,
    `last_login_at` DATETIME      NULL,
    `last_login_ip` CHAR(64)      NULL COMMENT 'HMAC de l\'IP, jamais en clair',
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role_status` (`role`, `status`),
    KEY `idx_users_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────
--  customers — données de livraison
--  user_id NULLABLE : un checkout invité crée une ligne sans compte (§18).
-- ───────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `customers` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED NULL COMMENT 'NULL = commande invitée',
    `first_name`   VARCHAR(80)  NOT NULL,
    `last_name`    VARCHAR(80)  NOT NULL,
    `email`        VARCHAR(190) NOT NULL,
    `phone`        VARCHAR(30)  NOT NULL,
    `address`      VARCHAR(255) NOT NULL,
    `city`         VARCHAR(120) NOT NULL,
    `country_code` CHAR(2)      NOT NULL DEFAULT 'SN',
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_customers_user` (`user_id`),
    KEY `idx_customers_email` (`email`),
    KEY `idx_customers_phone` (`phone`),
    KEY `idx_customers_city` (`city`),
    CONSTRAINT `fk_customers_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────
--  categories — collections
--  parent_id permet d'imbriquer (ex. > Vestes > Manteaux).
-- ───────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `categories` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id`       INT UNSIGNED NULL,
    `name`            VARCHAR(120) NOT NULL,
    `name_en`         VARCHAR(120) NULL COMMENT 'Repli sur name si vide',
    `slug`            VARCHAR(160) NOT NULL,
    `description`     TEXT         NULL,
    `description_en`  TEXT         NULL,
    `image_path`      VARCHAR(255) NULL,
    `status`          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `sort_order`      INT          NOT NULL DEFAULT 0,
    `seo_title`       VARCHAR(190) NULL,
    `seo_description` VARCHAR(320) NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_slug` (`slug`),
    KEY `idx_categories_parent` (`parent_id`),
    KEY `idx_categories_status_sort` (`status`, `sort_order`),
    CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`)
        REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────
--  products
--  Pas de colonne stock ici : le stock vit sur product_variants, car
--  chaque produit possède au moins une variante.
-- ───────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `products` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id`         INT UNSIGNED NULL,
    `name`                VARCHAR(180) NOT NULL,
    `name_en`             VARCHAR(180) NULL COMMENT 'Repli sur name si vide',
    `slug`                VARCHAR(200) NOT NULL,
    `short_description`   VARCHAR(320) NULL,
    `short_description_en` VARCHAR(320) NULL,
    `description`         LONGTEXT     NULL,
    `description_en`      LONGTEXT     NULL,
    `price`               DECIMAL(12,0) NOT NULL COMMENT 'Prix de vente en FCFA',
    `sale_price`          DECIMAL(12,0) NULL    COMMENT 'Prix promotionnel, facultatif',
    `cost_price`          DECIMAL(12,0) NULL    COMMENT 'Prix d\'achat, usage interne',
    `status`              ENUM('draft','published','hidden','archived') NOT NULL DEFAULT 'draft',
    `label`               ENUM('none','new','bestseller','limited') NOT NULL DEFAULT 'none',
    `sku`                 VARCHAR(64)  NULL,
    `has_sizes`           TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '0 = variante UNIQUE seule',
    `is_featured`         TINYINT(1)   NOT NULL DEFAULT 0,
    `view_count`          INT UNSIGNED NOT NULL DEFAULT 0,
    `sold_count`          INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Dénormalisé pour le tri popularité',
    `seo_title`           VARCHAR(190) NULL,
    `seo_title_en`        VARCHAR(190) NULL,
    `seo_description`     VARCHAR(320) NULL,
    `seo_description_en`  VARCHAR(320) NULL,
    `published_at`        DATETIME     NULL,
    `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_products_slug` (`slug`),
    KEY `idx_products_category` (`category_id`),
    KEY `idx_products_status_published` (`status`, `published_at`),
    KEY `idx_products_sku` (`sku`),
    KEY `idx_products_price` (`price`),
    KEY `idx_products_label` (`label`),
    KEY `idx_products_sold` (`sold_count`),
    KEY `idx_products_featured` (`is_featured`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`)
        REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────
--  product_variants
--  Toujours au moins une ligne par produit. Sans tailles, size = 'UNIQUE'.
--  price_override permet un tarif spécifique à une taille.
-- ───────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `product_variants` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`     INT UNSIGNED NOT NULL,
    `size`           VARCHAR(20)  NOT NULL DEFAULT 'UNIQUE',
    `sku`            VARCHAR(64)  NULL,
    `stock`          INT          NOT NULL DEFAULT 0,
    `price_override` DECIMAL(12,0) NULL,
    `is_default`     TINYINT(1)   NOT NULL DEFAULT 0,
    `sort_order`     INT          NOT NULL DEFAULT 0,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_variants_product_size` (`product_id`, `size`),
    KEY `idx_variants_sku` (`sku`),
    KEY `idx_variants_product_stock` (`product_id`, `stock`),
    KEY `idx_variants_stock` (`stock`),
    CONSTRAINT `fk_variants_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────
--  product_images — galerie, ordre modifiable par Drag & Drop
--  path est relatif au dossier storage/uploads.
-- ───────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `product_images` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `path`       VARCHAR(255) NOT NULL,
    `alt_text`   VARCHAR(190) NULL,
    `alt_text_en` VARCHAR(190) NULL,
    `sort_order` INT          NOT NULL DEFAULT 0,
    `is_primary` TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_images_product_sort` (`product_id`, `sort_order`),
    KEY `idx_images_product_primary` (`product_id`, `is_primary`),
    CONSTRAINT `fk_images_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────
--  shipping_zones — tarifs de livraison
--  Résolution (voir ShippingService) :
--    1. ville exacte (country_code + city)
--    2. pays entier (country_code, city IS NULL)
--    3. zone de repli (is_default = 1)
--  Sans correspondance, le checkout affiche « livraison indisponible ».
--  Une seule ligne peut porter is_default = 1.
-- ───────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `shipping_zones` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `label`        VARCHAR(120) NOT NULL COMMENT 'Libellé admin, ex. « Sénégal — Dakar »',
    `country_code` CHAR(2)      NULL COMMENT 'NULL uniquement pour la zone par défaut',
    `city`         VARCHAR(120) NULL COMMENT 'NULL = tout le pays',
    `country_name` VARCHAR(120) NULL,
    `price`        DECIMAL(12,0) NOT NULL DEFAULT 0 COMMENT 'Frais en FCFA',
    `is_default`   TINYINT(1)   NOT NULL DEFAULT 0,
    `status`       ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `sort_order`   INT          NOT NULL DEFAULT 0,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- La collation utf8mb4_unicode_ci est insensible à la casse :
    -- la comparaison city = 'dakar' trouve « Dakar » sans colonne dérivée.
    KEY `idx_zones_resolution` (`status`, `country_code`, `city`),
    KEY `idx_zones_default` (`is_default`),
    KEY `idx_zones_city` (`city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────
--  cart_items — panier
--  Rattaché à une session (invité) et/ou à un utilisateur (connecté).
--  Le serveur recalcule toujours les montants (§13.3).
-- ───────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `cart_items` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(128) NULL,
    `user_id`    INT UNSIGNED NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `variant_id` INT UNSIGNED NOT NULL,
    `quantity`   INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cart_session` (`session_id`),
    KEY `idx_cart_user` (`user_id`),
    KEY `idx_cart_variant` (`variant_id`),
    KEY `idx_cart_session_variant` (`session_id`, `variant_id`),
    CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_cart_variant` FOREIGN KEY (`variant_id`)
        REFERENCES `product_variants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────
--  orders
--  payment_method : cod (à la livraison) ou wave (lien global).
--  Wave ne confirme rien automatiquement (§18) : payment_status passe à
--  'paid' uniquement quand l'admin le décide depuis le back-office.
--  Le snapshot client est recopié ici : une commande reste lisible même
--  si la fiche client évolue ensuite.
-- ───────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `orders` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reference`       VARCHAR(32)   NOT NULL COMMENT 'Référence courte communiquée au client',
    `customer_id`     INT UNSIGNED  NULL COMMENT 'NULL si la fiche client a été supprimée',
    `user_id`         INT UNSIGNED  NULL COMMENT 'NULL pour un checkout invité',
    `status`          ENUM('pending','confirmed','preparing','shipped','delivered','cancelled')
                      NOT NULL DEFAULT 'pending',
    `payment_method`  ENUM('cod','wave')  NOT NULL DEFAULT 'cod',
    `payment_status`  ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
    `subtotal`        DECIMAL(12,0) NOT NULL DEFAULT 0,
    `shipping_cost`   DECIMAL(12,0) NOT NULL DEFAULT 0,
    `discount`        DECIMAL(12,0) NOT NULL DEFAULT 0,
    `total`           DECIMAL(12,0) NOT NULL DEFAULT 0,
    `currency`        CHAR(3)       NOT NULL DEFAULT 'XOF',
    `item_count`      INT UNSIGNED  NOT NULL DEFAULT 0 COMMENT 'Somme des quantités',
    -- Snapshot des coordonnées client
    `customer_email`  VARCHAR(190)  NOT NULL,
    `customer_phone`  VARCHAR(30)   NOT NULL,
    -- Snapshot de l'adresse de livraison
    `shipping_first_name`  VARCHAR(80)  NOT NULL,
    `shipping_last_name`   VARCHAR(80)  NOT NULL,
    `shipping_address`     VARCHAR(255) NOT NULL,
    `shipping_city`        VARCHAR(120) NOT NULL,
    `shipping_country_code` CHAR(2)     NOT NULL DEFAULT 'SN',
    `shipping_zone_id`     INT UNSIGNED NULL,
    `shipping_method`      VARCHAR(120) NULL COMMENT 'Libellé lisible de la zone appliquée',
    `notes`           TEXT          NULL COMMENT 'Message du client',
    `admin_notes`     TEXT          NULL COMMENT 'Notes internes, invisibles du client',
    `tracking_number` VARCHAR(120)  NULL,
    `cancelled_reason` VARCHAR(255) NULL,
    `paid_at`         DATETIME      NULL,
    `shipped_at`      DATETIME      NULL,
    `delivered_at`    DATETIME      NULL,
    `cancelled_at`    DATETIME      NULL,
    `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orders_reference` (`reference`),
    KEY `idx_orders_status_created` (`status`, `created_at`),
    KEY `idx_orders_payment_status` (`payment_status`),
    KEY `idx_orders_customer` (`customer_id`),
    KEY `idx_orders_user` (`user_id`),
    KEY `idx_orders_created_at` (`created_at`),
    KEY `idx_orders_email` (`customer_email`),
    KEY `idx_orders_zone` (`shipping_zone_id`),
    CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`)
        REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_orders_zone` FOREIGN KEY (`shipping_zone_id`)
        REFERENCES `shipping_zones` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────
--  order_items — lignes de commande
--  product_name / size / unit_price sont figés au moment de l'achat :
--  le serveur ne fait jamais confiance au prix envoyé par le navigateur
--  (§13.3), et la commande reste exacte même si le produit évolue.
--  product_id / variant_id sont SET NULL pour préserver l'historique.
-- ───────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `order_items` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`       BIGINT UNSIGNED NOT NULL,
    `product_id`     INT UNSIGNED NULL,
    `variant_id`     INT UNSIGNED NULL,
    `product_name`   VARCHAR(180) NOT NULL,
    `product_name_en` VARCHAR(180) NULL,
    `sku`            VARCHAR(64)  NULL,
    `size`           VARCHAR(20)  NOT NULL DEFAULT 'UNIQUE',
    `quantity`       INT UNSIGNED NOT NULL DEFAULT 1,
    `unit_price`     DECIMAL(12,0) NOT NULL,
    `line_total`     DECIMAL(12,0) NOT NULL,
    `image_path`     VARCHAR(255) NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_items_order` (`order_id`),
    KEY `idx_order_items_product` (`product_id`),
    KEY `idx_order_items_variant` (`variant_id`),
    CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`)
        REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_order_items_variant` FOREIGN KEY (`variant_id`)
        REFERENCES `product_variants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────
--  order_status_history — traçabilité des changements de statut (§7)
-- ───────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `order_status_history` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`   BIGINT UNSIGNED NOT NULL,
    `status`     ENUM('pending','confirmed','preparing','shipped','delivered','cancelled') NOT NULL,
    `note`       VARCHAR(255) NULL,
    `changed_by` INT UNSIGNED NULL COMMENT 'NULL = changement automatique ou client',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status_history_order` (`order_id`, `created_at`),
    KEY `idx_status_history_user` (`changed_by`),
    CONSTRAINT `fk_status_history_order` FOREIGN KEY (`order_id`)
        REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_status_history_user` FOREIGN KEY (`changed_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────
--  settings — paramètres de la boutique (clé / valeur)
--  Contient notamment le lien de paiement Wave global.
-- ───────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
    `key`        VARCHAR(80) NOT NULL,
    `value`      TEXT        NULL,
    `updated_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────
--  audit_logs — journal des actions sensibles (§13.2)
--  L'IP est stockée sous forme de HMAC, jamais en clair.
-- ───────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NULL,
    `action`     VARCHAR(80)  NOT NULL COMMENT 'login, product.create, order.status_change...',
    `entity`     VARCHAR(60)  NULL COMMENT 'table ou type de ressource',
    `entity_id`  VARCHAR(64)  NULL,
    `ip_hash`    CHAR(64)     NULL,
    `metadata`   JSON         NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_user` (`user_id`),
    KEY `idx_audit_action_created` (`action`, `created_at`),
    KEY `idx_audit_entity` (`entity`, `entity_id`),
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ═══════════════════════════════════════════════════════════════════════════
--  Données de référence
--  Le compte administrateur est créé par database/seed.sql, jamais ici.
-- ═══════════════════════════════════════════════════════════════════════════

-- Lien de paiement Wave Business (lien global, cf. décision Phase 1).
-- credit_name / credit_url : mention « site créé par » dans le pied de page
-- et sur les pages de connexion, modifiable depuis l'admin.
INSERT INTO `settings` (`key`, `value`) VALUES
    ('wave_payment_link', ''),
    ('shop_email',        'contact@wylde.sn'),
    ('shop_phone',        ''),
    ('shop_address',      ''),
    ('free_shipping_threshold', '0'),
    ('credit_name',       'Jef Tech'),
    ('credit_url',        '')
ON DUPLICATE KEY UPDATE `value` = `value`;

-- Zone de livraison initiale : Sénégal / Dakar = 2 000 FCFA.
INSERT INTO `shipping_zones`
    (`label`, `country_code`, `city`, `country_name`, `price`, `is_default`, `status`, `sort_order`)
VALUES
    ('Sénégal — Dakar',        'SN', 'Dakar', 'Sénégal', 2000, 0, 'active', 1),
    ('Sénégal — autres villes', 'SN', NULL,    'Sénégal', 3000, 0, 'active', 2),
    ('Reste du monde',        NULL, NULL,     NULL,      8000, 1, 'active', 99);
