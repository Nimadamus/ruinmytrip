-- Migration 096 - the languages a traveler speaks. See the pgsql file.
ALTER TABLE profiles ADD COLUMN languages TEXT;
