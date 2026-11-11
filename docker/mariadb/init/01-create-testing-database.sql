-- Runs once, when the mariadb volume is first initialised.
-- The test suite (phpunit.xml) runs in this separate database.
CREATE DATABASE IF NOT EXISTS heavy_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
