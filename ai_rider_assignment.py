#!/usr/bin/env python3
import json
import mysql.connector
import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestClassifier
from sklearn.preprocessing import LabelEncoder
from sklearn.metrics.pairwise import cosine_similarity
import warnings
import math
import requests
import time
warnings.filterwarnings('ignore')

class RealAIRiderAssignment:
    def __init__(self):
        self.db_config = {
            'host': '127.0.0.1',
            'user': 'root',
            'password': 'www.saemoon.26',
            'database': 'courier_app'
        }
        self.model = RandomForestClassifier(n_estimators=100, random_state=42)
        self.label_encoder = LabelEncoder()
        
    def connect_db(self):
        return mysql.connector.connect(**self.db_config)
    
    def get_data(self):
        conn = self.connect_db()
        cursor = conn.cursor(dictionary=True)
        
        # Get pending parcels with coordinates (cached in DB)
        cursor.execute("""
            SELECT p.parcel_id, p.pickup_city, p.pickup_location, p.pickup_lat, p.pickup_lng,
                   pd.client_address, pd.client_latitude, pd.client_longitude
            FROM parcel p
            LEFT JOIN parcel_details pd ON p.parcel_id = pd.parcel_id
            WHERE p.assigned_to IS NULL AND p.parcel_status = 'pending'
        """)
        parcels = cursor.fetchall()
        
        # Normalize city names
        for parcel in parcels:
            if parcel['pickup_city']:
                parcel['pickup_city'] = parcel['pickup_city'].lower().strip()
        
        # Get available riders with coordinates (cached in DB)
        cursor.execute("""
            SELECT u.id, u.first_name, u.last_name, 
                   a.city, a.address, a.latitude, a.longitude,
                   COUNT(CASE WHEN p.parcel_status IN ('pending', 'picked_up', 'in_transit', 'out_for_delivery') THEN 1 END) as active_parcels
            FROM users u
            JOIN address a ON u.address_id = a.id
            LEFT JOIN parcel p ON u.id = p.assigned_to
            WHERE u.role = 'rider' AND u.status = 'active'
            GROUP BY u.id, u.first_name, u.last_name, a.city, a.address, a.latitude, a.longitude
            HAVING active_parcels < 5
        """)
        riders = cursor.fetchall()
        
        # Normalize city names
        for rider in riders:
            if rider['city']:
                rider['city'] = rider['city'].lower().strip()
        
        # Get historical assignments for training
        cursor.execute("""
            SELECT p.pickup_city, p.pickup_location,
                   u.id as rider_id, a.city as rider_city, a.address as rider_address,
                   p.parcel_status,
                   CASE WHEN p.parcel_status = 'delivered' THEN 1 ELSE 0 END as success
            FROM parcel p
            JOIN users u ON p.assigned_to = u.id
            JOIN address a ON u.address_id = a.id
            WHERE p.assigned_to IS NOT NULL
            LIMIT 1000
        """)
        training_data = cursor.fetchall()
        
        conn.close()
        return parcels, riders, training_data
    
    def geocode_address(self, address, city):
        """Get GPS coordinates from address using Nominatim with fallback"""
        # Try multiple search strategies
        search_queries = [
            f"{address}, {city}, Pakistan",
            f"{address} {city} Pakistan",
            f"{city}, {address}, Pakistan",
            f"{city} Pakistan"  # Fallback to city center
        ]
        
        for query in search_queries:
            try:
                url = "https://nominatim.openstreetmap.org/search"
                params = {
                    'q': query,
                    'format': 'json',
                    'limit': 1,
                    'countrycodes': 'pk'
                }
                headers = {'User-Agent': 'CourierApp/1.0'}
                
                time.sleep(1.2)  # Rate limit
                response = requests.get(url, params=params, headers=headers, timeout=15)
                
                if response.status_code == 200:
                    data = response.json()
                    if data and len(data) > 0:
                        return float(data[0]['lat']), float(data[0]['lon'])
            except:
                continue
        
        return None, None
    
    def haversine_distance(self, lat1, lon1, lat2, lon2):
        """Calculate actual distance in KM using Haversine formula"""
        if None in [lat1, lon1, lat2, lon2]:
            return 999  # Large distance if coordinates missing
        
        R = 6371  # Earth radius in KM
        
        lat1_rad = math.radians(lat1)
        lat2_rad = math.radians(lat2)
        delta_lat = math.radians(lat2 - lat1)
        delta_lon = math.radians(lon2 - lon1)
        
        a = math.sin(delta_lat/2)**2 + math.cos(lat1_rad) * math.cos(lat2_rad) * math.sin(delta_lon/2)**2
        c = 2 * math.atan2(math.sqrt(a), math.sqrt(1-a))
        
        return R * c
    
    def calculate_distance_score(self, parcel, rider):
        """Calculate real GPS distance using cached coordinates"""
        # Get parcel coordinates (from DB cache or geocode)
        parcel_lat = parcel.get('pickup_lat')
        parcel_lon = parcel.get('pickup_lng')
        
        # If not cached, geocode and cache
        if not parcel_lat or not parcel_lon:
            parcel_lat, parcel_lon = self.geocode_and_cache_parcel(parcel)
        
        # Get rider coordinates (from DB cache or geocode)
        rider_lat = rider.get('latitude')
        rider_lon = rider.get('longitude')
        
        # If not cached, geocode and cache
        if not rider_lat or not rider_lon:
            rider_lat, rider_lon = self.geocode_and_cache_rider(rider)
        
        # Calculate distance
        distance_km = self.haversine_distance(parcel_lat, parcel_lon, rider_lat, rider_lon)
        
        # Convert to score (closer = higher score)
        if distance_km is None or distance_km >= 10:
            return 0
        
        score = 1 - (distance_km / 10)
        return max(0, score)
    
    def geocode_and_cache_parcel(self, parcel):
        """Geocode parcel and cache in database"""
        lat, lon = self.geocode_address(parcel['pickup_location'], parcel['pickup_city'])
        
        if lat and lon:
            conn = self.connect_db()
            cursor = conn.cursor()
            cursor.execute(
                "UPDATE parcel SET pickup_lat = %s, pickup_lng = %s WHERE parcel_id = %s",
                (lat, lon, parcel['parcel_id'])
            )
            conn.commit()
            conn.close()
        
        return lat, lon
    
    def geocode_and_cache_rider(self, rider):
        """Geocode rider address and cache in database"""
        lat, lon = self.geocode_address(rider['address'], rider['city'])
        
        if lat and lon:
            conn = self.connect_db()
            cursor = conn.cursor()
            cursor.execute(
                "UPDATE address SET latitude = %s, longitude = %s WHERE user_id = (SELECT id FROM users WHERE id = %s LIMIT 1)",
                (lat, lon, rider['id'])
            )
            conn.commit()
            conn.close()
        
        return lat, lon
    
    def train_model(self, training_data):
        """Train ML model on historical data"""
        if not training_data:
            return False
        
        df = pd.DataFrame(training_data)
        
        # Feature engineering
        df['distance_score'] = df.apply(
            lambda row: 0.5,  # Placeholder for historical data
            axis=1
        )
        
        df['city_match'] = (df['pickup_city'] == df['rider_city']).astype(int)
        
        # Prepare features
        features = ['distance_score', 'city_match']
        X = df[features]
        y = df['success']
        
        # Train model
        self.model.fit(X, y)
        return True
    
    def predict_best_rider(self, parcel, riders):
        """Use ML to predict best rider"""
        if not riders:
            return None
        
        # Normalize parcel city
        parcel_city = parcel['pickup_city'].lower().strip() if parcel['pickup_city'] else ''
        
        # Filter riders by same city first (STRICT REQUIREMENT) and max 5 parcels
        city_riders = [r for r in riders if r['city'].lower().strip() == parcel_city and r['active_parcels'] < 5]
        
        if not city_riders:
            return None  # N/A - no riders in same city or all riders have 5 parcels
        
        # Calculate ML scores for each rider
        rider_scores = []
        for rider in city_riders:
            distance_score = self.calculate_distance_score(parcel, rider)
            
            features = np.array([[
                distance_score,
                1  # city_match = 1 (same city)
            ]])
            
            # ML prediction probability
            ml_score = self.model.predict_proba(features)[0][1] if hasattr(self.model, 'predict_proba') else 0.5
            
            # Combined score: 60% distance + 20% ML + 20% workload
            # Heavy workload penalty - prefer riders with fewer parcels
            workload_penalty = rider['active_parcels'] * 0.2  # 20% penalty per parcel
            combined_score = (distance_score * 0.6) + (ml_score * 0.2) + ((5 - rider['active_parcels']) * 0.04)
            
            rider_scores.append({
                'rider': rider,
                'ml_score': ml_score,
                'distance_score': distance_score,
                'combined_score': combined_score,
                'active_parcels': rider['active_parcels']
            })
        
        # Sort by combined score (highest first)
        rider_scores.sort(key=lambda x: x['combined_score'], reverse=True)
        
        return rider_scores[0]['rider'] if rider_scores else None
    
    def assign_parcels(self):
        """Main ML assignment function"""
        parcels, riders, training_data = self.get_data()
        
        # Train ML model
        model_trained = self.train_model(training_data)
        
        assigned = 0
        conn = self.connect_db()
        cursor = conn.cursor()
        
        for parcel in parcels:
            best_rider = self.predict_best_rider(parcel, riders)
            
            if best_rider:
                # Assign parcel to rider
                cursor.execute(
                    "UPDATE parcel SET assigned_to = %s WHERE parcel_id = %s",
                    (best_rider['id'], parcel['parcel_id'])
                )
                assigned += 1
                
                # Update rider's active parcels count
                for rider in riders:
                    if rider['id'] == best_rider['id']:
                        rider['active_parcels'] += 1
                        break
        
        conn.commit()
        conn.close()
        
        return {
            'assigned': assigned,
            'total_parcels': len(parcels),
            'model_trained': model_trained,
            'message': f'Real ML AI assigned {assigned} parcels using Random Forest'
        }

if __name__ == "__main__":
    try:
        ai = RealAIRiderAssignment()
        result = ai.assign_parcels()
        print(json.dumps(result))
    except Exception as e:
        print(json.dumps({
            'assigned': 0,
            'error': str(e),
            'message': 'ML AI Error: ' + str(e)
        }))