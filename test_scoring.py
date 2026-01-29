import mysql.connector

db_config = {'host': '127.0.0.1', 'user': 'root', 'password': 'www.saemoon.26', 'database': 'courier_app'}

def calculate_distance_score(pickup_location, rider_address):
    pickup_words = set(pickup_location.lower().split())
    rider_words = set(rider_address.lower().split())
    if not pickup_words or not rider_words:
        return 0
    intersection = pickup_words.intersection(rider_words)
    union = pickup_words.union(rider_words)
    jaccard = len(intersection) / len(union) if union else 0
    area_boost = sum(0.1 for word in intersection if len(word) > 3)
    return jaccard + area_boost

conn = mysql.connector.connect(**db_config)
cursor = conn.cursor(dictionary=True)

pickup = "Gulshan-e-Iqbal Block 15"
print(f"Pickup Location: {pickup}\n")

cursor.execute("""
    SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as name, 
           a.address, u.rating, COUNT(p.parcel_id) as active
    FROM users u
    JOIN address a ON u.id = a.user_id
    LEFT JOIN parcel p ON u.id = p.assigned_to AND p.parcel_status IN ('pending','picked_up','in_transit')
    WHERE u.role = 'rider' AND LOWER(a.city) = 'karachi'
    GROUP BY u.id, name, a.address, u.rating
    ORDER BY active
""")

print("RIDER SCORING:")
for r in cursor.fetchall():
    score = calculate_distance_score(pickup, r['address'])
    status = "OK" if r['active'] < 5 else "FULL"
    print(f"{status} ID:{r['id']:2d} {r['name']:20s} Score:{score:.3f} Active:{r['active']}/5 Rating:{r['rating']}")
    print(f"   Address: {r['address']}")

conn.close()
