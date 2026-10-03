-- ═══════════════════════════════════════════════════════════════════════
--  WYLDE — Données de démonstration (Phase 1)
--  À exécuter APRÈS schema.sql. Idempotent sur les slugs (INSERT ... ON
--  DUPLICATE KEY UPDATE).
--
--  Conventions alignées sur le schéma :
--    - produits.published_at renseigné pour le tri « nouveautés » ;
--    - sale_price = prix promotionnel (prix barré à l'affichage) ;
--    - variante UNIQUE tautomobiles produits sans tailles ; sinon S..XXL ;
--    - aucune image (path NULL) : les cartes affichent un placeholder.
-- ═══════════════════════════════════════════════════════════════════════

-- ── Catégories ──────────────────────────────────────────────────────────
INSERT INTO `categories` (`parent_id`, `name`, `name_en`, `slug`, `description`, `status`, `sort_order`) VALUES
    (NULL, 'T-shirts', 'T-shirts', 't-shirts', 'Les basiques qui se portent toute l''année.', 'active', 10),
    (NULL, 'Vestes', 'Jackets', 'vestes', 'Cuir, denim et plus pour les saisons fraîches.', 'active', 20),
    (NULL, 'Hauts', 'Tops', 'hauts', 'Chemises, polos et pulls.', 'active', 30),
    (NULL, 'Accessoires', 'Accessories', 'accessoires', 'Ceintures, sacs et chapeaux.', 'active', 40)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `name_en` = VALUES(`name_en`);

SET @cat_tshirts    = (SELECT `id` FROM `categories` WHERE `slug` = 't-shirts' LIMIT 1);
SET @cat_vestes     = (SELECT `id` FROM `categories` WHERE `slug` = 'vestes' LIMIT 1);
SET @cat_hauts      = (SELECT `id` FROM `categories` WHERE `slug` = 'hauts' LIMIT 1);
SET @cat_access     = (SELECT `id` FROM `categories` WHERE `slug` = 'accessoires' LIMIT 1);

-- ── Produits ────────────────────────────────────────────────────────────
INSERT INTO `products`
    (`category_id`, `name`, `name_en`, `slug`, `short_description`, `short_description_en`,
     `description`, `price`, `sale_price`, `cost_price`, `status`, `label`, `sku`,
     `has_sizes`, `is_featured`, `sold_count`, `published_at`) VALUES
    (@cat_tshirts, 'T-shirt Essentiel Noir', 'Essential Black Tee', 't-shirt-essentiel-noir',
     '100% coton peigné, coupe droite.', 'Brushed cotton, regular fit.',
     'T-shirt en coton peigné 180 g/m², coupe droite, col ras du cou renforcé. Fabriqué en ateliers certifiés. Lavage à 30°C, séchage à plat.', 12000, 9000, 4200, 'published', 'bestseller', 'WT-BLK-S', 1, 1, 214, '2026-09-15 09:00:00'),
    (@cat_tshirts, 'T-shirt Essentiel Blanc', 'Essential White Tee', 't-shirt-essentiel-blanc',
     'Le basique blanc qui va avec tout.', 'The white staple that goes with anything.',
     'Même construction que le modèle noir, teinture Azo-free. Blouseur léger, coupe standard. Testé au laboratoire pour 30 lavages.', 12000, NULL, 4200, 'published', 'new', 'WT-WHT-S', 1, 1, 96, '2026-09-20 09:00:00'),
    (@cat_vestes, 'Veste Denim Oversize', 'Oversized Denim Jacket', 'veste-denim-oversize',
     'Coupe ample, délavage brut.', 'Ample cut, raw wash.',
     'Denim 14 oz, coupe oversize assumée. Boutons corozo, intérieur du colppe renforcée. Délavage progressif unique par pièce.', 45000, 38000, 21000, 'published', 'limited', 'DJ-DEN-O', 1, 0, 12, '2026-09-10 09:00:00'),
    (@cat_vestes, 'Veste Cuir Noire', 'Black Leather Jacket', 'veste-cuir-noire',
     'Cuir pleine fleur, finition satinée.', 'Full-grain leather, satin finish.',
     'Cuir pleine fleur 1.2 mm, doublure coton. Zips YKK, fermeture homme/femme unisexe. Patine garantie avec le temps.', 95000, NULL, 61000, 'published', 'bestseller', 'LJ-BLK-O', 1, 1, 58, '2026-09-05 09:00:00'),
    (@cat_hauts, 'Pull Col Roulé Cachemire', 'Cashmere Roll Neck Pullover', 'pull-col-roule-cachemire',
     'Cachemire doux, chaleur sans volume.', 'Soft cashmere, warmth without bulk.',
     'Cachemire 100%, 2 plis. Finition irréprochable, ourlet invisible. Entretien délicat recommandé.', 65000, 52000, 39000, 'published', 'new', 'CN-CRS-F', 1, 0, 33, '2026-09-18 09:00:00'),
    (@cat_hauts, 'Chemise Oxford Occasionnelle', 'Casual Oxford Shirt', 'chemise-oxford-casual',
     'Oxford épais, coupe semi-moulante.', 'Thick oxford, semi-fitted cut.',
     'Coton oxford 130 g/m², boutons nacre imitation. Idéale en jean comme en chino. Repassage facile.', 22000, NULL, 9800, 'published', 'none', 'OX-OXF-M', 1, 0, 7, '2026-09-22 09:00:00'),
    (@cat_access, 'Sacoche Toile Officielle', 'Official Canvas Messenger', 'sacoche-toile-officielle',
     'Large et durable, entièrement invisible.', 'Roomy and durable, fully stealth.',
     'Toile 18 oz enduite, bretelle réglable, poche fermeture aimantée. Volume 8 L : document, tablette 10", petite gourde.', 25000, 22000, 13400, 'published', 'bestseller', 'AC-CNV-B', 1, 0, 87, '2026-09-12 09:00:00'),
    (@cat_access, 'Casquette Overcast', 'Overcast Cap', 'casquette-overcast',
     'Coton brossé, visière aplatie, broderie mate.', 'Brushed cotton, flat brim, matte embroidery.',
     'Structure 6 panneaux, étiquette brodée ton sur ton. Taille réglable par bande auto-agrippante.', 9000, NULL, 3400, 'published', 'new', 'AC-OVR-O', 1, 0, 22, '2026-09-25 09:00:00')
ON DUPLICATE KEY UPDATE `price` = VALUES(`price`), `status` = VALUES(`status`);

-- ── Variantes ───────────────────────────────────────────────────────────
-- T-shirts, vestes, pulls, chemises, casquette : S/M/L/XL (+XXL sur tee).
-- Sacoche : variante UNIQUE.
INSERT INTO `product_variants` (`product_id`, `size`, `sku`, `stock`, `is_default`, `sort_order`) VALUES
    ((SELECT `id` FROM `products` WHERE `slug` = 't-shirt-essentiel-noir' LIMIT 1), 'S', 'WT-BLK-S', 12, 1, 10),
    ((SELECT `id` FROM `products` WHERE `slug` = 't-shirt-essentiel-noir' LIMIT 1), 'M', 'WT-BLK-M', 18, 0, 20),
    ((SELECT `id` FROM `products` WHERE `slug` = 't-shirt-essentiel-noir' LIMIT 1), 'L', 'WT-BLK-L', 14, 0, 30),
    ((SELECT `id` FROM `products` WHERE `slug` = 't-shirt-essentiel-noir' LIMIT 1), 'XL', 'WT-BLK-X', 8, 0, 40),
    ((SELECT `id` FROM `products` WHERE `slug` = 't-shirt-essentiel-noir' LIMIT 1), 'XXL', 'WT-BLK-XX', 4, 0, 50),

    ((SELECT `id` FROM `products` WHERE `slug` = 't-shirt-essentiel-blanc' LIMIT 1), 'S', 'WT-WHT-S', 15, 1, 10),
    ((SELECT `id` FROM `products` WHERE `slug` = 't-shirt-essentiel-blanc' LIMIT 1), 'M', 'WT-WHT-M', 20, 0, 20),
    ((SELECT `id` FROM `products` WHERE `slug` = 't-shirt-essentiel-blanc' LIMIT 1), 'L', 'WT-WHT-L', 16, 0, 30),
    ((SELECT `id` FROM `products` WHERE `slug` = 't-shirt-essentiel-blanc' LIMIT 1), 'XL', 'WT-WHT-X', 9, 0, 40),

    ((SELECT `id` FROM `products` WHERE `slug` = 'veste-denim-oversize' LIMIT 1), 'S', 'DJ-DEN-S', 3, 0, 10),
    ((SELECT `id` FROM `products` WHERE `slug` = 'veste-denim-oversize' LIMIT 1), 'M', 'DJ-DEN-M', 5, 0, 20),
    ((SELECT `id` FROM `products` WHERE `slug` = 'veste-denim-oversize' LIMIT 1), 'L', 'DJ-DEN-L', 4, 0, 30),
    ((SELECT `id` FROM `products` WHERE `slug` = 'veste-denim-oversize' LIMIT 1), 'XL', 'DJ-DEN-X', 2, 0, 40),

    ((SELECT `id` FROM `products` WHERE `slug` = 'veste-cuir-noire' LIMIT 1), 'S', 'LJ-BLK-S', 4, 0, 10),
    ((SELECT `id` FROM `products` WHERE `slug` = 'veste-cuir-noire' LIMIT 1), 'M', 'LJ-BLK-M', 6, 0, 20),
    ((SELECT `id` FROM `products` WHERE `slug` = 'veste-cuir-noire' LIMIT 1), 'L', 'LJ-BLK-L', 5, 0, 30),
    ((SELECT `id` FROM `products` WHERE `slug` = 'veste-cuir-noire' LIMIT 1), 'XL', 'LJ-BLK-X', 2, 0, 40),

    ((SELECT `id` FROM `products` WHERE `slug` = 'pull-col-roule-cachemire' LIMIT 1), 'F', 'CN-CRS-F', 8, 1, 10),
    ((SELECT `id` FROM `products` WHERE `slug` = 'pull-col-roule-cachemire' LIMIT 1), 'M', 'CN-CRS-M', 6, 0, 20),
    ((SELECT `id` FROM `products` WHERE `slug` = 'pull-col-roule-cachemire' LIMIT 1), 'L', 'CN-CRS-L', 5, 0, 30),

    ((SELECT `id` FROM `products` WHERE `slug` = 'chemise-oxford-casual' LIMIT 1), 'S', 'OX-OXF-S', 7, 1, 10),
    ((SELECT `id` FROM `products` WHERE `slug` = 'chemise-oxford-casual' LIMIT 1), 'M', 'OX-OXF-M', 11, 0, 20),
    ((SELECT `id` FROM `products` WHERE `slug` = 'chemise-oxford-casual' LIMIT 1), 'L', 'OX-OXF-L', 9, 0, 30),
    ((SELECT `id` FROM `products` WHERE `slug` = 'chemise-oxford-casual' LIMIT 1), 'XL', 'OX-OXF-X', 3, 0, 40),

    ((SELECT `id` FROM `products` WHERE `slug` = 'sacoche-toile-officielle' LIMIT 1), 'UNIQUE', 'AC-CNV-B', 20, 1, 10),

    ((SELECT `id` FROM `products` WHERE `slug` = 'casquette-overcast' LIMIT 1), 'UNIQUE', 'AC-OVR-O', 11, 1, 10);

-- ── Zones de livraison ─────────────────────────────────────────────────
-- Reprise des lignes initiales du schéma (Dakar 2000, autres villes 3000,
-- repli « reste du monde » 8000) : réexécuter le seed est idempotent.
-- NB : si la décision produit est de limiter la livraison à Dakar
-- (is_default ne pointant que vers le Sénégal), ajuster ici et dans
-- schema.sql.
DELETE FROM `shipping_zones`;
INSERT INTO `shipping_zones`
    (`label`, `country_code`, `city`, `country_name`, `price`, `is_default`, `status`, `sort_order`) VALUES
    ('Sénégal — Dakar',          'SN', 'Dakar', 'Sénégal', 2000, 0, 'active', 1),
    ('Sénégal — autres villes',  'SN', NULL,    'Sénégal', 3000, 0, 'active', 2),
    ('Reste du monde',           NULL, NULL,     NULL,     8000, 1, 'active', 99);