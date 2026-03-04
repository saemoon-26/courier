# Normalized Rider Database Structure

## ✅ Successfully Created

All new tables created successfully! Old tables (rider_registrations, riders_auth) are kept intact.

## Created Tables

### 1. `riders` - Rider specific info
- id
- user_id (links to users table)
- father_name
- mobile_primary
- mobile_alternate
- cnic_number (UNIQUE)
- driving_license_number (UNIQUE)
- timestamps

### 2. `rider_documents` - All rider documents
- id
- rider_id
- document_type (enum)
- document_path
- status (pending/approved/rejected)
- rejection_reason
- uploaded_at
- verified_at

### 3. `rider_vehicles` - Vehicle information
- id
- rider_id
- vehicle_type
- vehicle_brand
- vehicle_model
- vehicle_registration
- registration_no
- timestamps

### 4. `rider_banks` - Banking information
- id
- rider_id (UNIQUE)
- bank_name
- account_number (encrypted via model)
- account_title
- timestamps

## Created Models

- `Rider.php` - Main rider model with relationships
- `RiderDocument.php` - Document management
- `RiderVehicle.php` - Vehicle information
- `RiderBank.php` - Banking with auto-encryption

## Old Tables (Kept Intact)

- `rider_registrations` - Still exists, not modified
- `riders_auth` - Still exists, not modified
- `users` - Not touched, can be extended later if needed

## Usage Example

```php
// Create a new rider
$rider = Rider::create([
    'user_id' => $userId,
    'father_name' => 'Father Name',
    'mobile_primary' => '03001234567',
    'cnic_number' => '12345-1234567-1',
    'driving_license_number' => 'DL123456',
]);

// Add vehicle
$rider->vehicle()->create([
    'vehicle_type' => 'Bike',
    'vehicle_brand' => 'Honda',
    'vehicle_model' => 'CD 70',
]);

// Add bank
$rider->bank()->create([
    'bank_name' => 'HBL',
    'account_number' => '1234567890', // Auto-encrypted
    'account_title' => 'Rider Name',
]);

// Add document
$rider->documents()->create([
    'document_type' => 'cnic_front',
    'document_path' => '/path/to/cnic.jpg',
    'status' => 'pending',
]);
```
