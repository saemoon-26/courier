# Frontend Integration Guide - Rider Registration

## 🚨 422 Error Fix Guide

### 1. Required Fields Check
Make sure these fields are **required** and not empty:
```javascript
{
    "full_name": "string (required)",
    "email": "valid email (required)", 
    "password": "min 6 chars (required)",
    "mobile_primary": "string (required)",
    "vehicle_type": "string (required)",
    "city": "string (required)",
    "state": "string (required)", 
    "address": "string (required)"
}
```

### 2. API Endpoint
```
POST http://localhost:8000/api/rider-registrations
```

### 3. Headers
```javascript
{
    'Content-Type': 'application/json',
    'Accept': 'application/json'
}
```

### 4. Sample Working Request
```javascript
const registrationData = {
    full_name: 'John Doe',
    father_name: 'Father Name', // optional
    email: 'john@example.com',
    password: 'password123',
    mobile_primary: '03001234567',
    mobile_alternate: '', // can be empty
    cnic_number: '', // can be empty
    vehicle_type: 'Bike',
    vehicle_brand: '', // can be empty
    vehicle_model: '', // can be empty
    vehicle_registration: '', // can be empty
    driving_license_number: '', // can be empty
    city: 'Karachi',
    state: 'Sindh',
    address: 'Complete address here',
    bank_name: '', // can be empty
    account_number: '', // can be empty
    account_title: '' // can be empty
};

// Send request
const response = await fetch('http://localhost:8000/api/rider-registrations', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    },
    body: JSON.stringify(registrationData)
});

const result = await response.json();
```

### 5. Debug Your Data
Use this endpoint to see what data you're sending:
```
POST http://localhost:8000/api/debug-registration
```

### 6. Common Issues & Solutions

#### Issue: Empty strings vs null
❌ **Wrong**: Sending `null` values
✅ **Correct**: Send empty strings `""` for optional fields

#### Issue: Missing required fields
❌ **Wrong**: Not sending required fields
✅ **Correct**: Always send all required fields

#### Issue: Wrong data types
❌ **Wrong**: Sending numbers as strings for boolean fields
✅ **Correct**: Send proper data types

#### Issue: CORS errors
✅ **Solution**: Backend already configured for localhost:3000 and localhost:5173

### 7. Error Response Format
```javascript
// 422 Validation Error Response
{
    "status": false,
    "message": "Validation failed",
    "errors": {
        "email": ["The email field is required."],
        "password": ["The password must be at least 6 characters."]
    }
}

// 201 Success Response  
{
    "status": true,
    "message": "Rider registration submitted successfully. Admin will review your application.",
    "data": {
        "registration_id": 123,
        "registration": { ... }
    }
}
```

### 8. Status Check API
After registration, rider can check status:
```javascript
const statusResponse = await fetch('http://localhost:8000/api/rider-registrations/check-status', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        email: 'rider@example.com'
    })
});
```

### 9. Test Page
Open `http://localhost:8000/test-registration.html` in browser to test the API directly.

## 🔧 Quick Debug Steps:
1. Check browser console for exact error
2. Verify all required fields are present
3. Check data types match requirements
4. Use debug endpoint to see received data
5. Compare with working sample above

Backend is working perfectly - issue is likely in frontend data formatting! 🚀