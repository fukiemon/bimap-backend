-- Fixes a real bug in the existing driver_location table: `longitude
-- decimal(10,8)` only allows 2 digits before the decimal point (max
-- ±99.99999999), which overflows for any real-world longitude outside a
-- narrow band near 0° (e.g. the Philippines sits around 121-126°E).
-- decimal(11,8) allows up to ±999.99999999, comfortably covering the full
-- valid longitude range of -180 to 180.
--
-- Run this once: mysql -u root bimapcapstone_db < migrations/fix_driver_location_longitude.sql

ALTER TABLE `driver_location` MODIFY `longitude` decimal(11,8) NOT NULL;
