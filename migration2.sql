CREATE TABLE `wp_shtm_mapstops_to_scenes_copy` LIKE `wp_shtm_mapstops_to_scenes`;
INSERT `wp_shtm_mapstops_to_scenes_copy` SELECT * FROM `wp_shtm_mapstops_to_scenes`;

CREATE TABLE `wp_shtm_scenes_copy` LIKE `wp_shtm_scenes`;
INSERT `wp_shtm_scenes_copy` SELECT * FROM `wp_shtm_scenes`;

ALTER TABLE `wp_shtm_mapstops_to_scenes` MODIFY `scene_id` bigint(20) unsigned NOT NULL;

ALTER TABLE `wp_shtm_scenes` DROP COLUMN `id`;
ALTER TABLE `wp_shtm_scenes` CHANGE `post_id` `id` bigint(20) unsigned PRIMARY KEY NOT NULL;