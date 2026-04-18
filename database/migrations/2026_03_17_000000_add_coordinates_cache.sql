-- Add GPS coordinates to address table (for rider locations)
ALTER TABLE `address` 
ADD COLUMN `latitude` DECIMAL(10, 8) NULL AFTER `address`,
ADD COLUMN `longitude` DECIMAL(11, 8) NULL AFTER `latitude`,
ADD INDEX `idx_coordinates` (`latitude`, `longitude`);

-- Add GPS coordinates to parcel_details table (for client/dropoff locations)
ALTER TABLE `parcel_details`
ADD COLUMN `client_latitude` DECIMAL(10, 8) NULL AFTER `client_address`,
ADD COLUMN `client_longitude` DECIMAL(11, 8) NULL AFTER `client_latitude`,
ADD INDEX `idx_client_coordinates` (`client_latitude`, `client_longitude`);

-- Verify changes
DESCRIBE address;
DESCRIBE parcel;
DESCRIBE parcel_details;
