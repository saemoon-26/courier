#!/usr/bin/env python3
import json
import mysql.connector
import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestClassifier
from sklearn.preprocessing import LabelEncoder
from sklearn.metrics.pairwise import cosine_similarity
import warnings
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
        
        # Get pending parcels
        cursor.execute("""
            SELECT parcel_id, pickup_city, pickup_location, dropoff_city 
            FROM parcel 
            WHERE assigned_to IS NULL AND parcel_status = 'pending'
        """)
        parcels = cursor.fetchall()
        
        # Normalize city names to lowercase
        for parcel in parcels:
            if parcel['pickup_city']:
                parcel['pickup_city'] = parcel['pickup_city'].lower().strip()
        
        # Get available riders
        cursor.execute("""
            SELECT u.id, u.first_name, u.last_name, u.rating, 
                   a.city, a.address,
                   COUNT(p.parcel_id) as active_parcels
            FROM users u
            JOIN address a ON u.id = a.user_id
            LEFT JOIN parcel p ON u.id = p.assigned_to 
                AND p.parcel_status IN ('pending', 'picked_up', 'in_transit')
            WHERE u.role = 'rider'
            GROUP BY u.id, u.first_name, u.last_name, u.rating, a.city, a.address
            HAVING active_parcels < 5
        """)
        riders = cursor.fetchall()
        
        # Normalize city names to lowercase
        for rider in riders:
            if rider['city']:
                rider['city'] = rider['city'].lower().strip()
        
        # Get historical assignments for training
        cursor.execute("""
            SELECT p.pickup_city, p.pickup_location, p.dropoff_city,
                   u.id as rider_id, a.city as rider_city, a.address as rider_address,
                   u.rating, p.parcel_status,
                   CASE WHEN p.parcel_status = 'delivered' THEN 1 ELSE 0 END as success
            FROM parcel p
            JOIN users u ON p.assigned_to = u.id
            JOIN address a ON u.id = a.user_id
            WHERE p.assigned_to IS NOT NULL
            LIMIT 1000
        """)
        training_data = cursor.fetchall()
        
        conn.close()
        return parcels, riders, training_data
    
    def calculate_distance_score(self, pickup_location, rider_address):
        """AI-based text similarity for location matching"""
        pickup_words = set(pickup_location.lower().split())
        rider_words = set(rider_address.lower().split())
        
        if not pickup_words or not rider_words:
            return 0
        
        intersection = pickup_words.intersection(rider_words)
        union = pickup_words.union(rider_words)
        
        # Jaccard similarity
        jaccard = len(intersection) / len(union) if union else 0
        
        # Boost for exact area matches
        area_boost = 0
        for word in intersection:
            if len(word) > 3:  # Meaningful words
                area_boost += 0.1
        
        return jaccard + area_boost
    
    def train_model(self, training_data):
        """Train ML model on historical data"""
        if not training_data:
            return False
        
        df = pd.DataFrame(training_data)
        
        # Feature engineering
        df['distance_score'] = df.apply(
            lambda row: self.calculate_distance_score(
                row['pickup_location'], row['rider_address']
            ), axis=1
        )
        
        df['city_match'] = (df['pickup_city'] == df['rider_city']).astype(int)
        df['rating_score'] = df['rating'].fillna(4.0)
        
        # Prepare features
        features = ['distance_score', 'city_match', 'rating_score']
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
        
        # Filter riders by same city first (STRICT REQUIREMENT)
        city_riders = [r for r in riders if r['city'].lower().strip() == parcel_city]
        
        if not city_riders:
            return None  # N/A - no riders in same city
        
        # Calculate ML scores for each rider
        rider_scores = []
        for rider in city_riders:
            distance_score = self.calculate_distance_score(
                parcel['pickup_location'], rider['address']
            )
            
            features = np.array([[
                distance_score,
                1,  # city_match = 1 (same city)
                rider['rating'] or 4.0
            ]])
            
            # ML prediction probability
            ml_score = self.model.predict_proba(features)[0][1] if hasattr(self.model, 'predict_proba') else 0.5
            
            rider_scores.append({
                'rider': rider,
                'ml_score': ml_score,
                'distance_score': distance_score
            })
        
        # Sort by ML score (highest first)
        rider_scores.sort(key=lambda x: x['ml_score'], reverse=True)
        
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