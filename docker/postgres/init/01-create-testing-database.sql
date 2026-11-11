-- Runs once, when the postgres volume is first initialised.
-- The test suite (phpunit.xml) runs against this separate database.
CREATE DATABASE heavy_testing;
