<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; }
        .content { padding: 30px; }
        .info-box { background: #f8f9fa; border-left: 4px solid #667eea; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e9ecef; }
        .info-row:last-child { border-bottom: none; }
        .label { font-weight: bold; color: #495057; }
        .value { color: #212529; }
        .footer { background: #2c3e50; color: white; padding: 20px; text-align: center; font-size: 14px; }
        .btn { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚚 New Delivery Request</h1>
            <p style="margin: 10px 0 0 0; font-size: 16px;">Tracking Code: {{ $deliveryData['tracking_code'] }}</p>
        </div>
        
        <div class="content">
            <p style="font-size: 16px; color: #495057;">Hello Admin,</p>
            <p style="font-size: 14px; color: #6c757d;">A new delivery request has been submitted. Please review the details below:</p>
            
            <div class="info-box">
                <h3 style="margin-top: 0; color: #667eea;">📦 Parcel Details</h3>
                <div class="info-row">
                    <span class="label">Tracking Code:</span>
                    <span class="value">{{ $deliveryData['tracking_code'] }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Merchant:</span>
                    <span class="value">{{ $deliveryData['merchant_name'] }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Payment Method:</span>
                    <span class="value">{{ strtoupper($deliveryData['payment_method']) }}</span>
                </div>
            </div>
            
            <div class="info-box">
                <h3 style="margin-top: 0; color: #667eea;">📍 Pickup Location</h3>
                <div class="info-row">
                    <span class="label">Address:</span>
                    <span class="value">{{ $deliveryData['pickup_location'] }}</span>
                </div>
                <div class="info-row">
                    <span class="label">City:</span>
                    <span class="value">{{ $deliveryData['pickup_city'] }}</span>
                </div>
            </div>
            
            <div class="info-box">
                <h3 style="margin-top: 0; color: #667eea;">🎯 Delivery Location</h3>
                <div class="info-row">
                    <span class="label">Address:</span>
                    <span class="value">{{ $deliveryData['dropoff_location'] }}</span>
                </div>
                <div class="info-row">
                    <span class="label">City:</span>
                    <span class="value">{{ $deliveryData['dropoff_city'] }}</span>
                </div>
            </div>
            
            <div class="info-box">
                <h3 style="margin-top: 0; color: #667eea;">👤 Client Information</h3>
                <div class="info-row">
                    <span class="label">Name:</span>
                    <span class="value">{{ $deliveryData['client_name'] }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Phone:</span>
                    <span class="value">{{ $deliveryData['client_phone'] }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Address:</span>
                    <span class="value">{{ $deliveryData['client_address'] }}</span>
                </div>
                @if(isset($deliveryData['client_email']) && $deliveryData['client_email'])
                <div class="info-row">
                    <span class="label">Email:</span>
                    <span class="value">{{ $deliveryData['client_email'] }}</span>
                </div>
                @endif
            </div>
            
            <div style="text-align: center;">
                <a href="http://127.0.0.1:8000/admin/parcels" class="btn">View in Dashboard</a>
            </div>
        </div>
        
        <div class="footer">
            <p style="margin: 0;">© {{ date('Y') }} Courier Hub. All rights reserved.</p>
            <p style="margin: 5px 0 0 0; font-size: 12px; opacity: 0.8;">This is an automated notification email.</p>
        </div>
    </div>
</body>
</html>
