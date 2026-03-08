#!/usr/bin/env python3
"""Debug: Check database state"""
import mysql.connector

DB_CONFIG = {
    'host': '127.0.0.1',
    'user': 'root',
    'password': 'www.saemoon.26',
    'database': 'courier_app'
}

conn = mysql.connector.connect(**DB_CONFIG)
cursor = conn.cursor(dictionary=True)

print("\n" + "="*80)
print("DATABASE DEBUG CHECK")
print("="*80)

# Check parcels
print("\nPARCELS:")
cursor.execute("""
    SELECT parcel_id, pickup_city, pickup_location, assigned_to, parcel_status
    FROM parcel
    ORDER BY parcel_id DESC
    LIMIT 10
""")
parcels = cursor.fetchall()
print(f"Total parcels: {len(parcels)}")
for p in parcels:
    print(f"  ID: {p['parcel_id']} | City: {p['pickup_city']} | Status: {p['parcel_status']} | Assigned: {p['assigned_to']}")

# Check pending parcels
print("\nPENDING PARCELS (assigned_to IS NULL):")  
cursor.execute("""
    SELECT parcel_id, pickup_city, pickup_location, assigned_to, parcel_status
    FROM parcel
    WHERE assigned_to IS NULL AND parcel_status = 'pending'
""")
pending = cursor.fetchall()
print(f"Count: {len(pending)}")
for p in pending:
    print(f"  ID: {p['parcel_id']} | City: '{p['pickup_city']}' | Location: {p['pickup_location']}")

# Check riders
print("\nRIDERS:")
cursor.execute("""
    SELECT u.id, u.first_name, u.role, u.status, a.city, a.address
    FROM users u
    LEFT JOIN address a ON u.id = a.user_id
    WHERE u.role = 'rider'
""")
riders = cursor.fetchall()
print(f"Total riders: {len(riders)}")
for r in riders:
    print(f"  ID: {r['id']} | Name: {r['first_name']} | City: '{r['city']}' | Status: {r['status']}")

# Check city match
print("\nCITY MATCHING CHECK:")
if pending and riders:
    for p in pending[:3]:
        parcel_city = p['pickup_city'].lower().strip() if p['pickup_city'] else ''
        print(f"\nParcel #{p['parcel_id']} city: '{parcel_city}'")
        
        matching_riders = []
        for r in riders:
            rider_city = r['city'].lower().strip() if r['city'] else ''
            if rider_city == parcel_city:
                matching_riders.append(r)
        
        print(f"  Matching riders: {len(matching_riders)}")
        for r in matching_riders:
            print(f"    - {r['first_name']} (ID: {r['id']}) in '{r['city']}'")

conn.close()
print("\n" + "="*80)
