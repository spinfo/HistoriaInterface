
-- Create syntax for TABLE 'wp_shtm_mapstops_to_scenes'
CREATE TABLE `wp_shtm_mapstops_to_scenes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mapstop_id` bigint(20) unsigned NOT NULL,
  `scene_id` bigint(20) NOT NULL,
  `coordinate_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('info','route') COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'info',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mapstop_id` (`mapstop_id`,`scene_id`),
  CONSTRAINT `wp_shtm_mapstops_to_scenes_ibfk_1` FOREIGN KEY (`mapstop_id`) REFERENCES `wp_shtm_mapstops` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Create syntax for TABLE 'wp_shtm_scenes'
CREATE TABLE `wp_shtm_scenes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tour_id` bigint(20) unsigned NOT NULL,
  `post_id` bigint(20) unsigned NOT NULL,
  `position` smallint(5) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_id` (`post_id`),
  KEY `tour_id` (`tour_id`,`position`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

-- alter coordinates
ALTER TABLE `wp_shtm_coordinates` MODIFY `lat` DECIMAL(10,6);
ALTER TABLE `wp_shtm_coordinates` MODIFY `lon` DECIMAL(10,6);

-- alter tours
ALTER TABLE `wp_shtm_tours` MODIFY `type` ENUM('round-tour','tour','public-transport-tour','bike-tour','indoor-tour');