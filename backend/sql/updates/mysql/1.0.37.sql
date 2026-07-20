-- Remap list-field relations to the lowest value ID before removing duplicates.
UPDATE `#__ishop_fields_map` AS `map`
INNER JOIN `#__ishop_fields` AS `field`
    ON `field`.`id` = `map`.`field_id`
   AND `field`.`type` = 1
INNER JOIN (
    SELECT
        `duplicate`.`id` AS `duplicate_id`,
        `canonical`.`id` AS `canonical_id`,
        `duplicate`.`field_id`
    FROM `#__ishop_values` AS `duplicate`
    INNER JOIN `#__ishop_values` AS `canonical`
        ON `canonical`.`field_id` = `duplicate`.`field_id`
       AND `canonical`.`value` = `duplicate`.`value`
       AND `canonical`.`language` = `duplicate`.`language`
       AND `canonical`.`id` < `duplicate`.`id`
    LEFT JOIN `#__ishop_values` AS `earlier`
        ON `earlier`.`field_id` = `duplicate`.`field_id`
       AND `earlier`.`value` = `duplicate`.`value`
       AND `earlier`.`language` = `duplicate`.`language`
       AND `earlier`.`id` < `canonical`.`id`
    WHERE `earlier`.`id` IS NULL
) AS `duplicates`
    ON `duplicates`.`field_id` = `map`.`field_id`
   AND `map`.`value` = `duplicates`.`duplicate_id`
SET `map`.`value` = `duplicates`.`canonical_id`;

DELETE `duplicate`
FROM `#__ishop_values` AS `duplicate`
INNER JOIN `#__ishop_values` AS `canonical`
    ON `canonical`.`field_id` = `duplicate`.`field_id`
   AND `canonical`.`value` = `duplicate`.`value`
   AND `canonical`.`language` = `duplicate`.`language`
   AND `canonical`.`id` < `duplicate`.`id`;

ALTER TABLE `#__ishop_values`
    ADD UNIQUE INDEX `uniq_field_value_language` (`field_id`, `value`, `language`);
