<!DOCTYPE html>
<html>
<head>
    <title>Your Parcel Delivery Code</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .code-box { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0; }
        .code { font-size: 32px; font-weight: bold; color: #007bff; letter-spacing: 4px; }
        .warning { background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Dear {{ $clientName }},</h2>
        
        <p>Your parcel with tracking code <strong>{{ $trackingCode }}</strong> has been processed.</p>
        
        <div class="code-box">
            <p>Your Delivery Verification Code:</p>
            <div class="code">{{ $code }}</div>
        </div>
        
        <div class="warning">
            <h4>⚠️ IMPORTANT SECURITY NOTICE:</h4>
            <p><strong>DO NOT share this code with anyone except the delivery rider at the time of delivery.</strong></p>
            <p>• The rider will ask for this code to complete the delivery</p>
            <p>• Only provide this code when you receive your parcel</p>
            <p>• Your parcel will NOT be delivered without this verification code</p>
        </div>
        
        <p>Thank you for using our courier service!</p>
        
        <hr>
        <small>This is an automated message. Please do not reply to this email.</small>
    </div>
</body>
</html>