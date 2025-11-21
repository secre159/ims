-- Add remember_token column to users table for Remember Me functionality
-- Run this SQL directly on your database

ALTER TABLE `users` 
ADD COLUMN `remember_token` VARCHAR(255) NULL DEFAULT NULL
AFTER `password`;

-- Verify the column was added
SHOW COLUMNS FROM `users` LIKE 'remember_token';
